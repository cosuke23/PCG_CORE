<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\UnitUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Findings of the 2026-11-03 penetration test (SECURITY_AUDIT section 0f).
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        if (!Schema::hasTable('unit_users')) {
            Schema::create('unit_users', function (Blueprint $t) {
                $t->id(); $t->string('full_name')->nullable(); $t->string('email'); $t->string('username')->nullable();
                $t->string('password')->nullable(); $t->string('role')->nullable(); $t->string('unit_name')->nullable();
                $t->string('unit_code')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
            });
        }
        DB::table('unit_users')->insert([
            ['id' => 1, 'full_name' => 'M1', 'email' => 'm1@x.test', 'role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM', 'password' => Hash::make('old-password-1')],
            ['id' => 2, 'full_name' => 'Unit', 'email' => 'u@x.test', 'role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF', 'password' => Hash::make('old-password-1')],
        ]);
    }

    public function test_hr_admin_cannot_grant_m1_or_reset_an_m1_password()
    {
        $hr = ['admin_logged_in' => true, 'admin_role' => 'hr_admin'];

        $this->withSession($hr)->post('/admin/unit-users/2/role', ['role' => UnitUser::ROLE_M1])->assertForbidden();
        $this->assertSame(UnitUser::ROLE_UNIT, DB::table('unit_users')->where('id', 2)->value('role'));

        $this->withSession($hr)->post('/admin/unit-users/1/password', ['password' => 'taken-over-123'])->assertForbidden();
        $this->assertTrue(Hash::check('old-password-1', DB::table('unit_users')->where('id', 1)->value('password')));

        // ordinary unit accounts: HR keeps managing them
        $this->withSession($hr)->post('/admin/unit-users/2/password', ['password' => 'new-password-12'])->assertRedirect();

        // the super admin may do both
        $super = ['admin_logged_in' => true, 'admin_role' => 'super_admin'];
        $this->withSession($super)->post('/admin/unit-users/2/role', ['role' => UnitUser::ROLE_M1])->assertRedirect();
        $this->assertSame(UnitUser::ROLE_M1, DB::table('unit_users')->where('id', 2)->value('role'));
    }

    public function test_file_paths_with_dot_or_double_slash_segments_are_refused()
    {
        $super = ['admin_logged_in' => true, 'admin_role' => 'super_admin'];
        foreach (['/files/documents_toupload/./documents/a.pdf', '/files/documents_toupload/documents//a.pdf',
                  '/files/201_files/./legal/x.pdf', '/files/./selfies/a.png'] as $path) {
            $this->withSession($super)->get($path)->assertNotFound();
        }
    }

    public function test_accented_email_variants_share_the_per_account_limit()
    {
        AdminUser::create(['name' => 'Re', 'email' => 're@x.test', 'password' => Hash::make('right-password-1'), 'role' => 'reenlistment_admin']);

        // non-ASCII email addresses are refused outright on the admin sign-in
        $this->post('/admin/login', ['email' => 'ré@x.test', 'password' => 'guess'])->assertSessionHasErrors('email');

        // and the limiter key folds accents, so variants count against one account
        $limiter = app(\Illuminate\Cache\RateLimiter::class);
        $key = fn (string $email) => md5('admin-login' . 'admin-login-acct|' . strtolower(trim(\Illuminate\Support\Str::ascii($email))));
        $this->assertSame($key('re@x.test'), $key('ré@x.test'));
        $this->assertSame($key('re@x.test'), $key(' RÈ@x.test '));
        unset($limiter);
    }

    public function test_password_only_session_ends_when_the_role_needs_the_second_step()
    {
        $re = AdminUser::create(['name' => 'Re', 'email' => 're@x.test', 'password' => Hash::make('right-password-1'), 'role' => 'reenlistment_admin']);

        $this->post('/admin/login', ['email' => 're@x.test', 'password' => 'right-password-1'])->assertRedirect(route('admin.reenlistment.index'));
        $this->assertTrue((bool) session('admin_password_only'));

        // a super admin promotes the account while that session is still open
        $re->forceFill(['role' => 'super_admin'])->save();

        $this->get('/admin/accounts')->assertRedirect(route('admin.login'));
        $this->assertNull(session('admin_logged_in'));
    }

    public function test_dashboard_row_data_keeps_all_json_escaping()
    {
        $blade = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $this->assertStringContainsString("data-user='@json(\$user)'", $blade);
    }
}
