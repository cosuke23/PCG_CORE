<?php

namespace App\Console\Commands;

use App\Mail\ReenlistmentDue;
use App\Support\MemberNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Remind approved non-officers whose ETE is exactly config('pcg.reenlistment.reminder_days')
 * days away (default 180 and 90): an email and a portal notification. Daily 07:45 Manila.
 */
class ReenlistmentReminders extends Command
{
    protected $signature = 'pcg:reenlistment-reminders {--dry-run : List the reminders without sending}';

    protected $description = 'Remind non-officers 6 and 3 months before their ETE';

    public function handle(): int
    {
        $days = array_values(array_unique(array_map('intval', (array) config('pcg.reenlistment.reminder_days', [180, 90]))));
        $today = Carbon::now('Asia/Manila')->startOfDay();
        $dates = array_map(fn ($d) => $today->copy()->addDays($d)->toDateString(), $days);
        $rows = DB::table('users')->where('registration_type', 'non_officer')->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
            ->whereIn('ete', $dates)->get(['id', 'name', 'rank', 'email', 'serial_number', 'ete']);
        $sent = 0;
        foreach ($rows as $u) {
            $left = (int) $today->diffInDays(Carbon::parse($u->ete, 'Asia/Manila')->startOfDay());
            $months = (int) round($left / 30);
            $line = trim(($u->rank ?? '') . ' ' . $u->name) . " <{$u->email}>: ETE {$u->ete} ({$months} months)";
            if ($this->option('dry-run')) {
                $this->line('  ' . $line);
                continue;
            }
            if (!Cache::add("reenlist-reminder:{$u->id}:{$u->ete}:{$left}", 1, now()->addDays(2))) {
                continue;
            }
            MemberNotifications::send((int) $u->id, 'reenlistment.due', "Your term of enlistment ends in about {$months} months",
                'ETE ' . Carbon::parse($u->ete)->format('M j, Y') . '. Start your re-enlistment requirements with your unit or HR.', '/dashboard');
            if (filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($u->email)->send(new ReenlistmentDue((string) $u->name, (string) $u->ete, $months,
                        array_values(\App\Support\ReenlistmentCases::requirements()), url('/dashboard'), (string) config('app.name', 'MEPCOM HRMIS')));
                } catch (\Throwable $e) {
                    Log::warning('Re-enlistment reminder email failed', ['user_id' => $u->id, 'reason' => class_basename($e)]);
                }
            }
            Log::channel('audit')->info('reenlistment.reminder', ['user_id' => $u->id, 'serial_number' => $u->serial_number, 'days_left' => $left]);
            $sent++;
            $this->line('  ' . $line);
        }
        $this->info($this->option('dry-run') ? $rows->count() . ' reminder(s) would be sent.' : "$sent reminder(s) sent.");
        return self::SUCCESS;
    }
}
