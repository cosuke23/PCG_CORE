<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * One account per serial number: records are joined on serial_number, so a
 * second account with someone else's serial must never be created or approved.
 */
class SerialNumberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->unique(); $t->string('password');
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });

        DB::table('users')->insert([
            ['name' => 'Owner', 'email' => 'owner@x.test', 'password' => 'x', 'serial_number' => 'SN-100', 'status' => 'Approved'],
            ['name' => 'Rejected', 'email' => 'rej@x.test', 'password' => 'x', 'serial_number' => 'SN-200', 'status' => 'Rejected'],
            ['name' => 'Impostor', 'email' => 'imp@x.test', 'password' => 'x', 'serial_number' => 'sn-100 ', 'status' => 'Pending'],
        ]);
    }

    public function test_serial_check_ignores_case_spaces_and_rejected_accounts()
    {
        $this->assertTrue(User::serialTaken('SN-100'));
        $this->assertTrue(User::serialTaken('  sn-100'));
        $this->assertFalse(User::serialTaken('SN-200'), 'a rejected account does not block the serial');
        $this->assertFalse(User::serialTaken('SN-100', DB::table('users')->where('email', 'owner@x.test')->value('id')),
            'a pending account (the impostor) does not reserve the serial');
        $this->assertFalse(User::serialTaken('SN-300'));
        $this->assertFalse(User::serialTaken(''));
    }

    public function test_registration_with_a_taken_serial_is_refused()
    {
        $this->from('/register?type=officer')->post('/register.submit', [
            'registration_type' => 'officer', 'rank' => 'ENS', 'name' => 'Someone Else',
            'serial_number' => 'SN-100', 'email' => 'new@x.test',
            'password' => 'Secret-pass-1', 'password_confirmation' => 'Secret-pass-1',
            'dob' => '1990-01-01', 'date_of_appointment' => '2015-01-01', 'command' => 'MEPCOM',
            'selfie_image' => 'data:image/png;base64,AAAA',
        ])->assertSessionHasErrors('serial_number');

        $this->assertSame(0, DB::table('users')->where('email', 'new@x.test')->count());
    }

    public function test_a_pending_account_does_not_block_the_real_member()
    {
        DB::table('users')->insert(['name' => 'Squatter', 'email' => 'sq@x.test', 'password' => 'x', 'serial_number' => 'SN-500', 'status' => 'Pending']);

        $this->assertFalse(User::serialTaken('SN-500'));
    }

    public function test_a_second_account_for_a_serial_is_not_approved()
    {
        $impostor = DB::table('users')->where('email', 'imp@x.test')->value('id');

        $this->withoutMiddleware([\App\Http\Middleware\AuditAdminRequests::class])
            ->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin'])
            ->post("/admin/approve/{$impostor}")
            ->assertSessionHas('error');

        $this->assertSame('Pending', DB::table('users')->where('id', $impostor)->value('status'));
    }
}
