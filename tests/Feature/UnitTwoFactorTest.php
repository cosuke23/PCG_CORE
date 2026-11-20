<?php

namespace Tests\Feature;

use App\Mail\UnitLoginCode;
use App\Models\UnitUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Unit and M1 checkers sign in with their password and then an emailed one-time code (owner request 2026-11-20). */
class UnitTwoFactorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['pcg.unit.two_factor' => 'email']);
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->string('full_name')->nullable(); $t->string('email'); $t->string('password')->nullable();
            $t->string('role')->nullable(); $t->string('unit_name')->nullable(); $t->boolean('is_active')->default(true);
            $t->boolean('must_change_password')->default(false); $t->timestamps();
        });
        DB::table('unit_users')->insert([
            ['full_name' => 'Unit Checker', 'email' => 'unit@x.test', 'password' => Hash::make('pw-unit-12345'), 'role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF', 'is_active' => true, 'must_change_password' => false],
            ['full_name' => 'M1 Checker', 'email' => 'm1@x.test', 'password' => Hash::make('pw-m1-123456'), 'role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM', 'is_active' => true, 'must_change_password' => false],
        ]);
    }

    private function sentCode(string $to): string
    {
        $code = null;
        Mail::assertSent(UnitLoginCode::class, function ($m) use ($to, &$code) {
            if ($m->hasTo($to)) {
                $code = $m->code;
            }
            return $m->hasTo($to);
        });
        return (string) $code;
    }

    public function test_the_checker_is_signed_in_only_after_the_emailed_code()
    {
        Mail::fake();
        $this->post('/unit/login', ['email' => 'unit@x.test', 'password' => 'pw-unit-12345'])->assertRedirect(route('unit.two-factor'));
        $this->assertNull(session('unit_user_id'));
        $this->get('/unit/dashboard')->assertRedirect();                     // not in yet
        $this->get('/unit/two-factor')->assertOk()->assertSee('u***@x.test');

        $code = $this->sentCode('unit@x.test');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->post('/unit/two-factor', ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertNull(session('unit_user_id'));

        $this->post('/unit/two-factor', ['code' => $code])->assertRedirect('/unit/dashboard');
        $this->assertSame(1, (int) session('unit_user_id'));
        $this->assertSame('MEPLEF', session('unit_name'));

        // single use
        $this->post('/unit/two-factor', ['code' => $code])->assertRedirect('/unit/login');
    }

    public function test_m1_checkers_use_the_code_too_and_land_on_the_m1_page()
    {
        Mail::fake();
        $this->post('/unit/loginM1', ['email' => 'm1@x.test', 'password' => 'pw-m1-123456'])->assertRedirect(route('unit.two-factor'));
        $this->post('/unit/two-factor', ['code' => $this->sentCode('m1@x.test')])->assertRedirect('/unit/dashboardM1');
        $this->assertSame(UnitUser::ROLE_M1, session('unit_role'));
    }

    public function test_a_wrong_password_sends_no_code_and_five_wrong_codes_end_it()
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);   // the limiter has its own tests
        Mail::fake();
        $this->post('/unit/login', ['email' => 'unit@x.test', 'password' => 'nope'])->assertSessionHas('error');
        Mail::assertNothingSent();

        $this->post('/unit/login', ['email' => 'unit@x.test', 'password' => 'pw-unit-12345']);
        $code = $this->sentCode('unit@x.test');
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/unit/two-factor', ['code' => $wrong]);
        }
        $this->post('/unit/two-factor', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull(session('unit_user_id'));
    }

    public function test_the_code_page_needs_a_pending_sign_in()
    {
        $this->get('/unit/two-factor')->assertRedirect('/unit/login');
        $this->post('/unit/two-factor', ['code' => '123456'])->assertRedirect('/unit/login');
    }

    public function test_with_the_second_step_off_the_password_is_enough()
    {
        config(['pcg.unit.two_factor' => 'off']);
        Mail::fake();
        $this->post('/unit/login', ['email' => 'unit@x.test', 'password' => 'pw-unit-12345'])->assertRedirect('/unit/dashboard');
        Mail::assertNothingSent();
    }
}
