<?php

namespace App\Http\Controllers;

use App\Models\UnitUser;
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
                ->whereIn('id', DB::table('leave_requests')->select('user_id'))
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
        $data = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,decline'],
        ], ['note.required_if' => 'Say why the leave is declined, so the member knows what to change.']);

        $leave = DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.id', $id)->select('l.*', 'u.name', 'u.mother_unit')->first();
        if (!$leave) {
            abort(404);
        }
        $own = strcasecmp(trim((string) $leave->mother_unit), trim((string) $request->session()->get('unit_name', ''))) === 0;
        if (!$this->isM1($request) && !$own) {
            abort(403);
        }
        if (strtolower((string) $leave->status) !== 'pending') {
            return back()->with('error', 'This request was already decided or withdrawn.');
        }

        if ($data['decision'] === 'approve') {
            $clash = DB::table('leave_requests')->where('user_id', $leave->user_id)->where('id', '<>', $id)
                ->whereRaw('LOWER(status) = ?', ['approved'])
                ->where('start_date', '<=', $leave->end_date)->where('end_date', '>=', $leave->start_date)->exists();
            if ($clash) {
                return back()->with('error', $leave->name . ' already has approved leave on some of these days. Decline this request with a note instead.');
            }
        }

        if ($data['decision'] === 'approve'
            && ($short = LeaveCredits::shortfall((int) $leave->user_id, $leave->leave_type, (float) $leave->days, $id))) {
            return back()->with('error', $leave->name . ': ' . $short . ' Adjust the credits first or decline with a note.');
        }

        $status = $data['decision'] === 'approve' ? 'approved' : 'declined';
        $by = ['type' => 'unit', 'id' => (int) $request->session()->get('unit_user_id'),
            'name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email'))];
        DB::transaction(function () use ($status, $leave, $id, $by, $request, $data) {
            if ($status === 'approved' && ($pool = LeaveCredits::poolFor($leave->leave_type))) {
                LeaveCredits::post((int) $leave->user_id, (string) $leave->serial_number, $pool, 'used', -(float) $leave->days,
                    (string) $leave->start_date, LeaveTypes::label($leave->leave_type) . ', ' . LeaveCalendar::dateLabel((string) $leave->start_date, (string) $leave->end_date), $id, $by);
            }
            DB::table('leave_requests')->where('id', $id)->update([
                'status' => $status,
                'reviewed_by' => (int) $request->session()->get('unit_user_id'),
                'reviewer_name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email')),
                'reviewed_at' => now(),
                'review_note' => $data['note'] ?? null,
                'updated_at' => now(),
            ]);
        });

        Log::channel('audit')->info('leave.' . $status, [
            'leave_id' => $id, 'member_user_id' => $leave->user_id, 'serial_number' => $leave->serial_number,
            'type' => $leave->leave_type, 'start' => $leave->start_date, 'end' => $leave->end_date,
            'unit_user_id' => $request->session()->get('unit_user_id'), 'ip' => $request->ip(),
        ]);

        return back()->with('success', LeaveTypes::label($leave->leave_type) . ' of ' . $leave->name . ' for '
            . LeaveCalendar::dateLabel((string) $leave->start_date, (string) $leave->end_date) . ' is ' . $status . '.');
    }
}
