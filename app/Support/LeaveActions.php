<?php

namespace App\Support;

use App\Mail\LeaveDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * The two decisions of the leave module, shared by unit checkers
 * (UnitLeaveController, UnitLeaveCreditController) and the admin console
 * (AdminLeaveController): deciding a leave request and changing a member's
 * credits. Callers check who may act on the member first; this applies the
 * rules, writes, and audits. $by = ['type' => unit|admin, 'id' => .., 'name' => ..].
 */
class LeaveActions
{
    public static function reviewRules(): array
    {
        return [
            'decision' => ['required', 'in:approve,decline'],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,decline'],
        ];
    }

    public static function reviewMessages(): array
    {
        return ['note.required_if' => 'Say why the leave is declined, so the member knows what to change.'];
    }

    /** Pending request joined with its member, or null. */
    public static function request(int $id): ?object
    {
        return DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.id', $id)->select('l.*', 'u.name', 'u.mother_unit')->first();
    }

    /**
     * Approve or decline. Returns [true, message] or [false, error]. Approval takes
     * the days from the member's credits in the same transaction.
     */
    public static function decide(object $leave, string $decision, ?string $note, array $by, ?string $ip = null): array
    {
        if (strtolower((string) $leave->status) !== 'pending') {
            return [false, 'This request was already decided or withdrawn.'];
        }
        $id = (int) $leave->id;
        if ($decision === 'approve') {
            $clash = DB::table('leave_requests')->where('user_id', $leave->user_id)->where('id', '<>', $id)
                ->whereRaw('LOWER(status) = ?', ['approved'])
                ->where('start_date', '<=', $leave->end_date)->where('end_date', '>=', $leave->start_date)->exists();
            if ($clash) {
                return [false, $leave->name . ' already has approved leave on some of these days. Decline this request with a note instead.'];
            }
            if ($short = LeaveCredits::shortfall((int) $leave->user_id, $leave->leave_type, (float) $leave->days, $id)) {
                return [false, $leave->name . ': ' . $short . ' Adjust the credits first or decline with a note.'];
            }
        }

        $status = $decision === 'approve' ? 'approved' : 'declined';
        DB::transaction(function () use ($status, $leave, $id, $by, $note) {
            if ($status === 'approved' && ($pool = LeaveCredits::poolFor($leave->leave_type))) {
                LeaveCredits::post((int) $leave->user_id, (string) $leave->serial_number, $pool, 'used', -(float) $leave->days,
                    (string) $leave->start_date, LeaveTypes::label($leave->leave_type) . ', '
                        . LeaveCalendar::dateLabel((string) $leave->start_date, (string) $leave->end_date), $id, $by);
            }
            DB::table('leave_requests')->where('id', $id)->update([
                'status' => $status,
                'reviewed_by' => ($by['type'] ?? '') === 'unit' ? ($by['id'] ?? null) : null,   // unit_users.id; admins are in the audit log
                'reviewer_name' => (string) ($by['name'] ?? '') . (($by['type'] ?? '') === 'admin' ? ' (admin)' : ''),
                'reviewed_at' => now(),
                'review_note' => $note,
                'updated_at' => now(),
            ]);
        });

        Log::channel('audit')->info('leave.' . $status, [
            'leave_id' => $id, 'member_user_id' => $leave->user_id, 'serial_number' => $leave->serial_number,
            'type' => $leave->leave_type, 'start' => $leave->start_date, 'end' => $leave->end_date,
            'by_type' => $by['type'] ?? null, 'by_id' => $by['id'] ?? null, 'ip' => $ip,
        ]);

        self::notifyMember($leave, $status === 'approved', $note);

        return [true, LeaveTypes::label($leave->leave_type) . ' of ' . $leave->name . ' for '
            . LeaveCalendar::dateLabel((string) $leave->start_date, (string) $leave->end_date) . ' is ' . $status . '.'];
    }

    /** Email the member the decision. A mail problem never undoes the decision; it is logged. */
    private static function notifyMember(object $leave, bool $approved, ?string $note): void
    {
        if (!config('pcg.leave.notify_members', true)) {
            return;
        }
        $email = trim((string) DB::table('users')->where('id', $leave->user_id)->value('email'));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            Mail::to($email)->send(new LeaveDecided(
                (string) $leave->name, LeaveTypes::label($leave->leave_type),
                LeaveCalendar::dateLabel((string) $leave->start_date, (string) $leave->end_date), (int) $leave->days,
                $approved, $note, route('leave.index', ['month' => substr((string) $leave->start_date, 0, 7)]),
                'PCG HRMIS',
            ));
        } catch (\Throwable $e) {
            Log::warning('leave.notify_failed', ['leave_id' => $leave->id, 'error' => class_basename($e)]);
        }
    }

    public static function creditRules(?string $action): array
    {
        return [
            'action' => ['required', Rule::in(['opening', 'grant', 'adjustment'])],
            'pool' => ['required_if:action,adjustment', 'nullable', Rule::in(array_keys(LeaveCredits::POOLS))],
            'days' => ['required', 'numeric', 'between:-365,365'],
            'effective_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . LeaveCalendar::today()->copy()->addDays(31)->toDateString()],
            'note' => ['nullable', 'string', 'max:500', Rule::requiredIf(in_array($action, ['grant', 'adjustment'], true))],
        ];
    }

    public static function creditMessages(): array
    {
        return ['note.required' => 'Say what the credit is for, e.g. the deployment or the reason for the adjustment.'];
    }

    /**
     * Record an opening balance, RNR days or an adjustment for $member (id, name,
     * serial_number). Returns [true, message] or [false, ['field' => error]].
     */
    public static function credit(object $member, array $data, array $by, ?string $ip = null): array
    {
        $days = round((float) $data['days'], 2);
        [$pool, $kind] = match ($data['action']) {
            'opening' => ['annual', 'opening'],
            'grant' => ['rnr', 'grant'],
            default => [$data['pool'], 'adjustment'],
        };
        if ($kind === 'opening' && $days < 0) {
            return [false, ['days' => 'An opening balance cannot be negative.']];
        }
        if ($kind === 'grant' && $days <= 0) {
            return [false, ['days' => 'Add at least half a day of RNR.']];
        }
        if ($kind === 'adjustment' && abs($days) < 0.01) {
            return [false, ['days' => 'An adjustment needs a number of days other than 0.']];
        }

        $entryId = LeaveCredits::post((int) $member->id, (string) $member->serial_number, $pool, $kind, $days,
            $data['effective_date'], $data['note'] ?? null, null, $by);

        Log::channel('audit')->info('leave_credit.' . $kind, [
            'entry_id' => $entryId, 'member_user_id' => $member->id, 'serial_number' => $member->serial_number, 'pool' => $pool,
            'days' => $days, 'effective_date' => $data['effective_date'], 'by_type' => $by['type'] ?? null, 'by_id' => $by['id'] ?? null, 'ip' => $ip,
        ]);

        $what = match ($kind) {
            'opening' => 'Opening balance of ' . LeaveCredits::fmt($days) . ' days set',
            'grant' => LeaveCredits::fmt($days) . ' RNR days added',
            default => 'Adjustment of ' . ($days > 0 ? '+' : '') . LeaveCredits::fmt($days) . ' days to ' . strtolower(LeaveCredits::label($pool)) . ' recorded',
        };
        return [true, $what . ' for ' . $member->name . '.'];
    }
}
