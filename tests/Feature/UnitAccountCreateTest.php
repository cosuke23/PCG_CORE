<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Hermes (2026-11-08): creating a checker account failed with
 * "Column 'user_id' cannot be null"; the real table differs from the code.
 */
class UnitAccountCreateTest extends TestCase
{
    public function test_checker_account_is_created_on_a_table_with_required_unused_columns()
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        // like the real table: user_id / personnel_id required, no permissions column
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('personnel_id', 100); $t->string('serial_number', 100)->nullable();
            $t->string('full_name')->nullable(); $t->string('email'); $t->string('username')->nullable(); $t->string('password');
            $t->string('role')->nullable(); $t->string('unit_name')->nullable(); $t->string('unit_code')->nullable();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });

        $super = ['admin_logged_in' => true, 'admin_role' => 'super_admin'];
        $this->withSession($super)->post('/unit-users/store', [
            'full_name' => 'Eumica Checker', 'email' => 'checker@example.test', 'password' => 'chosen-pass-123', 'unit_name' => 'MEPCOM',
        ])->assertRedirect()->assertSessionHas('success');

        $row = DB::table('unit_users')->where('email', 'checker@example.test')->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->user_id);
        $this->assertSame('', $row->personnel_id);
        $this->assertSame('MEPCOM', $row->unit_name);
        $this->assertTrue(Hash::check('chosen-pass-123', $row->password));
    }
}
