<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Exact row count of every table in the connected database, as JSON
 * ({"table": count, ...}). Read-only, no row contents. deploy/backup.sh stores
 * it next to each dump so the restore drill can check a restore is complete.
 */
class RowCounts extends Command
{
    protected $signature = 'pcg:row-counts';

    protected $description = 'Print the exact row count of every table as JSON (read-only)';

    public function handle(): int
    {
        // tables of the connected database only (MySQL lists every schema the account can see)
        $tables = DB::connection()->getDriverName() === 'mysql' || DB::connection()->getDriverName() === 'mariadb'
            ? array_map(fn ($r) => array_values((array) $r)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            : array_map(fn ($t) => is_array($t) ? $t['name'] : $t->name, DB::connection()->getSchemaBuilder()->getTables());
        sort($tables);
        $out = [];
        foreach ($tables as $t) {
            $out[$t] = (int) DB::table($t)->count();
        }
        $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
