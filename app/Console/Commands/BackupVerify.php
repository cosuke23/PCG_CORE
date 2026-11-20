<?php

namespace App\Console\Commands;

use App\Support\Monitor;
use Illuminate\Console\Command;

/**
 * Weekly backup verification (owner request 2026-11-20): checks the newest
 * backup made by deploy/backup.sh (age, SHA256SUMS, the database dump
 * decompresses to its end, the files archive lists). Read-only. With --alert
 * (the weekly schedule) the result is emailed to the administrators, pass or fail.
 */
class BackupVerify extends Command
{
    protected $signature = 'pcg:backup-verify {--dir= : Backups folder (default PCG_BACKUP_DIR or ../../backups)} {--alert : Email the result}';

    protected $description = 'Verify the newest backup (read-only)';

    public function handle(): int
    {
        $r = Monitor::verifyBackup($this->option('dir') ?: null);
        foreach ($r['lines'] as $line) {
            $this->line('  ' . $line);
        }
        $this->line($r['ok'] ? 'Backup verified.' : 'Backup check FAILED.');
        if ($this->option('alert')) {
            Monitor::alert('backup:' . now()->format('YmdH'), $r['ok'] ? 'weekly backup check passed' : 'weekly backup check FAILED',
                array_merge($r['dir'] ? ['Backup: ' . basename($r['dir'])] : [], $r['lines'],
                    $r['ok'] ? [] : ['Run: php artisan pcg:backup-verify on the server, and deploy/backup.sh to make a new backup.']), 0);
        }
        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
