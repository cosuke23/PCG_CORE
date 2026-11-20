<?php

namespace Tests\Feature;

use App\Mail\MemberLoginCode;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Member sign-in protection: optional emailed code, devices list, sign out elsewhere (owner request 2026-11-20). */
class MemberSecurityTest extends TestCase
{
    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_21_000001_add_member_sign_in_security.php'))->up();
        $this->ana = User::forceCreate(['name' => 'Ana Reyes', 'email' => 'ana@x.test', 'password' => Hash::make('pw-ana-12345'), 'serial_number' => 'SN-A', 'status' => 'Approved']);
    }

    public function test_without_the_code_the_password_signs_in_and_the_device_is_listed()
    {
        Mail::fake();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0')->post('/login', ['email' => 'ana@x.test', 'password' => 'pw-ana-12345'])->assertRedirect('/dashboard');
        Mail::assertNothingSent();
        $this->assertSame('Chrome on Windows', DB::table('member_devices')->value('label'));
        $this->get('/security')->assertOk()->assertSee('Your password alone signs you in')->assertSee('This device');
    }

    public function test_the_member_turns_on_the_code_and_then_needs_it()
    {
        Mail::fake();
        $this->actingAs($this->ana)->post('/security/login-code', ['enabled' => '1'])->assertSessionHas('success');
        $this->assertTrue((bool) DB::table('users')->value('login_code_enabled'));
        $this->post('/logout');

        $this->post('/login', ['email' => 'ana@x.test', 'password' => 'pw-ana-12345'])->assertRedirect(route('login.code'));
        $this->assertGuest();
        $code = null;
        Mail::assertSent(MemberLoginCode::class, function ($m) use (&$code) { $code = $m->code; return $m->hasTo('ana@x.test'); });
        $this->post('/login/code', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/login/code', ['code' => $code])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->ana);
        $this->post('/login/code', ['code' => $code])->assertRedirect('/login');   // single use
    }

    public function test_signing_out_other_devices_ends_their_sessions()
    {
        // another device of Ana's
        $other = DB::table('member_devices')->insertGetId(['user_id' => $this->ana->id, 'label' => 'Safari on iPhone/iPad', 'created_at' => now(), 'last_seen_at' => now()]);
        $this->post('/login', ['email' => 'ana@x.test', 'password' => 'pw-ana-12345']);
        $this->get('/security')->assertSee('2 devices are signed in')->assertSee('Safari on iPhone/iPad');
        $this->post('/security/devices/others')->assertSessionHas('success', '1 other device was signed out.');
        $this->assertNotNull(DB::table('member_devices')->where('id', $other)->value('signed_out_at'));
        $this->get('/security')->assertOk();                                    // this device stays signed in

        // a session whose device was signed out elsewhere is ended on its next page
        $mine = (int) session(\App\Support\MemberDevices::SESSION_KEY);
        DB::table('member_devices')->where('id', $mine)->update(['signed_out_at' => now()]);
        $this->get('/security')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_member_cannot_sign_out_someone_elses_device()
    {
        $bob = User::forceCreate(['name' => 'Bob', 'email' => 'bob@x.test', 'password' => Hash::make('x'), 'status' => 'Approved']);
        $theirs = DB::table('member_devices')->insertGetId(['user_id' => $bob->id, 'label' => 'x', 'created_at' => now(), 'last_seen_at' => now()]);
        $this->actingAs($this->ana)->post("/security/devices/$theirs")->assertNotFound();
        $this->assertNull(DB::table('member_devices')->where('id', $theirs)->value('signed_out_at'));
    }
}
