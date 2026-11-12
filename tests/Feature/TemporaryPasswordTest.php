<?php

namespace Tests\Feature;

use App\Mail\AccountPassword;
use App\Models\AdminUser;
use App\Support\TemporaryPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Owner request 2026-11-12: new admin and unit checker accounts get a random
 * password by email (nobody types it) and must choose their own at the first
 * sign-in; "Email new password" does the same for an existing account.
 */
class TemporaryPasswordTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Mail::fake();
        $this->super = AdminUser::create(['name' => 'Boss', 'email' => 'boss@example.test', 'password' => Hash::make('boss-password-123'), 'role' => 'super_admin', 'is_active' => true]);
    }

    private function asSuper(): array
    {
        return ['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $this->super->id, 'admin_email' => $this->super->email, 'admin_since' => time()];
    }

    private function sentTo(string $email): AccountPassword
    {
        $sent = null;
        Mail::assertSent(AccountPassword::class, function ($mail) use ($email, &$sent) {
            if ($mail->hasTo($email)) {
                $sent = $mail;
            }
            return $mail->hasTo($email);
        });

        return $sent;
    }

    public function test_generated_passwords_are_long_mixed_and_different()
    {
        $a = TemporaryPassword::generate();
        $b = TemporaryPassword::generate();
        $this->assertSame(16, strlen($a));
        $this->assertNotSame($a, $b);
        $this->assertMatchesRegularExpression('/[A-Z]/', $a);
        $this->assertMatchesRegularExpression('/[a-z]/', $a);
        $this->assertMatchesRegularExpression('/[2-9]/', $a);
        $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $a, 'no look-alike characters');
    }

    public function test_new_admin_gets_an_emailed_password_with_the_right_sign_in_page()
    {
        $this->withSession($this->asSuper())->post('/admin/accounts', ['name' => 'The Adjutant', 'email' => 'adj@example.test', 'role' => 'adjutant'])
            ->assertSessionHas('success');

        $mail = $this->sentTo('adj@example.test');
        $admin = AdminUser::where('email', 'adj@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($mail->password, $admin->password));
        $this->assertTrue($admin->must_change_password);
        $this->assertSame(route('adjutant.login'), $mail->signInUrl);
        $this->assertStringNotContainsString($mail->password, $mail->envelope()->subject, 'never in the subject');
        $html = $mail->render();
        $this->assertStringContainsString($mail->password, $html);
        $this->assertStringContainsString('must choose your own password', $html);
    }

    public function test_no_account_is_created_when_the_email_cannot_be_sent()
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $this->withSession($this->asSuper())->post('/admin/accounts', ['name' => 'Nobody', 'email' => 'nobody@example.test', 'role' => 'hr_admin'])
            ->assertSessionHas('error');
        $this->assertFalse(AdminUser::where('email', 'nobody@example.test')->exists(), 'rolled back');
    }

    public function test_production_refuses_a_mailer_that_would_not_deliver()
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['mail.default' => 'log']);
        $this->withSession($this->asSuper())->post('/admin/accounts', ['name' => 'Nobody', 'email' => 'nobody@example.test', 'role' => 'hr_admin'])
            ->assertSessionHas('error');
        $this->assertFalse(AdminUser::where('email', 'nobody@example.test')->exists());
    }

    public function test_admin_must_choose_a_password_before_anything_opens()
    {
        $hr = AdminUser::create(['name' => 'HR', 'email' => 'hr@example.test', 'password' => Hash::make('Temp1234abcdEF'), 'role' => 'hr_admin', 'is_active' => true, 'must_change_password' => true]);
        $session = ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_user_id' => $hr->id, 'admin_email' => $hr->email, 'admin_since' => time()];

        $this->withSession($session)->get('/admin/dashboard')->assertRedirect(route('admin.password.edit'));
        $this->getJson('/admin/hrmis')->assertStatus(403);
        $this->get('/admin/password')->assertOk()->assertSee('Choose your password');

        // too short / no numbers / not confirmed / same as the temporary one
        $this->post('/admin/password', ['password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->post('/admin/password', ['password' => 'only-letters-here', 'password_confirmation' => 'only-letters-here'])->assertSessionHasErrors('password');
        $this->post('/admin/password', ['password' => 'my-own-pass-2026', 'password_confirmation' => 'different-2026'])->assertSessionHasErrors('password');
        $this->post('/admin/password', ['password' => 'Temp1234abcdEF', 'password_confirmation' => 'Temp1234abcdEF'])->assertSessionHasErrors('password');
        $this->assertTrue($hr->fresh()->must_change_password);

        $this->post('/admin/password', ['password' => 'my-own-pass-2026', 'password_confirmation' => 'my-own-pass-2026'])->assertRedirect();
        $hr->refresh();
        $this->assertFalse($hr->must_change_password);
        $this->assertTrue(Hash::check('my-own-pass-2026', $hr->password));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'password.changed_first_sign_in']);

        // now the console opens, and the password page sends you home
        $this->get('/admin/organization')->assertOk();
        $this->get('/admin/password')->assertRedirect();
    }

    public function test_email_new_password_resets_and_forces_a_change()
    {
        $hr = AdminUser::create(['name' => 'HR', 'email' => 'hr@example.test', 'password' => Hash::make('old-password-123'), 'role' => 'hr_admin', 'is_active' => true]);
        $this->withSession($this->asSuper())->post("/admin/accounts/{$hr->id}/password")->assertSessionHas('success');

        $mail = $this->sentTo('hr@example.test');
        $this->assertTrue($mail->reset);
        $hr->refresh();
        $this->assertFalse(Hash::check('old-password-123', $hr->password), 'the old password stops working');
        $this->assertTrue(Hash::check($mail->password, $hr->password));
        $this->assertTrue($hr->must_change_password);
    }

    public function test_unit_checker_must_choose_a_password_before_anything_opens()
    {
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->string('full_name')->nullable(); $t->string('email'); $t->string('password'); $t->string('role')->nullable();
            $t->string('unit_name')->nullable(); $t->boolean('is_active')->default(true); $t->boolean('must_change_password')->default(false); $t->timestamps();
        });
        $id = DB::table('unit_users')->insertGetId(['full_name' => 'Checker', 'email' => 'c@example.test', 'password' => Hash::make('Temp1234abcdEF'),
            'role' => 'unit_checker', 'unit_name' => 'MEPLEF', 'is_active' => 1, 'must_change_password' => 1]);

        // signing in works with the temporary password, then only the password page opens
        $this->post('/unit/login', ['email' => 'c@example.test', 'password' => 'Temp1234abcdEF'])->assertRedirect('/unit/dashboard');
        $this->get('/unit/dashboard')->assertRedirect(route('unit.password.edit'));
        $this->get('/unit/201-files')->assertRedirect(route('unit.password.edit'));
        $this->get('/unit/password')->assertOk()->assertSee('Choose your password');

        $this->post('/unit/password', ['password' => 'checker-pass-2026', 'password_confirmation' => 'checker-pass-2026'])->assertRedirect('/unit/dashboard');
        $row = DB::table('unit_users')->find($id);
        $this->assertSame(0, (int) $row->must_change_password);
        $this->assertTrue(Hash::check('checker-pass-2026', $row->password));
    }

    public function test_forms_no_longer_ask_for_a_password()
    {
        $html = $this->withSession($this->asSuper())->get('/admin/accounts')->assertOk()->getContent();
        $this->assertStringNotContainsString('Temporary password</label>', $html);
        $this->assertStringContainsString('emailed to the address above', $html);
        $this->assertStringContainsString('Email new password', $html);
    }
}
