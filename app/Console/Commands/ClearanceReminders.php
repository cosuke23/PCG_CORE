<?php

namespace App\Console\Commands;

use App\Mail\ClearanceExpiring;
use App\Support\ClearanceExpiry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email members whose NBI / Ombudsman / Sandiganbayan clearance ends in exactly
 * one of config('pcg.clearance.reminder_days') days (default 30, 7, 0). Scheduled
 * daily 07:30 Manila; a second run the same day sends nothing again.
 */
class ClearanceReminders extends Command
{
    protected $signature = 'pcg:clearance-reminders {--dry-run : List the emails without sending}';

    protected $description = 'Email members about clearances that are about to expire';

    public function handle(): int
    {
        $days = array_values(array_unique(array_map('intval', (array) config('pcg.clearance.reminder_days', [30, 7, 0]))));
        if (!$days) {
            $this->info('Clearance reminders are off.');
            return self::SUCCESS;
        }
        $due = ClearanceExpiry::upcoming(null, max($days))->filter(fn ($r) => in_array($r->days_left, $days, true));
        $sent = 0;
        foreach ($due as $r) {
            $who = trim(($r->rank ?? '') . ' ' . $r->name);
            if (!filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
                $this->line("  skip {$who}: no email address");
                continue;
            }
            $line = "  {$who} <{$r->email}>: {$r->label} " . ClearanceExpiry::when($r->days_left) . " ({$r->valid_until})";
            if ($this->option('dry-run')) {
                $this->line($line);
                continue;
            }
            $key = "clearance-reminder:{$r->user_id}:{$r->field}:{$r->valid_until}:{$r->days_left}";
            if (!Cache::add($key, 1, now()->addDays(2))) {
                continue;
            }
            try {
                Mail::to($r->email)->send(new ClearanceExpiring($r->name, $r->label, $r->valid_until, $r->days_left,
                    url('/documents/upload'), (string) config('app.name', 'MEPCOM HRMIS')));
                $sent++;
                $this->line($line);
                Log::channel('audit')->info('clearance.reminder', ['user_id' => $r->user_id, 'serial_number' => $r->serial_number, 'document' => $r->field, 'days_left' => $r->days_left]);
            } catch (\Throwable $e) {
                Cache::forget($key);
                Log::warning('Clearance reminder email failed', ['user_id' => $r->user_id, 'reason' => $e::class]);
            }
        }
        $this->info($this->option('dry-run') ? $due->count() . ' reminder(s) would be sent.' : "$sent reminder(s) sent.");
        return self::SUCCESS;
    }
}
