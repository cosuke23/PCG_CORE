<?php

namespace App\Http\Controllers;

use App\Models\UnitUser;
use App\Support\LeaveActions;
use App\Support\LeaveCalendar;
use App\Support\LeaveCredits;
use App\Support\LeaveTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Leave approval for unit checkers (owner request 2026-11-20). Own-unit checkers
 * see and decide leave of members whose users.mother_unit is their unit; M1
 * checkers see every unit (same scope as document review). Decisions go to the
 * audit log; the unit calendar shows approved leave with the real type, since
 * the checker decides it.
 */
class UnitLeaveController extends Controller
{
    private function scopeUnit(Request $request): ?string
    {
        if ($request->session()->get('unit_role') === UnitUser::ROLE_M1) {
            $filter = trim((string) $request->query('unit', ''));
            return $filter !== '' ? $filter : null;          // null = all units
        }
        return trim((string) $request->session()->get('unit_name', ''));
    }

    private function isM1(Request $request): bool
    {
        return $request->session()->get('unit_role') === UnitUser::ROLE_M1;
    }

    public function index(Request $request)
    {
        $unit = $this->scopeUnit($request);
        $month = LeaveCalendar::month($request->query('month'));
        [$from, $to] = LeaveCalendar::range($month);

        $pending = DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->whereRaw('LOWER(l.status) = ?', ['pending'])
            ->select('l.*', 'u.name', 'u.rank', 'u.mother_unit')
            ->orderBy('l.start_date')->orderBy('l.id');
        if ($unit !== null) {
            $pending->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower($unit)]);
        }
        $pending = $pending->get()->each(function ($r) {
            $pool = LeaveCredits::poolFor($r->leave_type);
            $r->credit = $pool ? LeaveCredits::balances((int) $r->user_id)[$pool] : null;
            $r->shortfall = $pool ? LeaveCredits::shortfall((int) $r->user_id, $r->leave_type, (float) $r->days, (int) $r->id) : null;
        });

        $rows = $unit === '' ? collect() : LeaveCalendar::approved($unit, $from, $to);
        $byDay = LeaveCalendar::byDay($rows, $from, $to, null, true);

        $units = $this->isM1($request)
            ? DB::table('users')->whereNotNull('mother_unit')->where('mother_unit', '<>', '')
                ->distinct()->orderBy('mother_unit')->pluck('mother_unit')
            : collect();

        $query = array_filter(['unit' => $this->isM1($request) ? $unit : null]);

        return view('unit.leave', [
            'unit' => $unit,
            'isM1' => $this->isM1($request),
            'units' => $units,
            'pending' => $pending,
            'month' => $month,
            'weeks' => LeaveCalendar::grid($month),
            'byDay' => $byDay,
            'legend' => LeaveCalendar::legend($byDay),
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
            'query' => $query,
        ]);
    }

    public function review(Request $request, int $id)
    {
        $data = $request->validate(LeaveActions::reviewRules(), LeaveActions::reviewMessages());
        $leave = LeaveActions::request($id);
        if (!$leave) {
            abort(404);
        }
        $own = strcasecmp(trim((string) $leave->mother_unit), trim((string) $request->session()->get('unit_name', ''))) === 0;
        if (!$this->isM1($request) && !$own) {
            abort(403);
        }
        $by = ['type' => 'unit', 'id' => (int) $request->session()->get('unit_user_id'),
            'name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email'))];
        [$ok, $message] = LeaveActions::decide($leave, $data['decision'], $data['note'] ?? null, $by, $request->ip());

        return back()->with($ok ? 'success' : 'error', $message);
    }
}
