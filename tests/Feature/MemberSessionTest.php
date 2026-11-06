<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** A member's account status is checked on every request, not only at sign-in. */
class MemberSessionTest extends TestCase
{
    public function test_a_rejected_member_is_signed_out_at_the_next_request()
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        $member = User::forceCreate(['name' => 'M', 'email' => 'm@example.test', 'password' => Hash::make('x'), 'serial_number' => 'SN-1', 'status' => 'Approved']);
        $this->actingAs($member);

        // e.g. rejected after another account was approved for the same serial
        $member->forceFill(['status' => 'Rejected'])->save();

        $this->get('/upload-201')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
