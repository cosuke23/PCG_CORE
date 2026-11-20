<?php

namespace App\Http\Controllers;

use App\Support\DbError;
use App\Support\LeaveCalendar;
use App\Support\LeaveTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Leave for members (owner request 2026-11-20): pick the dates on the calendar,
 * choose the type of leave and file it; the unit checker approves it
 * (UnitLeaveController). Approved leave of everyone in the member's unit
 * (users.mother_unit) shows on the calendar, sensitive types as "On leave".
 * The member is always $request->user(), never an input.
 */
class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $month = LeaveCalendar::month($request->query('month'));
        [$from, $to] = LeaveCalendar::range($month);
        $unit = trim((string) ($user->mother_unit ?? ''));

        $rows = $unit !== '' ? LeaveCalendar::approved($unit, $from, $to) : collect();
        // the member's own pending leave shows too (dashed), so they see what is waiting
        $ownPending = DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.user_id', $user->id)->whereRaw('LOWER(l.status) = ?', ['pending'])
            ->where('l.start_date', '<=', $to)->where('l.end_date', '>=', $from)
            ->select('l.id', 'l.user_id', 'l.serial_number', 'l.leave_type', 'l.start_date', 'l.end_date', 'l.days', 'l.status',
                'u.name', 'u.rank', 'u.mother_unit')->get();
        if ($unit === '') {
            $rows = LeaveCalendar::approved(null, $from, $to)->where('user_id', $user->id)->values();
        }
        $byDay = LeaveCalendar::byDay($rows->concat($ownPending), $from, $to, (int) $user->id);

        $mine = DB::table('leave_requests')->where('user_id', $user->id)
            ->orderByDesc('start_date')->orderByDesc('id')->limit(30)->get();

        $today = LeaveCalendar::today();

        return view('applicant.leave', [
            'user' => $user,
            'unit' => $unit,
            'month' => $month,
            'weeks' => LeaveCalendar::grid($month),
            'byDay' => $byDay,
            'legend' => LeaveCalendar::legend($byDay),
            'mine' => $mine,
            'types' => LeaveTypes::TYPES,
            'minDate' => $today->copy()->subDays(LeaveTypes::BACKDATE_DAYS)->toDateString(),
            'today' => $today->toDateString(),
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $today = LeaveCalendar::today();
        $data = $request->validate([
            'leave_type' => ['required', Rule::in(LeaveTypes::keys())],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $today->copy()->subDays(LeaveTypes::BACKDATE_DAYS)->toDateString()],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'start_date.after_or_equal' => 'Leave can start at most ' . LeaveTypes::BACKDATE_DAYS . ' days ago.',
            'end_date.after_or_equal' => 'The last day must be on or after the first day.',
        ]);

        $start = Carbon::parse($data['start_date'], LeaveCalendar::TZ);
        $end = Carbon::parse($data['end_date'], LeaveCalendar::TZ);
        $days = $start->diffInDays($end) + 1;
        $max = LeaveTypes::maxDays($data['leave_type']);
        if ($days > $max) {
            throw ValidationException::withMessages([
                'end_date' => LeaveTypes::label($data['leave_type']) . ' can cover at most ' . $max . ' days in one request (this one is ' . $days . ').',
            ]);
        }

        $overlap = DB::table('leave_requests')->where('user_id', $user->id)
            ->whereIn(DB::raw('LOWER(status)'), ['pending', 'approved'])
            ->where('start_date', '<=', $end->toDateString())->where('end_date', '>=', $start->toDateString())
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'You already have leave filed or approved on some of these days. Cancel it first or choose other dates.',
            ]);
        }

        try {
            $id = DB::table('leave_requests')->insertGetId([
                'user_id' => $user->id,
                'serial_number' => (string) $user->serial_number,
                'unit_name' => $user->mother_unit,
                'leave_type' => $data['leave_type'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('leave.store failed', ['user_id' => $user->id, 'reason' => DbError::reason($e)]);
            return back()->withInput()->with('error', 'Your leave could not be filed. Please try again.');
        }

        Log::channel('audit')->info('leave.filed', [
            'leave_id' => $id, 'user_id' => $user->id, 'serial_number' => $user->serial_number,
            'type' => $data['leave_type'], 'start' => $start->toDateString(), 'end' => $end->toDateString(), 'days' => $days,
        ]);

        return redirect()->route('leave.index', ['month' => $start->format('Y-m')])
            ->with('success', LeaveTypes::label($data['leave_type']) . ' for ' . LeaveCalendar::dateLabel($start->toDateString(), $end->toDateString())
                . ' (' . $days . ' ' . ($days === 1 ? 'day' : 'days') . ') is filed and waiting for your unit\'s approval.');
    }

    /** Withdraw a request that is still waiting, or approved leave that has not started. */
    public function cancel(Request $request, int $id)
    {
        $user = $request->user();
        $leave = DB::table('leave_requests')->where('id', $id)->where('user_id', $user->id)->first();
        if (!$leave) {
            abort(404);
        }
        $status = strtolower((string) $leave->status);
        $started = (string) $leave->start_date <= LeaveCalendar::today()->toDateString();
        if (!($status === 'pending' || ($status === 'approved' && !$started))) {
            return back()->with('error', 'This leave can no longer be cancelled here. Ask your unit if it needs to change.');
        }

        DB::table('leave_requests')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        Log::channel('audit')->info('leave.cancelled', ['leave_id' => $id, 'user_id' => $user->id, 'was' => $status]);

        return back()->with('success', 'Your ' . strtolower(LeaveTypes::label($leave->leave_type)) . ' for '
            . LeaveCalendar::dateLabel((string) $leave->start_date, (string) $leave->end_date) . ' is cancelled.');
    }
}
