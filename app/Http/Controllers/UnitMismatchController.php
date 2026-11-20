<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Services\OrgScope;
use App\Services\PersonnelAssignments;
use App\Support\AdminAudit;
use App\Support\AdminPermissions;
use App\Support\LeaveCalendar;
use App\Support\UnitMismatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Members whose sign-up unit (users.mother_unit) and HRMIS assignment disagree
 * (App\Support\UnitMismatch), with two fixes: copy the assignment's unit into the
 * sign-up unit, or record a new assignment to the sign-up unit (history kept).
 * personnel.view to see, personnel.edit to fix; both within the admin's OrgScope.
 */
class UnitMismatchController extends Controller
{
    public function __construct(private OrgScope $scope)
    {
    }

    private function serials(): ?array
    {
        return $this->scope->isOrgWide() ? null : $this->scope->personnelQuery()->pluck('serial_number')
            ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
    }

    private function row(int $userId): object
    {
        $row = UnitMismatch::rows($this->serials())->firstWhere('user_id', $userId);
        abort_unless($row, 404);
        return $row;
    }

    public function index(Request $request)
    {
        $state = (string) $request->query('state', 'problems');
        $rows = UnitMismatch::rows($this->serials());
        $counts = $rows->countBy('state');
        $shown = match ($state) {
            'all' => $rows,
            'problems' => $rows->where('state', '!=', 'ok'),
            default => $rows->where('state', $state),
        };
        return view('admin.unit-mismatch', [
            'rows' => $shown->values(), 'counts' => $counts, 'total' => $rows->count(), 'state' => $state,
            'problems' => $rows->where('state', '!=', 'ok')->count(),
            'canFix' => AdminPermissions::can('personnel.edit'),
            'scopeName' => $this->scope->isOrgWide() ? 'all units' : (optional($this->scope->scopeUnit())->name ?? 'your command'),
        ]);
    }

    /** Fix 1: users.mother_unit := the unit of the current assignment (the checker-level unit). */
    public function useAssignment(Request $request, int $user)
    {
        $row = $this->row($user);
        abort_unless($row->can_use_assignment, 422, 'Nothing to change for this member.');
        DB::table('users')->where('id', $row->user_id)->update(['mother_unit' => $row->suggested_mother_unit, 'updated_at' => now()]);
        AdminAudit::record('unit.aligned_to_assignment', 'users:' . $row->user_id,
            ['serial_number' => $row->serial_number, 'from' => $row->mother_unit, 'to' => $row->suggested_mother_unit]);
        return back()->with('success', "{$row->name}'s sign-up unit is now {$row->suggested_mother_unit}, the unit of their assignment. Unit checkers of {$row->suggested_mother_unit} now see their records.");
    }

    /** Fix 2: record an assignment to the sign-up unit from today (the current one ends yesterday). */
    public function useMotherUnit(Request $request, int $user, PersonnelAssignments $assignments)
    {
        $row = $this->row($user);
        abort_unless($row->can_use_mother_unit, 422, 'Nothing to change for this member.');
        if (!$this->scope->canUseUnit((int) $row->mother_unit_id)) {
            return back()->with('error', "{$row->mother_unit} is outside your command or unit. Transfers to another command are done by headquarters.");
        }
        try {
            $assignments->assign(Personnel::findOrFail($row->personnel_id), [
                'org_unit_id' => $row->mother_unit_id, 'start_date' => LeaveCalendar::today()->toDateString(),
                'remarks' => 'Aligned with the unit chosen at registration (unit mismatch report)',
            ], session('admin_user_id'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        AdminAudit::record('unit.assigned_to_mother_unit', 'personnel:' . $row->personnel_id,
            ['serial_number' => $row->serial_number, 'from' => $row->assigned?->code, 'to' => $row->mother_unit]);
        return back()->with('success', "{$row->name} is now assigned to {$row->mother_unit} from today. The previous assignment is kept in the history.");
    }
}
