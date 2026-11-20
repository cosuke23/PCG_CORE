<?php

namespace App\Console\Commands;

use App\Support\Monitor;
use Illuminate\Console\Command;

/**
 * Live health check (database, storage, uploads, disk space). With --alert
 * (the hourly schedule) it emails the administrators when a check fails, at
 * most once every 6 hours for the same problem, and once more when it is fixed.
 */
class MonitorCommand extends Command
{
    protected $signature = 'pcg:monitor {--alert : Email the administrators when a check fails}';

    protected $description = 'Check the live system health; with --alert, email when something fails';

    public function handle(): int
    {
        $checks = Monitor::checks() + Monitor::offsiteChecks();
        foreach ($checks as $name => $c) {
            $this->line(($c['ok'] ? '  <info>[ OK ]</info> ' : '  <error>[FAIL]</error> ') . "$name: {$c['note']}");
        }
        $healthy = Monitor::healthy($checks);
        if ($this->option('alert')) {
            $failed = array_filter($checks, fn ($c) => !$c['ok']);
            $was = cache()->get('pcg-monitor:failing');
            if ($failed) {
                $key = 'monitor:' . implode(',', array_keys($failed));
                Monitor::alert($key, count($failed) . ' health ' . (count($failed) === 1 ? 'check fails' : 'checks fail') . ': ' . implode(', ', array_keys($failed)),
                    array_map(fn ($n, $c) => "$n: {$c['note']}", array_keys($failed), $failed), 360);
                cache()->forever('pcg-monitor:failing', implode(',', array_keys($failed)));
            } elseif ($was) {
                Monitor::alert('monitor:ok:' . now()->timestamp, 'health checks pass again', ["Earlier failing: $was", 'All checks pass now.'], 0);
                cache()->forget('pcg-monitor:failing');
            }
        }
        $this->line($healthy ? 'All checks passed.' : 'Some checks failed.');
        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
