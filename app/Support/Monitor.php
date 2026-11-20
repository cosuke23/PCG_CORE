<?php

namespace App\Support;

use App\Mail\SystemAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Monitoring and alerts (owner request 2026-11-20): live health checks (the
 * public /health probe and `pcg:monitor`), error alert emails (throttled), and
 * the weekly backup verification (`pcg:backup-verify`). Alerts go to
 * config('pcg.monitor.alert_to'), or to the active super_admin accounts when
 * that is empty. Nothing here ever throws into the caller.
 */
class Monitor
{
    /**
     * Quick checks safe for every request of /health (no paths or values leave the server).
     *
     * @return array<string, array{ok: bool, note: string}>
     */
    public static function checks(): array
    {
        $out = [];
        try {
            DB::select('select 1');
            $out['database'] = ['ok' => true, 'note' => 'connected'];
        } catch (Throwable $e) {
            $out['database'] = ['ok' => false, 'note' => 'cannot connect'];
        }
        $out['storage'] = is_writable(storage_path('framework/cache'))
            ? ['ok' => true, 'note' => 'writable'] : ['ok' => false, 'note' => 'storage/framework/cache is not writable'];
        $root = (string) config('pcg.upload_root');
        $out['uploads'] = $root !== '' && is_dir($root) && is_writable($root)
            ? ['ok' => true, 'note' => 'writable'] : ['ok' => false, 'note' => 'the upload folder is missing or not writable'];
        $total = @disk_total_space(storage_path());
        $free = @disk_free_space(storage_path());
        if ($total && $free !== false) {
            $pct = round($free / $total * 100, 1);
            $min = (float) config('pcg.monitor.disk_min_free_percent', 10);
            $out['disk'] = ['ok' => $pct >= $min, 'note' => "{$pct}% free (alert below {$min}%)"];
        }
        return $out;
    }

    public static function healthy(array $checks): bool
    {
        foreach ($checks as $c) {
            if (!$c['ok']) {
                return false;
            }
        }
        return true;
    }

    /** @return string[] */
    public static function recipients(): array
    {
        $to = array_filter(array_map('trim', explode(',', (string) config('pcg.monitor.alert_to'))));
        if ($to) {
            return array_values($to);
        }
        try {
            if (Schema::hasTable('admin_users')) {
                return DB::table('admin_users')->where('role', 'super_admin')->where('is_active', 1)
                    ->whereNotNull('email')->pluck('email')->all();
            }
        } catch (Throwable $e) {
        }
        return [];
    }

    /**
     * Email an alert, at most once per $throttleMinutes for the same $key.
     * Returns true when it was sent.
     */
    public static function alert(string $key, string $subject, array $lines, int $throttleMinutes = 60): bool
    {
        try {
            $to = self::recipients();
            if (!$to) {
                return false;
            }
            if ($throttleMinutes > 0 && !Cache::add('pcg-alert:' . sha1($key), 1, now()->addMinutes($throttleMinutes))) {
                return false;
            }
            Mail::to($to)->send(new SystemAlert($subject, $lines, (string) config('app.url'), self::system()));
            return true;
        } catch (Throwable $e) {
            Log::warning('Alert email failed', ['reason' => $e::class]);
            return false;
        }
    }

    /** Called from the exception handler: one email per error location per hour, 10 an hour at most. */
    public static function reportError(Throwable $e): void
    {
        if (!config('pcg.monitor.error_alerts', true) || app()->runningUnitTests() && !config('pcg.monitor.alerts_in_tests')) {
            return;
        }
        try {
            $hour = 'pcg-alert-count:' . now()->format('YmdH');
            Cache::add($hour, 0, now()->addHours(2));
            if (Cache::increment($hour) > 10) {
                return;
            }
            $where = basename($e->getFile()) . ':' . $e->getLine();
            $reason = $e instanceof \Illuminate\Database\QueryException ? DbError::reason($e) : mb_substr($e->getMessage(), 0, 300);
            $url = app()->runningInConsole() ? 'console: ' . implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 2)) : request()->method() . ' /' . ltrim(request()->path(), '/');
            self::alert('error:' . $e::class . $where, 'Error: ' . class_basename($e) . ' at ' . $where, [
                'What: ' . $e::class,
                'Reason: ' . ($reason !== '' ? $reason : '(none)'),
                'Where: ' . $where,
                'Page: ' . $url,
                'Time: ' . now('Asia/Manila')->format('Y-m-d H:i') . ' (Manila)',
                'The full trace is in storage/logs/laravel.log. You get at most one email an hour for the same error.',
            ]);
        } catch (Throwable $ignored) {
        }
    }

    public static function backupDir(): ?string
    {
        $dir = (string) config('pcg.monitor.backup_dir');
        if ($dir === '') {
            // on Hermes: /srv/mepcom-hrmis/current -> releases/<id>; backups sit at /srv/mepcom-hrmis/backups
            $guess = dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'backups';
            $dir = is_dir($guess) ? $guess : '';
        }
        return $dir !== '' ? $dir : null;
    }

    /**
     * Check the newest backup: its age, SHA256SUMS, that the database dump decompresses
     * to the end ("Dump completed") and that the files archive lists.
     *
     * @return array{ok: bool, dir: ?string, lines: string[]}
     */
    public static function verifyBackup(?string $base = null): array
    {
        $base ??= self::backupDir();
        if (!$base || !is_dir($base)) {
            return ['ok' => false, 'dir' => null, 'lines' => ['No backup folder found (set PCG_BACKUP_DIR).']];
        }
        $dirs = array_filter(glob(rtrim($base, '/\\') . '/*', GLOB_ONLYDIR) ?: [], fn ($d) => preg_match('/^\d{8}-\d{6}$/', basename($d)));
        sort($dirs);
        $dir = end($dirs) ?: null;
        if (!$dir) {
            return ['ok' => false, 'dir' => null, 'lines' => ['No backup has been made yet.']];
        }
        $lines = [];
        $ok = true;
        $fail = function (string $m) use (&$ok, &$lines) { $ok = false; $lines[] = 'FAIL ' . $m; };
        $pass = function (string $m) use (&$lines) { $lines[] = 'OK   ' . $m; };

        // age from the files themselves: the folder name is in the server's local time, the app runs in UTC
        $stamp = @filemtime(is_file($dir . '/SHA256SUMS') ? $dir . '/SHA256SUMS' : $dir);
        $age = $stamp ? max(0, time() - $stamp) / 3600 : INF;
        $max = (int) config('pcg.monitor.backup_max_age_hours', 30);
        $age <= $max ? $pass(sprintf('newest backup %s is %.0f hours old', basename($dir), $age))
            : $fail(sprintf('newest backup %s is %.0f hours old (more than %d)', basename($dir), $age, $max));

        $sums = $dir . '/SHA256SUMS';
        if (!is_file($sums)) {
            $fail('SHA256SUMS is missing');
        } else {
            foreach (file($sums, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $row) {
                if (!preg_match('/^([0-9a-f]{64})\s+\*?(.+)$/', trim($row), $m)) {
                    continue;
                }
                $file = $dir . '/' . basename($m[2]);
                if (!is_file($file)) {
                    $fail($m[2] . ' is missing');
                } elseif (!hash_equals($m[1], hash_file('sha256', $file))) {
                    $fail($m[2] . ' does not match its checksum');
                } else {
                    $pass($m[2] . ' matches its checksum (' . self::size(filesize($file)) . ')');
                }
            }
        }

        $db = $dir . '/database.sql.gz';
        if (is_file($db)) {
            $tail = self::gzTail($db);
            $tail === null ? $fail('database.sql.gz does not decompress')
                : (str_contains($tail, 'Dump completed') ? $pass('database.sql.gz decompresses to the end of the dump')
                    : $fail('database.sql.gz is cut short (no "Dump completed" line)'));
        } else {
            $fail('database.sql.gz is missing');
        }

        $tar = $dir . '/files.tar.gz';
        if (is_file($tar)) {
            try {
                $p = new Process(['tar', '-tzf', 'files.tar.gz'], $dir);   // relative: GNU tar reads "C:..." as a remote host
                $p->setTimeout(900);
                $count = 0;
                $p->run(function ($type, $buf) use (&$count) {
                    if ($type === Process::OUT) {
                        $count += substr_count($buf, "\n");
                    }
                });
                $p->isSuccessful() ? $pass("files.tar.gz lists ($count entries)") : $fail('files.tar.gz cannot be read');
            } catch (Throwable $e) {
                $lines[] = 'SKIP files.tar.gz (tar is not available here)';
            }
        } else {
            $fail('files.tar.gz is missing');
        }

        return ['ok' => $ok, 'dir' => $dir, 'lines' => $lines];
    }

    /** The last bytes of a gzip file's content, or null when it does not decompress cleanly. */
    private static function gzTail(string $file): ?string
    {
        $h = @fopen($file, 'rb');
        if (!$h) {
            return null;
        }
        $ctx = inflate_init(ZLIB_ENCODING_GZIP);
        $tail = '';
        $done = false;
        while (!feof($h)) {
            $chunk = fread($h, 1 << 20);
            if ($chunk === false) {
                break;
            }
            $out = @inflate_add($ctx, $chunk, feof($h) ? ZLIB_FINISH : ZLIB_SYNC_FLUSH);
            if ($out === false) {
                fclose($h);
                return null;
            }
            $tail = substr($tail . $out, -400);
            if (inflate_get_status($ctx) === ZLIB_STREAM_END) {
                $done = true;
            }
        }
        fclose($h);
        return $done ? $tail : null;
    }

    private static function size(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $u) {
            if ($bytes < 1024 || $u === 'GB') {
                return round($bytes, 1) . ' ' . $u;
            }
            $bytes /= 1024;
        }
        return $bytes . ' B';
    }

    public static function system(): string
    {
        return (string) config('app.name', 'MEPCOM HRMIS');
    }
}
