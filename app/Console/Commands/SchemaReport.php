<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * READ-ONLY: prints the layout of every table in the connected database, one
 * line per table (column type, NN = required, = default, AI = auto
 * increment). No rows are read, so the output holds no personal data and can
 * be shared with developers to match their test database to the real one.
 */
class SchemaReport extends Command
{
    protected $signature = 'pcg:schema-report {--table=* : Only these tables}';

    protected $description = 'Print the table layout of the database (read-only, no data)';

    public function handle()
    {
        $only = $this->option('table');
        $tables = collect(Schema::getTables())->pluck('name')->sort()->values();
        if ($only) {
            $tables = $tables->intersect($only)->values();
        }

        $this->line('# ' . config('database.connections.' . config('database.default') . '.database') . ', ' . $tables->count() . ' tables, ' . now()->toDateString());
        foreach ($tables as $table) {
            $cols = collect(Schema::getColumns($table))->map(function ($c) {
                $s = $c['name'] . ' ' . $c['type'];
                if (!$c['nullable']) {
                    $s .= ' NN';
                }
                if ($c['default'] !== null && strtoupper((string) $c['default']) !== 'NULL') {
                    $s .= ' =' . $c['default'];
                }
                if ($c['auto_increment']) {
                    $s .= ' AI';
                }

                return $s;
            });
            $this->line($table . ': ' . $cols->implode(' | '));
        }

        return self::SUCCESS;
    }
}
