<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data Privacy Act retention report (owner request 2026-11-20): counts the
 * records that are past the retention periods in config('pcg.privacy.retention')
 * so the Data Protection Officer can decide what to remove. Read-only: it never
 * deletes or changes anything, and prints counts only (no names).
 */
class RetentionReport extends Command
{
    protected $signature = 'pcg:retention-report';

    protected $description = 'Count records past their retention period (read-only, no names)';

    public function handle(): int
    {
        $r = config('pcg.privacy.retention');
        $rows = [];
        $count = function (string $table, callable $where) {
            return Schema::hasTable($table) ? $where(DB::table($table))->count() : null;
        };
        $before = fn (int $days) => now()->subDays($days);

        $rows[] = ['Registrations never approved (Pending/Rejected)', $r['pending_registration_days'] . ' days',
            $count('users', fn ($q) => $q->whereRaw('LOWER(COALESCE(status, ?)) IN (?, ?, ?)', ['', 'pending', 'rejected', 'declined'])
                ->where('created_at', '<', $before($r['pending_registration_days']))),
            'php artisan pcg:purge-accounts (dry run first, after a backup)'];
        $rows[] = ['Admin console activity records (admin_audit_logs)', $r['audit_days'] . ' days',
            $count('admin_audit_logs', fn ($q) => $q->where('created_at', '<', $before($r['audit_days']))), 'archive, then remove with the owner\'s approval'];
        $rows[] = ['Contact form messages', $r['contact_message_days'] . ' days',
            $count('contact_messages', fn ($q) => $q->where('created_at', '<', $before($r['contact_message_days']))), 'remove with the owner\'s approval'];
        $rows[] = ['Leave requests (declined or cancelled)', $r['leave_days'] . ' days',
            $count('leave_requests', fn ($q) => $q->whereRaw('LOWER(status) IN (?, ?)', ['declined', 'cancelled'])
                ->where('updated_at', '<', $before($r['leave_days']))), 'keep approved leave with the personnel record'];
        $files = storage_path('logs');
        $old = 0;
        foreach (glob($files . '/*.log') ?: [] as $f) {
            if (filemtime($f) < $before($r['audit_days'])->getTimestamp()) {
                $old++;
            }
        }
        $rows[] = ['Log files in storage/logs', $r['audit_days'] . ' days', $old, 'the daily logs remove themselves (LOG_DAILY_DAYS / LOG_AUDIT_DAYS)'];

        $this->table(['Records', 'Kept for', 'Past the period', 'What to do'],
            array_map(fn ($row) => [$row[0], $row[1], $row[2] === null ? 'n/a' : (string) $row[2], $row[3]], $rows));
        $this->line('Read-only: nothing was changed. Personnel records, 201 files and approved leave are kept while the member');
        $this->line('is in the service and afterwards as government records rules require (docs/PRIVACY_RECORD.md).');
        return self::SUCCESS;
    }
}
