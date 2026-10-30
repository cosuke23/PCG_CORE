<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\Rank;
use App\Services\OrgScope;
use App\Services\PersonnelAssignments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin -> Personnel: the personnel master, limited to the admin's
 * organisational scope (OrgScope). Out-of-scope records answer 404 so
 * their existence is not disclosed.
 */
class PersonnelController extends Controller
{
    public function __construct(private OrgScope $scope)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'unit' => 'nullable|integer',
            'classification' => ['nullable', Rule::in(array_keys(Rank::CLASSIFICATIONS))],
            'status' => ['nullable', Rule::in(array_keys(Personnel::STATUSES))],
            'unassigned' => 'nullable|boolean',
            'incomplete' => 'nullable|boolean',
        ]);

        $query = $this->scope->personnelQuery()->with(['rank', 'currentAssignment.orgUnit']);

        if ($q = trim($filters['q'] ?? '')) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $query->where(fn ($w) => $w->where('full_name', 'like', $like)->orWhere('serial_number', 'like', $like));
        }
        if (!empty($filters['unit']) && $this->scope->canUseUnit((int) $filters['unit']) && ($unit = OrgUnit::find($filters['unit']))) {
            $ids = $unit->subtreeIds();
            $query->whereHas('currentAssignment', fn ($a) => $a->whereIn('org_unit_id', $ids));
        }
        if (!empty($filters['classification'])) {
            $query->where('classification', $filters['classification']);
        }
        if (!empty($filters['status'])) {
            $query->where('service_status', $filters['status']);
        }
        if (!empty($filters['incomplete'])) {
            $query->where(fn ($w) => $w->whereNull('rank_id')->orWhereNull('date_of_birth')->orWhereNull('date_entered_service'));
        }
        if (!empty($filters['unassigned']) && $this->scope->isOrgWide()) {
            $query->whereDoesntHave('currentAssignment');
        }

        return view('admin.personnel.index', [
            'personnel' => $query->orderBy('full_name')->paginate(25)->withQueryString(),
            'units' => $this->unitOptions(),
            'filters' => $filters,
            'scopeUnit' => $this->scope->scopeUnit(),
            'orgWide' => $this->scope->isOrgWide(),
            'unassignedCount' => $this->scope->isOrgWide() ? Personnel::whereDoesntHave('currentAssignment')->count() : null,
        ]);
    }

    public function show(Personnel $personnel)
    {
        abort_unless($this->scope->canSee($personnel), 404);

        $personnel->load(['rank', 'assignments.orgUnit.parent', 'user:id,email,status', 'promotions.fromRank', 'promotions.toRank', 'trainings', 'documents.category']);
        $restricted = \App\Support\AdminPermissions::can('documents.restricted');

        return view('admin.personnel.show', [
            'person' => $personnel,
            'units' => $this->unitOptions(true),
            'ranks' => Rank::ordered()->where('is_active', true)->get(),
            'documents' => $personnel->documents->filter(fn ($d) => $restricted || !$d->category->is_restricted),
            'hiddenDocuments' => $restricted ? 0 : $personnel->documents->filter(fn ($d) => $d->category->is_restricted)->count(),
            'categories' => \App\Models\DocumentCategory::where('is_active', true)->orderBy('sort_order')->get()
                ->filter(fn ($c) => $restricted || !$c->is_restricted),
        ]);
    }

    public function create()
    {
        // new personnel enter the master records only through an approved registration
        return redirect()->route('admin.registrations.create');
    }

    public function edit(Personnel $personnel)
    {
        abort_unless($this->scope->canSee($personnel), 404);

        return view('admin.personnel.edit', ['person' => $personnel]);
    }

    /**
     * Controlled editing: identity details only. The serial number never
     * changes here; rank changes only through promotions; placement only
     * through assignments.
     */
    public function update(Request $request, Personnel $personnel)
    {
        abort_unless($this->scope->canSee($personnel), 404);

        $data = $request->validate([
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'email' => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:50',
            'date_entered_service' => 'nullable|date',
            'service_status' => ['required', Rule::in(array_keys(Personnel::STATUSES))],
            'change_reason' => 'required|string|max:500',
        ], ['change_reason.required' => 'Say why the record is being changed (kept in the audit log).']);

        $before = $personnel->only(array_keys($data));
        $data['full_name'] = trim(preg_replace('/\s+/', ' ', implode(' ', [$data['first_name'], $data['middle_name'] ?? '', $data['last_name'], $data['suffix'] ?? ''])));
        $data['email'] = isset($data['email']) ? strtolower($data['email']) : null;
        $data['updated_by'] = session('admin_user_id');
        $reason = $data['change_reason'];
        unset($data['change_reason']);

        $personnel->update($data);

        $changed = collect($personnel->getChanges())->except(['updated_at', 'updated_by', 'full_name'])->keys()->all();
        \App\Support\AdminAudit::record('personnel.updated', $personnel->serial_number, [
            'fields' => $changed, 'reason' => $reason,
            'before' => collect($before)->only($changed)->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v)->all(),
        ]);

        return redirect()->route('admin.personnel.show', $personnel->id)
            ->with('success', $changed ? 'Record updated: ' . implode(', ', str_replace('_', ' ', $changed)) . '.' : 'No changes.');
    }

    /** Record a promotion (rank history). No eligibility rules are applied. */
    public function promote(Request $request, Personnel $personnel)
    {
        abort_unless($this->scope->canSee($personnel), 404);

        $data = $request->validate([
            'to_rank_id' => ['required', Rule::exists('ranks', 'id')->where('is_active', true)],
            'effective_date' => 'required|date',
            'order_reference' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($personnel, $data) {
            $person = Personnel::whereKey($personnel->id)->lockForUpdate()->first();
            if ((int) $person->rank_id === (int) $data['to_rank_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['to_rank_id' => 'That is already the current rank.']);
            }
            $last = \App\Models\Promotion::where('personnel_id', $person->id)->max('effective_date');
            if ($last && \Carbon\Carbon::parse($data['effective_date'])->lte(\Carbon\Carbon::parse($last))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['effective_date' => 'Must be after the last recorded promotion (' . \Carbon\Carbon::parse($last)->format('d M Y') . ').']);
            }

            $rank = Rank::find($data['to_rank_id']);
            \App\Models\Promotion::create($data + ['personnel_id' => $person->id, 'from_rank_id' => $person->rank_id, 'created_by' => session('admin_user_id')]);
            $person->update(['rank_id' => $rank->id, 'rank_date' => $data['effective_date'], 'classification' => $rank->classification, 'updated_by' => session('admin_user_id')]);
        });

        return redirect()->route('admin.personnel.show', $personnel->id)->with('success', 'Promotion recorded. The previous rank is kept in the rank history.');
    }

    public function addTraining(Request $request, Personnel $personnel)
    {
        abort_unless($this->scope->canSee($personnel), 404);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'kind' => ['required', Rule::in(array_keys(\App\Models\PersonnelTraining::KINDS))],
            'provider' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'certificate_number' => 'nullable|string|max:100',
            'valid_until' => 'nullable|date|after_or_equal:start_date',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $personnel->trainings()->create($data + ['created_by' => session('admin_user_id')]);

        return redirect()->route('admin.personnel.show', $personnel->id)->with('success', 'Training / qualification recorded.');
    }

    public function assign(Request $request, Personnel $personnel, PersonnelAssignments $assignments)
    {
        abort_unless($this->scope->canSee($personnel), 404);

        $data = $request->validate([
            'org_unit_id' => ['required', 'integer', Rule::exists('org_units', 'id')->where('is_active', true)],
            'position' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'order_reference' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:2000',
        ]);

        if (!$this->scope->canUseUnit((int) $data['org_unit_id'])) {
            return back()->withInput()->withErrors(['org_unit_id' => 'You can only assign within your own command or unit. Transfers to another command are done by headquarters.']);
        }

        $assignment = $assignments->assign($personnel, $data, session('admin_user_id'));

        return redirect()->route('admin.personnel.show', $personnel->id)
            ->with('success', 'Assignment to ' . $assignment->orgUnit->code . ' recorded. The previous assignment is kept in the history.');
    }

    /** Active units the admin may use, in tree order. */
    private function unitOptions(bool $activeOnly = false)
    {
        return OrgUnit::flatTree()
            ->filter(fn ($u) => $this->scope->canUseUnit($u->id) && (!$activeOnly || $u->is_active))
            ->values();
    }
}
