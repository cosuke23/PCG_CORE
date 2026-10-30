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

        $personnel->load(['rank', 'assignments.orgUnit.parent', 'user:id,email,status']);

        return view('admin.personnel.show', [
            'person' => $personnel,
            'units' => $this->unitOptions(true),
        ]);
    }

    public function create()
    {
        return view('admin.personnel.create', [
            'ranks' => Rank::ordered()->where('is_active', true)->get(),
            'units' => $this->unitOptions(true),
            'orgWide' => $this->scope->isOrgWide(),
        ]);
    }

    public function store(Request $request, PersonnelAssignments $assignments)
    {
        $data = $request->validate([
            'serial_number' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'email' => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:50',
            'rank_id' => ['required', Rule::exists('ranks', 'id')->where('is_active', true)],
            'rank_date' => 'nullable|date',
            'date_entered_service' => 'nullable|date',
            // scoped admins must place the person inside their scope
            'org_unit_id' => [$this->scope->isOrgWide() ? 'nullable' : 'required', 'integer', Rule::exists('org_units', 'id')->where('is_active', true)],
            'position' => 'nullable|string|max:255',
            'start_date' => 'required_with:org_unit_id|nullable|date',
            'order_reference' => 'nullable|string|max:255',
            'confirm_not_duplicate' => 'nullable|boolean',
        ]);

        $serial = Personnel::normalizeSerial($data['serial_number']);
        if (!preg_match('/^[A-Z0-9][A-Z0-9 \/-]*$/', $serial)) {
            return back()->withInput()->withErrors(['serial_number' => 'Use letters, numbers, spaces, / and - only.']);
        }

        if ($this->findBySerial($serial)) {
            return back()->withInput()->withErrors(['serial_number' => 'A personnel record with this serial number already exists. Search for it instead of creating a second record.']);
        }

        if (!empty($data['org_unit_id']) && !$this->scope->canUseUnit((int) $data['org_unit_id'])) {
            return back()->withInput()->withErrors(['org_unit_id' => 'You can only assign personnel within your own command or unit.']);
        }

        $fullName = trim(preg_replace('/\s+/', ' ', implode(' ', [$data['first_name'], $data['middle_name'] ?? '', $data['last_name'], $data['suffix'] ?? ''])));

        // possible duplicate: same name and date of birth under another serial
        if (!$request->boolean('confirm_not_duplicate')) {
            $similar = Personnel::whereRaw('LOWER(last_name) = ? AND LOWER(first_name) = ?', [mb_strtolower($data['last_name']), mb_strtolower($data['first_name'])])
                ->when($data['date_of_birth'] ?? null, fn ($q, $dob) => $q->whereDate('date_of_birth', $dob))
                ->count();
            if ($similar) {
                return back()->withInput()->with('possible_duplicate', $similar)
                    ->withErrors(['last_name' => "{$similar} existing record(s) have the same name" . (!empty($data['date_of_birth']) ? ' and date of birth' : '') . '. Check that this is not the same person, then tick the confirmation box.']);
            }
        }

        $rank = Rank::find($data['rank_id']);
        $adminId = session('admin_user_id');

        $person = DB::transaction(function () use ($data, $serial, $fullName, $rank, $adminId, $assignments) {
            $userId = $this->matchingLogin($serial);

            $person = Personnel::create([
                'serial_number' => $serial,
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'suffix' => $data['suffix'] ?? null,
                'full_name' => $fullName,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'email' => isset($data['email']) ? strtolower($data['email']) : null,
                'contact_number' => $data['contact_number'] ?? null,
                'classification' => $rank->classification,
                'rank_id' => $rank->id,
                'rank_date' => $data['rank_date'] ?? null,
                'date_entered_service' => $data['date_entered_service'] ?? null,
                'service_status' => 'active',
                'user_id' => $userId,
                'source' => 'manual',
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            if (!empty($data['org_unit_id'])) {
                $assignments->assign($person, $data, $adminId);
            }

            return $person;
        });

        return redirect()->route('admin.personnel.show', $person->id)->with('success', "Personnel record {$person->serial_number} created.");
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

    private function findBySerial(string $serial): ?Personnel
    {
        return Personnel::whereRaw('UPPER(TRIM(serial_number)) = ?', [$serial])->first();
    }

    /** Link the existing personnel login when exactly one unlinked account has this serial. */
    private function matchingLogin(string $serial): ?int
    {
        $ids = DB::table('users')->whereRaw('UPPER(TRIM(serial_number)) = ?', [$serial])->pluck('id');
        if ($ids->count() !== 1 || Personnel::where('user_id', $ids->first())->exists()) {
            return null;
        }

        return (int) $ids->first();
    }

    /** Active units the admin may use, in tree order. */
    private function unitOptions(bool $activeOnly = false)
    {
        return OrgUnit::flatTree()
            ->filter(fn ($u) => $this->scope->canUseUnit($u->id) && (!$activeOnly || $u->is_active))
            ->values();
    }
}
