<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\UnitUser;
use App\Services\OrgScope;
use App\Support\LeaveCalendar;
use App\Support\LeaveCredits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Printable leave form (one request): member details, the leave, the decision and
 * signature lines. The member prints their own; unit checkers those of their unit
 * (M1: every unit); admins with leave.view those in their OrgScope. Printing is
 * audited. Works in the Android app (window.print is bridged).
 */
class LeavePrintController extends Controller
{
    private function load(int $id): object
    {
        $l = DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.id', $id)->select('l.*', 'u.name', 'u.rank', 'u.mother_unit')->first();
        if (!$l) {
            abort(404);
        }
        return $l;
    }

    private function render(Request $request, object $l, string $back, string $who)
    {
        Log::channel('audit')->info('leave.printed', ['leave_id' => $l->id, 'by' => $who, 'ip' => $request->ip()]);
        $pool = LeaveCredits::poolFor($l->leave_type);
        return view('leave.print', [
            'l' => $l,
            'credit' => $pool ? LeaveCredits::balances((int) $l->user_id)[$pool] : null,
            'back' => $back,
            'printedAt' => now()->timezone(LeaveCalendar::TZ),
        ]);
    }

    public function member(Request $request, int $id)
    {
        $l = $this->load($id);
        if ((int) $l->user_id !== (int) $request->user()->id) {
            abort(404);
        }
        return $this->render($request, $l, route('leave.index'), 'member:' . $request->user()->id);
    }

    public function unit(Request $request, int $id)
    {
        $l = $this->load($id);
        $m1 = $request->session()->get('unit_role') === UnitUser::ROLE_M1;
        if (!$m1 && strcasecmp(trim((string) $l->mother_unit), trim((string) $request->session()->get('unit_name', ''))) !== 0) {
            abort(403);
        }
        return $this->render($request, $l, route('unit.leave'), 'unit:' . $request->session()->get('unit_user_id'));
    }

    public function admin(Request $request, OrgScope $scope, int $id)
    {
        $l = $this->load($id);
        if (!$scope->isOrgWide()) {
            $serials = $scope->personnelQuery()->pluck('serial_number')->map(fn ($s) => Personnel::normalizeSerial($s))->all();
            if (!in_array(Personnel::normalizeSerial($l->serial_number), $serials, true)) {
                abort(403);
            }
        }
        return $this->render($request, $l, route('admin.leave.index'), 'admin:' . $request->session()->get('admin_user_id'));
    }
}
