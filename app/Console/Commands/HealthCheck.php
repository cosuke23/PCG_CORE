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

        $this->check('APP_KEY is set', (bool) config('app.key'));
        $this->check('APP_DEBUG is off', !config('app.debug'), 'warn');
        $this->check('ADMIN_EMAIL and ADMIN_PASSWORD_HASH are set',
            config('pcg.admin.email') && str_starts_with((string) config('pcg.admin.password_hash'), '$2y$'));

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'gd'] as $ext) {
            $this->check("PHP extension $ext", extension_loaded($ext), $ext === 'gd' ? 'warn' : 'fail');
        }

        foreach ([storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), config('pcg.upload_root')] as $dir) {
            $this->check("Writable: $dir", is_dir($dir) && is_writable($dir));
        }

        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $version = $driver === 'mysql' ? DB::selectOne('select version() as v')->v : $driver;
            $this->check('Database connection (' . DB::connection()->getDatabaseName() . ", $version)", true);

            foreach (['users', 'unit_users', 'personnel_data', 'reinlist_basic_info', 'training_courses', 'osaert_trainees'] as $table) {
                $this->check("Table $table exists", Schema::hasTable($table));
            }

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
