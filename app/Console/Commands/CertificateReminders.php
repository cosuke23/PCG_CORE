<?php

namespace App\Console\Commands;

use App\Mail\CertificateExpiring;
use App\Support\CertificateExpiry;
use App\Support\ClearanceExpiry;
use App\Support\MemberNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email and notify members whose training certificate or 201 document ends in
 * exactly one of config('pcg.clearance.reminder_days') days (default 30, 7, 0).
 * Daily 07:35 Manila; a second run the same day sends nothing again.
 */
class CertificateReminders extends Command
{
    protected $signature = 'pcg:certificate-reminders {--dry-run : List the reminders without sending}';

    protected $description = 'Remind members of training certificates and documents that are about to expire';

    public function handle(): int
    {
        $days = array_values(array_unique(array_map('intval', (array) config('pcg.clearance.reminder_days', [30, 7, 0]))));
        if (!$days) {
            $this->info('Certificate reminders are off.');
            return self::SUCCESS;
        }
        $due = CertificateExpiry::upcoming(null, null, max($days))->filter(fn ($r) => $r->user_id && in_array($r->days_left, $days, true));
        $sent = 0;
        foreach ($due as $r) {
            $line = trim(($r->rank ?? '') . ' ' . $r->name) . ": {$r->title} " . ClearanceExpiry::when($r->days_left) . " ({$r->valid_until})";
            if ($this->option('dry-run')) {
                $this->line('  ' . $line);
                continue;
            }
            if (!Cache::add('cert-reminder:' . sha1("{$r->user_id}|{$r->source}|{$r->title}|{$r->valid_until}|{$r->days_left}"), 1, now()->addDays(2))) {
                continue;
            }
            MemberNotifications::send((int) $r->user_id, 'certificate.expiring', $r->title . ' ' . ClearanceExpiry::when($r->days_left),
                'Valid until ' . Carbon::parse($r->valid_until)->format('M j, Y') . '. Renew it and give the new copy to your unit or HR.', '/dashboard');
            if (filter_var($r->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($r->email)->send(new CertificateExpiring((string) $r->name, $r->title, $r->valid_until, $r->days_left,
                        url('/dashboard'), (string) config('app.name', 'MEPCOM HRMIS')));
                } catch (\Throwable $e) {
                    Log::warning('Certificate reminder email failed', ['user_id' => $r->user_id, 'reason' => class_basename($e)]);
                }
            }
            Log::channel('audit')->info('certificate.reminder', ['user_id' => $r->user_id, 'serial_number' => $r->serial_number, 'source' => $r->source, 'days_left' => $r->days_left]);
            $sent++;
            $this->line('  ' . $line);
        }
        $this->info($this->option('dry-run') ? $due->count() . ' reminder(s) would be sent.' : "$sent reminder(s) sent.");
        return self::SUCCESS;
    }
}
