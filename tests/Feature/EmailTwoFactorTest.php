<?php

namespace Tests\Feature;

use App\Mail\AdminLoginCode;
use App\Models\AdminUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Admin second sign-in step by one-time email code (PCG_ADMIN_2FA=email). */
class EmailTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['pcg.admin.two_factor' => 'email', 'pcg.admin.email_code_minutes' => 10]);
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('status')->nullable(); $t->timestamps();
            });
        }
        $this->admin = AdminUser::create(['name' => 'Boss', 'email' => 'boss@example.test', 'password' => Hash::make('correct-password-1'), 'role' => 'super_admin']);
        Mail::fake();
    }

    private function signIn(): ?string
    {
        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => 'correct-password-1'])
            ->assertRedirect(route('admin.two-factor.challenge'));

        $code = null;
        Mail::assertSent(AdminLoginCode::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('boss@example.test');
        });

        return $code;
    }

    public function test_password_then_emailed_code_signs_in_once()
    {
        $code = $this->signIn();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNull(session('admin_logged_in'));
        $this->get('/admin/two-factor/setup')->assertRedirect(route('admin.two-factor.challenge')); // no app setup
        $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSee('b***')->assertDontSee($code);

        $this->post(route('admin.two-factor.challenge.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('admin_logged_in'));

        // the same code cannot be used again
        $this->flushSession();
        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => 'correct-password-1']);
        if ($code !== session('admin_email_code.hash')) { // a new code was issued; the old one must fail
            $this->post(route('admin.two-factor.challenge.verify'), ['code' => $code === '000000' ? '111111' : $code])
                ->assertSessionHasErrors('code');
        }
        $this->assertNull(session('admin_logged_in'));
        $this->assertSame(0, DB::table('admin_audit_logs')->where('action', 'login.success')->where('admin_email', 'boss@example.test')->count() - 1);
    }

    public function test_wrong_codes_are_limited_and_expiry_is_enforced()
    {
        $code = $this->signIn();
        $wrong = $code === '123456' ? '654321' : '123456';

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.two-factor.challenge.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        // the rate limit (5/min) blocks a 6th try at once
        $this->post(route('admin.two-factor.challenge.verify'), ['code' => $code])->assertStatus(429);
        // and after it resets, the code itself is used up: even the right code is refused
        $this->travel(61)->seconds();
        $this->post(route('admin.two-factor.challenge.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull(session('admin_logged_in'));

        // expiry
        $this->flushSession();
        Mail::fake();
        $code = $this->signIn();
        $this->travel(11)->minutes();
        $this->post(route('admin.two-factor.challenge.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull(session('admin_logged_in'));
    }

    public function test_resend_is_rate_limited_and_replaces_the_old_code()
    {
        $first = $this->signIn();
        $this->post(route('admin.two-factor.challenge.resend'))->assertSessionHasErrors('code'); // within 60 s

        $this->travel(61)->seconds();
        Mail::fake();
        $this->post(route('admin.two-factor.challenge.resend'))->assertSessionHas('success');
        $second = null;
        Mail::assertSent(AdminLoginCode::class, function ($m) use (&$second) { $second = $m->code; return true; });

        if ($first !== $second) {
            $this->post(route('admin.two-factor.challenge.verify'), ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post(route('admin.two-factor.challenge.verify'), ['code' => $second])->assertRedirect();
        $this->assertTrue(session('admin_logged_in'));
    }

    public function test_mail_failure_does_not_leave_a_half_signed_in_session()
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => 'correct-password-1'])
            ->assertSessionHasErrors('email');
        $this->assertNull(session('admin_2fa_user_id'));
        $this->assertNull(session('admin_logged_in'));
    }

    public function test_wrong_password_sends_no_email()
    {
        $this->post('/admin/login', ['email' => 'boss@example.test', 'password' => 'nope'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }
}
