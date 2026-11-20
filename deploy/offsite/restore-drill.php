<?php
/**
 * Monthly restore drill (owner request 2026-11-20). Takes the newest off-site copy
 * (deploy/offsite-pull.ps1), decrypts its database dump, restores it into a
 * scratch database on this PC's MariaDB/MySQL (name must end in _drill), and
 * compares the exact row count of every table with row-counts.json recorded on
 * Hermes when the backup was made. Also checks the files archive decrypts and
 * matches its checksum. Plain copies are deleted afterwards; the scratch
 * database is dropped unless --keep. Never touches pcg_core or the live server's
 * database. Writes the result to <dest>\restore-drill.log and prints a JSON line.
 *
 *   php restore-drill.php <dest> <keyfile> [--mysql=C:/xampp/mysql/bin/mysql.exe]
 *       [--user=root] [--password=] [--host=127.0.0.1] [--db=mepcom_restore_drill] [--keep]
 */
$args = $argv;
array_shift($args);
$opt = ['mysql' => 'C:/xampp/mysql/bin/mysql.exe', 'user' => 'root', 'password' => '', 'host' => '127.0.0.1', 'db' => 'mepcom_restore_drill', 'keep' => false];
$pos = [];
foreach ($args as $a) {
    if (preg_match('/^--(\w+)(?:=(.*))?$/', $a, $m)) {
        $opt[$m[1]] = $m[2] ?? true;
    } else {
        $pos[] = $a;
    }
}
[$dest, $keyFile] = array_pad($pos, 2, null);
$started = microtime(true);
$lines = [];
$ok = true;
$say = function (string $m) use (&$lines) { $lines[] = $m; echo $m, "\n"; };
$fail = function (string $m) use (&$ok, $say) { $ok = false; $say('FAIL ' . $m); };

$finish = function () use (&$ok, &$lines, $dest, $started, &$backup) {
    $result = ['ok' => $ok, 'backup' => $backup ?? null, 'at' => date('c'), 'seconds' => round(microtime(true) - $started, 1)];
    if ($dest && is_dir($dest)) {
        file_put_contents($dest . '/restore-drill.log', date('Y-m-d H:i:s') . '  ' . ($ok ? 'PASS' : 'FAIL') . ' ' . ($backup ?? '-') . "\n    "
            . implode("\n    ", $lines) . "\n", FILE_APPEND);
    }
    echo json_encode($result), "\n";
    exit($ok ? 0 : 1);
};

if (!$dest || !$keyFile || !is_dir($dest)) {
    $fail('usage: restore-drill.php <dest> <keyfile> [options]');
    $finish();
}
if (!preg_match('/^[a-z0-9_]+_drill$/', (string) $opt['db'])) {
    $fail('the scratch database name must end in _drill');
    $finish();
}
$copies = array_filter(glob(rtrim($dest, '/\\') . '/*', GLOB_ONLYDIR) ?: [], fn ($d) => preg_match('/^\d{8}-\d{6}$/', basename($d)) && is_file("$d/COMPLETE"));
sort($copies);
$dir = end($copies) ?: null;
if (!$dir) {
    $fail('no complete off-site copy found');
    $finish();
}
$backup = basename($dir);
$say("backup $backup");
$crypt = __DIR__ . '/offsite-crypt.php';
$php = PHP_BINARY;
$tmp = sys_get_temp_dir() . '/hrmis-drill-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$cleanup = function () use ($tmp) {
    foreach (glob("$tmp/*") ?: [] as $f) { @unlink($f); }
    @rmdir($tmp);
};

try {
    // 1. decrypt every file and check it against SHA256SUMS
    $sums = [];
    foreach (file("$dir/SHA256SUMS", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $row) {
        if (preg_match('/^([0-9a-f]{64})\s+\*?(.+)$/', trim($row), $m)) { $sums[trim($m[2])] = $m[1]; }
    }
    foreach ($sums as $name => $hash) {
        $enc = "$dir/$name.enc";
        if (!is_file($enc)) { $fail("$name.enc is missing"); continue; }
        exec(escapeshellarg($php) . ' ' . escapeshellarg($crypt) . ' decrypt ' . escapeshellarg($keyFile) . ' ' . escapeshellarg($enc) . ' ' . escapeshellarg("$tmp/$name") . ' 2>&1', $out, $code);
        if ($code !== 0) { $fail("$name does not decrypt: " . implode(' ', $out)); continue; }
        hash_equals($hash, hash_file('sha256', "$tmp/$name")) ? $say("OK   $name decrypts and matches its checksum") : $fail("$name does not match its checksum");
    }
    if (!is_file("$tmp/database.sql.gz")) { throw new RuntimeException('no database dump'); }
    $expected = is_file("$tmp/row-counts.json") ? json_decode((string) file_get_contents("$tmp/row-counts.json"), true) : null;
    if (!is_array($expected)) { $say('NOTE no row-counts.json in this backup (older backup.sh): counts are only listed'); }

    // 2. gunzip the dump
    $sql = "$tmp/database.sql";
    $in = gzopen("$tmp/database.sql.gz", 'rb');
    $outF = fopen($sql, 'wb');
    // MariaDB 10.5.25+/11 dumps start with "/*M!999999\- enable the sandbox mode */", which
    // older mysql clients (XAMPP) reject: leave out that one line, nothing else changes
    $first = gzgets($in, 4096);
    if ($first !== false && !str_starts_with($first, '/*M!999999\\- enable the sandbox mode')) { fwrite($outF, $first); }
    while (!gzeof($in)) { fwrite($outF, gzread($in, 1 << 20)); }
    gzclose($in);
    fclose($outF);
    @unlink("$tmp/database.sql.gz");

    // 3. fresh scratch database, restore with the mysql client
    $pdo = new PDO("mysql:host={$opt['host']};charset=utf8mb4", $opt['user'], (string) $opt['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db = $opt['db'];
    $pdo->exec("DROP DATABASE IF EXISTS `$db`");
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $cmd = [$opt['mysql'], '--host=' . $opt['host'], '--user=' . $opt['user'], '--default-character-set=utf8mb4', $db];
    $env = $opt['password'] !== '' ? ['MYSQL_PWD' => (string) $opt['password']] + getenv() : null;
    $p = proc_open($cmd, [0 => ['file', $sql, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (!is_resource($p)) { throw new RuntimeException('cannot start ' . $opt['mysql']); }
    stream_get_contents($pipes[1]);
    $err = trim(stream_get_contents($pipes[2]));
    $code = proc_close($p);
    @unlink($sql);
    if ($code !== 0) { throw new RuntimeException('restore failed: ' . mb_substr($err, 0, 300)); }
    $say('OK   database restored into ' . $db);

    // 4. compare exact row counts
    $pdo->exec("USE `$db`");
    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
    $actual = [];
    foreach ($tables as $t) { $actual[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
    $say('OK   ' . count($actual) . ' tables, ' . number_format(array_sum($actual)) . ' rows restored');
    if (is_array($expected)) {
        $bad = 0;
        foreach ($expected as $t => $n) {
            if (!array_key_exists($t, $actual)) { $fail("table $t is missing after the restore"); $bad++; }
            elseif ($actual[$t] !== (int) $n) { $fail("table $t has {$actual[$t]} rows, the backup recorded $n"); $bad++; }
        }
        if (!$bad) { $say('OK   every table has the row count recorded on Hermes (' . count($expected) . ' tables)'); }
    }
    if (empty($opt['keep'])) { $pdo->exec("DROP DATABASE `$db`"); $say("OK   scratch database $db dropped"); }
} catch (Throwable $e) {
    $fail($e instanceof PDOException ? 'database: ' . $e->getMessage() . ' (is the local MariaDB/MySQL running?)' : $e->getMessage());
} finally {
    $cleanup();
}
$finish();
