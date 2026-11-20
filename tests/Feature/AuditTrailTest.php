<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Log::channel('audit') entries also land in admin_audit_logs, labelled by who did it (owner request 2026-11-20). */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function logAs(array $session, string $action, array $context = []): void
    {
        $request = $this->app['request'];
        $request->setLaravelSession($this->app['session']->driver());
        foreach ($session as $k => $v) {
            $request->session()->put($k, $v);
        }
        Log::channel('audit')->info($action, $context);
        $request->session()->flush();
    }

    public function test_unit_checker_and_admin_entries_are_copied_with_the_actor()
    {
        $this->logAs(['unit_user_id' => 4, 'unit_email' => 'checker@x.test'], 'leave.decided', ['leave_id' => 12, 'serial_number' => 'SN-1', 'ip' => '10.0.0.9']);
        $this->logAs(['admin_logged_in' => true, 'admin_user_id' => 7, 'admin_email' => 'hr@x.test'], 'leave.credit', ['entry_id' => 3]);

        $unit = DB::table('admin_audit_logs')->where('action', 'leave.decided')->first();
        $this->assertSame('unit: checker@x.test', $unit->admin_email);
        $this->assertNull($unit->admin_user_id);
        $this->assertSame('leave #12 (SN-1)', $unit->subject);
        $this->assertSame('10.0.0.9', $unit->ip);
        $this->assertStringNotContainsString('10.0.0.9', (string) $unit->details);

        $admin = DB::table('admin_audit_logs')->where('action', 'leave.credit')->first();
        $this->assertSame('hr@x.test', $admin->admin_email);
        $this->assertSame(7, (int) $admin->admin_user_id);
    }

    public function test_the_audit_page_filters_by_who()
    {
        $this->logAs(['unit_user_id' => 4, 'unit_email' => 'checker@x.test'], 'unit.login');
        DB::table('admin_audit_logs')->insert([
            ['admin_email' => 'member: ana@x.test', 'action' => 'leave.filed', 'created_at' => now()],
            ['admin_email' => 'su@x.test', 'action' => 'login', 'created_at' => now()],
        ]);
        $sup = AdminUser::create(['name' => 'Super', 'email' => 'su@x.test', 'password' => 'x', 'role' => 'super_admin', 'is_active' => true]);
        $s = ['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $sup->id, 'admin_email' => 'su@x.test', 'admin_name' => 'Super'];

        $this->withSession($s)->get('/admin/accounts/audit-log?actor=unit')->assertOk()
            ->assertSee('unit: checker@x.test')->assertDontSee('member: ana@x.test');
        $this->withSession($s)->get('/admin/accounts/audit-log?actor=member')->assertOk()
            ->assertSee('member: ana@x.test')->assertDontSee('unit: checker@x.test');
        $this->withSession($s)->get('/admin/accounts/audit-log?actor=admin')->assertOk()
            ->assertDontSee('member: ana@x.test')->assertDontSee('unit: checker@x.test');
    }
}
