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

    public function test_m1_access_password_and_status_work_on_a_table_without_timestamps()
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->string('email'); $t->string('password'); $t->string('role')->nullable();
            $t->string('unit_name')->nullable(); $t->boolean('is_active')->default(true);
        });
        DB::table('unit_users')->insert(['email' => 'c@example.test', 'password' => 'x', 'role' => 'unit_checker', 'unit_name' => 'MEPTDI']);
        $super = ['admin_logged_in' => true, 'admin_role' => 'super_admin'];

        $this->withSession($super)->post('/admin/unit-users/1/role', ['role' => 'm1_checker'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('m1_checker', DB::table('unit_users')->value('role'));
        // granting again (nothing changes) is not a "not found"
        $this->withSession($super)->post('/admin/unit-users/1/role', ['role' => 'm1_checker'])->assertRedirect();

        $this->withSession($super)->post('/admin/unit-users/1/password', ['password' => 'new-password-123'])->assertRedirect();
        $this->assertTrue(Hash::check('new-password-123', DB::table('unit_users')->value('password')));

        $this->withSession($super)->post('/admin/unit-users/1/status', ['is_active' => 0])->assertRedirect();
        $this->assertSame(0, (int) DB::table('unit_users')->value('is_active'));

        $this->withSession($super)->post('/admin/unit-users/99/role', ['role' => 'm1_checker'])->assertNotFound();
    }
}
