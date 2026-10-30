<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only environment check for local / Tailscale deployments.
 */
class HealthCheck extends Command
{
    protected $signature = 'pcg:check';

    protected $description = 'Check configuration, database connectivity and upload folders (read-only)';

    private int $failures = 0;

    public function handle()
    {
        $this->line('PHP ' . PHP_VERSION . ' | Laravel ' . app()->version() . ' | env=' . app()->environment());

        $this->check('PHP 8.2 or newer', version_compare(PHP_VERSION, '8.2.0', '>='));
        $this->check('PHP 8.3+ recommended (8.2 security support ends 2026-12-31; verified on 8.5.12)', version_compare(PHP_VERSION, '8.3.0', '>='), 'warn');
        $this->check('APP_KEY is set', (bool) config('app.key'));
        $this->check('APP_DEBUG is off', !config('app.debug'), 'warn');
        if (config('pcg.admin.two_factor', 'email') === 'email') {
            $mailer = config('mail.default');
            $this->check("Admin sign-in codes are emailed (MAIL_MAILER={$mailer}" . (in_array($mailer, ['log', 'array'], true) ? ': codes only go to storage/logs/laravel.log' : '') . ')',
                !in_array($mailer, ['log', 'array'], true), 'warn');
            $this->check('MAIL_FROM_ADDRESS is set', (bool) config('mail.from.address') && config('mail.from.address') !== 'hello@example.com', 'warn');
        }
        $this->check('ADMIN_EMAIL and ADMIN_PASSWORD_HASH are set (setup admin, only until an admin account exists)',
            config('pcg.admin.email') && str_starts_with((string) config('pcg.admin.password_hash'), '$2y$'), 'warn');

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'gd'] as $ext) {
            $this->check("PHP extension $ext", extension_loaded($ext), $ext === 'gd' ? 'warn' : 'fail');
        }

        foreach ([storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), config('pcg.upload_root')] as $dir) {
            $this->check("Writable: $dir", is_dir($dir) && is_writable($dir));
        }

        $uploads = realpath((string) config('pcg.upload_root')) ?: (string) config('pcg.upload_root');
        $public = realpath(public_path()) ?: public_path();
        $this->check('Uploads stored outside the web root (PCG_UPLOAD_ROOT)', !str_starts_with($uploads, $public), 'warn');

        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $version = $driver === 'mysql' ? DB::selectOne('select version() as v')->v : $driver;
            $this->check('Database connection (' . DB::connection()->getDatabaseName() . ", $version)", true);

            foreach (['users', 'unit_users', 'personnel_data', 'reinlist_basic_info', 'training_courses', 'osaert_trainees'] as $table) {
                $this->check("Table $table exists", Schema::hasTable($table));
            }

            $hasAdmins = Schema::hasTable('admin_users') && DB::table('admin_users')->where('is_active', 1)->exists();
            $this->check(Schema::hasTable('admin_users')
                ? 'Admin accounts exist (2FA); .env setup admin disabled'
                : 'admin_users table exists (back up, then: php artisan migrate)', $hasAdmins, 'warn');

            if (Schema::hasTable('unit_users') && Schema::hasColumn('unit_users', 'password')) {
                $missing = DB::table('unit_users')->where('is_active', 1)
                    ->where(fn ($q) => $q->whereNull('password')->orWhere('password', ''))
                    ->count();
                $this->check("Active unit users without a password: $missing", $missing === 0, 'warn');
            }
        } catch (\Throwable $e) {
            $this->check('Database connection: ' . $e->getMessage(), false);
        }

        $this->newLine();
        $this->line($this->failures ? "{$this->failures} check(s) failed." : 'All required checks passed.');

        return $this->failures ? 1 : 0;
    }

    private function check(string $label, bool $ok, string $level = 'fail'): void
    {
        if ($ok) {
            $this->line("  <info>[ OK ]</info> $label");
            return;
        }

        if ($level === 'warn') {
            $this->line("  <comment>[WARN]</comment> $label");
            return;
        }

        $this->failures++;
        $this->line("  <error>[FAIL]</error> $label");
    }
}
