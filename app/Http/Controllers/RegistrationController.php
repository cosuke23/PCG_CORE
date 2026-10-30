<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelRegistration;
use App\Models\Rank;
use App\Services\OrgScope;
use App\Services\RegistrationWorkflow;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin -> Registrations: HR prepares a personnel registration, submits it;
 * a reviewer may endorse or return it; an approver (not the author) approves
 * or rejects. Approval creates the personnel master record. Registrations
 * are scoped by their proposed unit; out-of-scope ones answer 404.
 */
class RegistrationController extends Controller
{
    public function __construct(private OrgScope $scope, private RegistrationWorkflow $workflow)
    {
    }

    private function scoped()
    {
        $ids = $this->scope->unitIds();

        return PersonnelRegistration::query()->when($ids !== null, fn ($q) => $q->whereIn('org_unit_id', $ids));
    }

    private function findScoped(int $id): PersonnelRegistration
    {
        return $this->scoped()->whereKey($id)->firstOrFail();
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(PersonnelRegistration::STATUSES))],
            'q' => 'nullable|string|max:100',
        ]);
        $status = $filters['status'] ?? null;

        $query = $this->scoped()->with(['rank', 'orgUnit'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when(trim($filters['q'] ?? ''), function ($q, $term) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
                $q->where(fn ($w) => $w->where('serial_number', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('first_name', 'like', $like));
            });

        return view('admin.registrations.index', [
            'registrations' => $query->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'returned' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
                ->orderByDesc('updated_at')->paginate(25)->withQueryString(),
            'counts' => $this->scoped()->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status'),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('admin.registrations.form', ['registration' => null] + $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $registration = DB::transaction(function () use ($data) {
            $reg = new PersonnelRegistration($data);
            $reg->forceFill(['status' => 'draft', 'created_by' => session('admin_user_id')])->save();
            $this->workflow->record($reg, 'created', null, 'draft');

            return $reg;
        });

        return $this->afterSave($request, $registration, 'Draft saved.');
    }

    public function show(int $registration)
    {
        $reg = $this->findScoped($registration)->load(['rank', 'orgUnit.parent', 'events', 'personnel']);
        $me = (int) session('admin_user_id');

        return view('admin.registrations.show', [
            'reg' => $reg,
            'duplicates' => $reg->isEditable() || $reg->status === 'submitted' ? $this->workflow->duplicateErrors($reg) : [],
            'isOwn' => $me && in_array($me, [(int) $reg->created_by, (int) $reg->submitted_by], true),
            'canEdit' => AdminPermissions::can('registration.create') && $reg->isEditable(),
            'canReview' => AdminPermissions::can('registration.review') && $reg->status === 'submitted',
            'canDecide' => AdminPermissions::can('registration.approve') && $reg->status === 'submitted',
            'names' => \App\Models\AdminUser::whereIn('id', $reg->events->pluck('admin_user_id')->filter())->pluck('name', 'id'),
        ]);
    }

    public function edit(int $registration)
    {
        $reg = $this->findScoped($registration);
        abort_unless($reg->isEditable(), 404);

        return view('admin.registrations.form', ['registration' => $reg] + $this->formData());
    }

    public function update(Request $request, int $registration)
    {
        $reg = $this->findScoped($registration);
        if (!$reg->isEditable()) {
            return back()->with('error', 'Only drafts and returned registrations can be changed.');
        }

        $data = $this->validated($request);
        DB::transaction(function () use ($reg, $data) {
            $reg->update($data);
            $this->workflow->record($reg, 'updated', $reg->status, $reg->status);
        });

        return $this->afterSave($request, $reg, 'Changes saved.');
    }

    public function submit(int $registration)
    {
        $reg = $this->findScoped($registration);
        $this->workflow->submit($reg);

        return redirect()->route('admin.registrations.show', $reg->id)->with('success', 'Submitted for approval.');
    }

    public function review(Request $request, int $registration)
    {
        $reg = $this->findScoped($registration);
        $data = $request->validate([
            'action' => 'required|in:endorse,return',
            'remarks' => 'required_if:action,return|nullable|string|max:2000',
        ], ['remarks.required_if' => 'Explain what must be corrected.']);

        if ($data['action'] === 'return') {
            $this->workflow->returnForCorrection($reg, $data['remarks']);
            $message = 'Returned for correction.';
        } else {
            $this->workflow->endorse($reg, $data['remarks'] ?? null);
            $message = 'Endorsed for approval.';
        }

        return redirect()->route('admin.registrations.show', $reg->id)->with('success', $message);
    }

    public function decide(Request $request, int $registration)
    {
        $reg = $this->findScoped($registration);
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'remarks' => 'required_if:action,reject|nullable|string|max:2000',
        ], ['remarks.required_if' => 'Give the reason for rejecting.']);

        if ($data['action'] === 'approve') {
            $person = $this->workflow->approve($reg, $data['remarks'] ?? null);

            return redirect()->route('admin.personnel.show', $person->id)
                ->with('success', "Registration approved. Personnel record {$person->serial_number} created.");
        }

        $this->workflow->reject($reg, $data['remarks']);

        return redirect()->route('admin.registrations.show', $reg->id)->with('success', 'Registration rejected.');
    }

    private function afterSave(Request $request, PersonnelRegistration $reg, string $message)
    {
        if ($request->input('intent') === 'submit') {
            try {
                $this->workflow->submit($reg);
                $message = 'Submitted for approval.';
            } catch (\Illuminate\Validation\ValidationException $e) {
                // the draft is saved; show it with what blocks submission (never re-post the create form)
                return redirect()->route('admin.registrations.show', $reg->id)
                    ->with('warning', 'Saved as a draft, but it cannot be submitted yet.')->withErrors($e->errors());
            }
        }

        return redirect()->route('admin.registrations.show', $reg->id)->with('success', $message);
    }

    private function validated(Request $request): array
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
            'rank_id' => ['nullable', Rule::exists('ranks', 'id')->where('is_active', true)],
            'rank_date' => 'nullable|date',
            'date_entered_service' => 'nullable|date',
            'org_unit_id' => ['required', 'integer', Rule::exists('org_units', 'id')->where('is_active', true)],
            'position' => 'nullable|string|max:255',
            'assignment_start_date' => 'nullable|date',
            'order_reference' => 'nullable|string|max:255',
            'confirmed_not_duplicate' => 'nullable|boolean',
            'intent' => 'nullable|in:draft,submit',
        ]);

        $data['serial_number'] = Personnel::normalizeSerial($data['serial_number']);
        if (!preg_match('/^[A-Z0-9][A-Z0-9 \/-]*$/', $data['serial_number'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['serial_number' => 'Use letters, numbers, spaces, / and - only.']);
        }
        if (!$this->scope->canUseUnit((int) $data['org_unit_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['org_unit_id' => 'You can only register personnel for your own command or unit.']);
        }
        $data['email'] = isset($data['email']) ? strtolower($data['email']) : null;
        $data['confirmed_not_duplicate'] = $request->boolean('confirmed_not_duplicate');
        unset($data['intent']);

        return $data;
    }

    private function formData(): array
    {
        return [
            'ranks' => Rank::ordered()->where('is_active', true)->get(),
            'units' => OrgUnit::flatTree()->filter(fn ($u) => $u->is_active && $this->scope->canUseUnit($u->id))->values(),
        ];
    }
}
