<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Support\Totp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Database admin accounts: password + mandatory TOTP, roles, audit log.
 * RefreshDatabase runs only the additive migrations in database/migrations.
 */
class AdminAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        config([
            'pcg.admin.two_factor' => 'totp', // authenticator-app flow; email codes: EmailTwoFactorTest
            'pcg.admin.email' => 'setup@example.test',
            'pcg.admin.password_hash' => Hash::make('setup-password-123'),
        ]);

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
                $t->string('status')->nullable(); $t->timestamps();
            });
        }
    }

    private function admin(string $role = AdminUser::ROLE_SUPER, bool $twoFactor = true, string $email = 'boss@example.test'): array
    {
        $admin = AdminUser::create(['name' => 'Admin ' . $role, 'email' => $email, 'password' => Hash::make('correct-password-1'), 'role' => $role]);
        $secret = Totp::generateSecret();

        if ($twoFactor) {
            $admin->two_factor_secret = $secret;
            $admin->two_factor_confirmed_at = now();
            $admin->save();
        }

        return [$admin, $secret];
    }

    private function signIn(string $email = 'boss@example.test', string $password = 'correct-password-1')
    {
        return $this->post('/admin/login', ['email' => $email, 'password' => $password]);
    }

    public function test_setup_admin_from_env_works_only_until_an_account_exists()
    {
        $this->signIn('setup@example.test', 'setup-password-123')->assertRedirect(route('admin.dashboard'));
        $this->assertSame('super_admin', session('admin_role'));
        $this->assertTrue(session('admin_bootstrap'));

        $this->flushSession();
        $this->admin();

        $this->signIn('setup@example.test', 'setup-password-123')->assertSessionHasErrors('email');
        $this->assertNull(session('admin_logged_in'));
    }

    public function test_open_setup_admin_session_ends_once_an_account_exists()
    {
        $this->signIn('setup@example.test', 'setup-password-123')->assertRedirect(route('admin.dashboard'));
        $this->admin(); // a real account is created while the setup session is still open

        $this->get('/admin/accounts')->assertRedirect(route('admin.login'));
        $this->assertNull(session('admin_logged_in'));
    }

    public function test_admin_session_ends_twelve_hours_after_sign_in()
    {
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_since' => time() - 13 * 3600])
            ->get('/admin/accounts')
            ->assertRedirect(route('admin.login'));
        $this->assertNull(session('admin_logged_in'));
    }

    public function test_reenlistment_admin_signs_in_with_password_only()
    {
        $this->admin(AdminUser::ROLE_REENLISTMENT, false, 're@example.test');

        $this->signIn('re@example.test')->assertRedirect(route('admin.reenlistment.index'));
        $this->assertTrue((bool) session('admin_logged_in'));
        $this->assertSame('reenlistment_admin', session('admin_role'));
    }

    public function test_other_roles_still_need_the_second_step()
    {
        $this->admin(AdminUser::ROLE_HR, false, 'hr@example.test');
        $this->signIn('hr@example.test')->assertRedirect(route('admin.two-factor.setup'));
        $this->assertNull(session('admin_logged_in'));

        // the super admin can never be password-only, whatever the setting
        config(['pcg.admin.password_only_roles' => ['super_admin']]);
        $this->assertFalse(\App\Http\Controllers\AdminLoginController::passwordOnly('super_admin'));
    }

    public function test_new_account_must_enrol_two_factor_before_access()
    {
        $this->admin(AdminUser::ROLE_HR, false);

        $this->signIn()->assertRedirect(route('admin.two-factor.setup'));
        $this->assertNull(session('admin_logged_in'));
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));

        $this->get(route('admin.two-factor.setup'))->assertOk()->assertSee('data-pcg-qr', false);
        $secret = session('admin_2fa_secret');

        $this->post(route('admin.two-factor.setup.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('admin.two-factor.setup.confirm'), ['code' => Totp::code($secret, Totp::currentStep())])
            ->assertRedirect(route('admin.two-factor.recovery-codes'));

        $this->assertTrue(session('admin_logged_in'));
        $this->assertSame('hr_admin', session('admin_role'));
        $this->assertTrue(AdminUser::first()->hasTwoFactor());
        $this->assertSame(8, AdminUser::first()->recoveryCodesLeft());
    }

    public function test_challenge_accepts_a_code_once_and_rejects_wrong_codes()
    {
        [$admin, $secret] = $this->admin();
        $code = Totp::code($secret, Totp::currentStep());

        $this->signIn()->assertRedirect(route('admin.two-factor.challenge'));
        $this->post(route('admin.two-factor.challenge.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertNull(session('admin_logged_in'));

        $this->post(route('admin.two-factor.challenge.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('admin_logged_in'));

        // same code cannot be replayed for a second sign-in
        $this->flushSession();
        $this->signIn();
        $this->post(route('admin.two-factor.challenge.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull(session('admin_logged_in'));
    }

    public function test_recovery_code_works_once()
    {
        [$admin] = $this->admin();
        $codes = $admin->regenerateRecoveryCodes();
        $admin->save();

        $this->signIn();
        $this->post(route('admin.two-factor.challenge.verify'), ['code' => strtolower($codes[0])])->assertRedirect();
        $this->assertTrue(session('admin_logged_in'));
        $this->assertSame(7, $admin->fresh()->recoveryCodesLeft());

        $this->flushSession();
        $this->signIn();
        $this->post(route('admin.two-factor.challenge.verify'), ['code' => $codes[0]])->assertSessionHasErrors('code');
    }

    public function test_inactive_or_wrong_password_cannot_sign_in()
    {
        [$admin] = $this->admin();
        $this->signIn('boss@example.test', 'wrong-password')->assertSessionHasErrors('email');

        $admin->update(['is_active' => false]);
        $this->signIn()->assertSessionHasErrors('email');
        $this->assertNull(session('admin_2fa_user_id'));
    }

    public function test_roles_limit_admin_areas()
    {
        $lms = ['admin_logged_in' => true, 'admin_role' => 'lms_admin'];
        $hr = ['admin_logged_in' => true, 'admin_role' => 'hr_admin'];

        $this->withSession($lms)->get('/admin/dashboard')->assertForbidden();
        $this->withSession($lms)->get('/admin/unit-users')->assertForbidden();
        $this->withSession($lms)->post('/admin/approve/1')->assertForbidden();
        $this->withSession($hr)->get('/admin/accounts')->assertForbidden();
        $this->withSession($hr)->get('/admin/osaert')->assertForbidden();
        $this->withSession($hr)->get('/admin/accounts/audit-log')->assertForbidden();
        // signed in, but no role at all
        $this->flushSession();
        $this->withSession(['admin_logged_in' => true])->get('/admin/dashboard')->assertForbidden();
    }

    public function test_super_admin_manages_accounts_and_changes_are_audited()
    {
        [$me] = $this->admin();
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $me->id, 'admin_email' => $me->email]);

        \Illuminate\Support\Facades\Mail::fake();
        // no password in the form: a temporary one is generated and emailed
        $this->post('/admin/accounts', ['name' => 'LMS Person', 'email' => 'LMS@Example.test', 'role' => 'lms_admin'])
            ->assertSessionHas('success');
        $new = AdminUser::where('email', 'lms@example.test')->firstOrFail();
        $sent = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\AccountPassword::class, function ($mail) use (&$sent) {
            $sent = $mail;
            return $mail->hasTo('lms@example.test');
        });
        $this->assertTrue(Hash::check($sent->password, $new->password));
        $this->assertTrue($new->must_change_password);

        // cannot deactivate yourself or demote the last super admin
        $this->post("/admin/accounts/{$me->id}/status", ['is_active' => 0])->assertSessionHas('error');
        $this->post("/admin/accounts/{$me->id}/role", ['role' => 'hr_admin'])->assertSessionHas('error');
        $this->assertSame('super_admin', $me->fresh()->role);

        $entry = DB::table('admin_audit_logs')->where('action', 'admin.accounts.store')->first();
        $this->assertNotNull($entry);
        $this->assertSame($me->email, $entry->admin_email);
        $this->assertSame(302, (int) $entry->status);
        $this->assertStringNotContainsString($sent->password, (string) $entry->details);
    }

    public function test_failed_sign_ins_are_audited()
    {
        $this->admin();
        $this->signIn('boss@example.test', 'nope');

        $this->assertSame(1, DB::table('admin_audit_logs')->where('action', 'login.failed')->count());
    }
}
