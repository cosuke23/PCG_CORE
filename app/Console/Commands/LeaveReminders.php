<?php

namespace App\Console\Commands;

use App\Mail\LeaveWaiting;
use App\Models\UnitUser;
use App\Support\LeaveCalendar;
use App\Support\LeaveTypes;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Remind unit checkers of leave requests that have waited longer than
 * config('pcg.leave.reminder_days'): one email per active checker, listing the
 * requests of their unit (M1 checkers: every unit). Scheduled daily at 08:00
 * (app/Console/Kernel.php). --dry-run lists who would be emailed.
 */
class LeaveReminders extends Command
{
    protected $signature = 'pcg:leave-reminders {--dry-run : List the emails without sending}';

    protected $description = 'Email unit checkers about leave requests waiting too long';

    public function handle(): int
    {
        $days = (int) config('pcg.leave.reminder_days', 2);
        if ($days <= 0 || !Schema::hasTable('leave_requests')) {
            $this->info('Leave reminders are off.');
            return self::SUCCESS;
        }
        $cutoff = now()->subDays($days);
        $waiting = DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->whereRaw('LOWER(l.status) = ?', ['pending'])->where('l.created_at', '<=', $cutoff)
            ->orderBy('l.created_at')->get(['l.leave_type', 'l.start_date', 'l.end_date', 'l.created_at', 'u.name', 'u.rank', 'u.mother_unit']);
        if ($waiting->isEmpty()) {
            $this->info('No leave has waited more than ' . $days . ' days.');
            return self::SUCCESS;
        }

        $checkers = DB::table('unit_users')->where('is_active', true)->whereNotNull('email')
            ->get(['id', 'full_name', 'email', 'role', 'unit_name']);
        $sent = 0;
        foreach ($checkers as $c) {
            $m1 = $c->role === UnitUser::ROLE_M1;
            $mine = $m1 ? $waiting : $waiting->filter(fn ($r) => strcasecmp(trim((string) $r->mother_unit), trim((string) $c->unit_name)) === 0);
            if ($mine->isEmpty() || !filter_var($c->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $list = $mine->map(fn ($r) => [
                'name' => trim(($r->rank ?? '') . ' ' . $r->name) . ($m1 && $r->mother_unit ? ' (' . $r->mother_unit . ')' : ''),
                'type' => LeaveTypes::label($r->leave_type),
                'dates' => LeaveCalendar::dateLabel((string) $r->start_date, (string) $r->end_date),
                'waiting' => max(1, (int) Carbon::parse($r->created_at)->diffInDays(now())),
            ])->values()->all();
            if ($this->option('dry-run')) {
                $this->line(sprintf('%s (%s): %d waiting', $c->email, $m1 ? 'all units' : $c->unit_name, count($list)));
                continue;
            }
            try {
                Mail::to($c->email)->send(new LeaveWaiting((string) ($c->full_name ?: 'unit checker'), $m1 ? 'all units' : (string) $c->unit_name,
                    $list, route('unit.leave'), 'PCG HRMIS'));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('leave.reminder_failed', ['unit_user_id' => $c->id, 'error' => class_basename($e)]);
            }
        }
        if (!$this->option('dry-run')) {
            Log::channel('audit')->info('leave.reminders_sent', ['checkers' => $sent, 'waiting' => $waiting->count()]);
            $this->info("Reminders sent to {$sent} " . ($sent === 1 ? 'checker' : 'checkers') . '.');
        }
        return self::SUCCESS;
    }
}
