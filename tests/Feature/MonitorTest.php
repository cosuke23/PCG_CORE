<?php

namespace Tests\Feature;

use App\Mail\SystemAlert;
use App\Support\Monitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Monitoring and alerts (owner request 2026-11-20): /health, pcg:monitor, error emails, pcg:backup-verify. */
class MonitorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/pcg-monitor-' . uniqid();
        File::ensureDirectoryExists($this->tmp . '/uploads');
        config(['pcg.upload_root' => $this->tmp . '/uploads', 'pcg.monitor.alert_to' => 'ops@x.test', 'pcg.monitor.disk_min_free_percent' => 0]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    public function test_health_reports_ok_or_fail_without_details()
    {
        $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok']);
        config(['pcg.upload_root' => $this->tmp . '/missing']);
        $this->get('/health')->assertStatus(503)->assertExactJson(['status' => 'fail']);
    }

    public function test_monitor_emails_once_while_failing_and_once_when_fixed()
    {
        Mail::fake();
        config(['pcg.upload_root' => $this->tmp . '/missing']);
        $this->artisan('pcg:monitor --alert')->assertFailed();
        $this->artisan('pcg:monitor --alert')->assertFailed();
        Mail::assertSent(SystemAlert::class, 1);
        Mail::assertSent(SystemAlert::class, fn ($m) => $m->hasTo('ops@x.test') && str_contains($m->title, 'uploads'));

        config(['pcg.upload_root' => $this->tmp . '/uploads']);
        $this->artisan('pcg:monitor --alert')->assertSuccessful();
        Mail::assertSent(SystemAlert::class, fn ($m) => str_contains($m->title, 'pass again'));
        Mail::assertSent(SystemAlert::class, 2);
    }

    public function test_the_office_pc_copy_and_drill_are_watched_once_they_start()
    {
        config(['pcg.monitor.shared_dir' => $this->tmp]);
        $this->assertSame([], Monitor::offsiteChecks());                       // nothing set up yet: no alert

        file_put_contents($this->tmp . '/offsite-last.txt', "20261120-021500\n");
        $this->assertTrue(Monitor::offsiteChecks()['offsite copy']['ok']);
        touch($this->tmp . '/offsite-last.txt', time() - 3 * 86400);
        $this->assertFalse(Monitor::offsiteChecks()['offsite copy']['ok']);

        file_put_contents($this->tmp . '/restore-drill-last.json', json_encode(['ok' => false, 'backup' => '20261120-021500']));
        $this->assertStringContainsString('FAILED', Monitor::offsiteChecks()['restore drill']['note']);
        file_put_contents($this->tmp . '/restore-drill-last.json', json_encode(['ok' => true, 'backup' => 'x']));
        $this->assertTrue(Monitor::offsiteChecks()['restore drill']['ok']);
        touch($this->tmp . '/restore-drill-last.json', time() - 45 * 86400);
        $this->assertFalse(Monitor::offsiteChecks()['restore drill']['ok']);
    }

    public function test_errors_are_emailed_once_an_hour_per_place()
    {
        Mail::fake();
        config(['pcg.monitor.alerts_in_tests' => true]);
        $e = new \RuntimeException('Something broke');
        Monitor::reportError($e);
        Monitor::reportError($e);
        Mail::assertSent(SystemAlert::class, 1);
        Mail::assertSent(SystemAlert::class, fn ($m) => in_array('Reason: Something broke', $m->lines, true));

        config(['pcg.monitor.error_alerts' => false]);
        Monitor::reportError(new \LogicException('other place'));
        Mail::assertSent(SystemAlert::class, 1);
    }

    public function test_a_failed_scheduled_command_is_not_emailed_as_an_error()
    {
        Mail::fake();
        config(['pcg.monitor.alerts_in_tests' => true]);
        Monitor::reportError(new \Exception("Scheduled command ['/usr/bin/php' 'artisan' pcg:monitor --alert] failed with exit code [1]."));
        Mail::assertNothingSent();

        Monitor::reportError(new \RuntimeException('A real error'));
        Mail::assertSent(SystemAlert::class, 1);
    }

    private function makeBackup(bool $complete = true): string
    {
        $dir = $this->tmp . '/backups/' . date('Ymd-His');
        File::ensureDirectoryExists($dir);
        file_put_contents("$dir/database.sql.gz", gzencode("CREATE TABLE x (id int);\n" . ($complete ? "-- Dump completed on 2026-11-20\n" : '')));
        file_put_contents("$dir/files.tar.gz", gzencode(self::tar('uploads/a.bin', 'abc')));
        $sums = '';
        foreach (['database.sql.gz', 'files.tar.gz'] as $f) {
            $sums .= hash_file('sha256', "$dir/$f") . "  $f\n";
        }
        file_put_contents("$dir/SHA256SUMS", $sums);
        return $dir;
    }

    /** A one-file ustar archive. */
    private static function tar(string $name, string $data): string
    {
        $h = str_pad($name, 100, "\0") . sprintf("%07o\0%07o\0%07o\0%011o\0%011o\0", 0644, 0, 0, strlen($data), time())
            . str_repeat(' ', 8) . '0' . str_repeat("\0", 100) . "ustar\x0000" . str_repeat("\0", 247);
        $sum = array_sum(array_map('ord', str_split($h)));
        $h = substr_replace($h, sprintf("%06o\0 ", $sum), 148, 8);
        return $h . str_pad($data, 512, "\0") . str_repeat("\0", 1024);
    }

    public function test_backup_verify_passes_a_good_backup_and_emails_the_result()
    {
        Mail::fake();
        $this->makeBackup();
        $this->artisan('pcg:backup-verify', ['--dir' => $this->tmp . '/backups', '--alert' => true])->assertSuccessful();
        Mail::assertSent(SystemAlert::class, fn ($m) => str_contains($m->title, 'passed'));
    }

    public function test_backup_verify_fails_a_changed_or_cut_short_backup()
    {
        $dir = $this->makeBackup(false);
        $r = Monitor::verifyBackup($this->tmp . '/backups');
        $this->assertFalse($r['ok']);
        $this->assertContains('FAIL database.sql.gz is cut short (no "Dump completed" line)', $r['lines']);

        file_put_contents("$dir/files.tar.gz", 'tampered');
        $r = Monitor::verifyBackup($this->tmp . '/backups');
        $this->assertContains('FAIL files.tar.gz does not match its checksum', $r['lines']);

        $this->assertFalse(Monitor::verifyBackup($this->tmp . '/none')['ok']);
    }
}
