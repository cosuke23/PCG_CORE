<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurgeAccountsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 8)->setTime(12, 0));

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('email'); $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::create('admin_users', function (Blueprint $t) {
            $t->id(); $t->string('email'); $t->string('role'); $t->boolean('is_active')->default(true);
        });
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->string('email');
        });
        Schema::create('personnel_data', function (Blueprint $t) {
            $t->id(); $t->string('serial_number');
        });
        Schema::create('admin_audit_logs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('admin_user_id')->nullable();
        });

        DB::table('users')->insert([
            ['email' => 'keep@x.test', 'serial_number' => 'K-1', 'status' => 'Approved', 'created_at' => '2026-10-01 00:00:00'],
            ['email' => 'old@x.test', 'serial_number' => 'O-1', 'status' => 'Approved', 'created_at' => '2026-10-01 00:00:00'],
            ['email' => 'twin@x.test', 'serial_number' => 'k-1 ', 'status' => 'Pending', 'created_at' => '2026-10-01 00:00:00'],
            ['email' => 'today@x.test', 'serial_number' => 'T-1', 'status' => 'Pending', 'created_at' => '2026-11-08 01:00:00'],
        ]);
        DB::table('personnel_data')->insert([['serial_number' => 'K-1'], ['serial_number' => 'O-1'], ['serial_number' => 'T-1'], ['serial_number' => 'NOBODY']]);
        DB::table('admin_users')->insert([
            ['email' => 'boss@x.test', 'role' => 'super_admin', 'is_active' => true],
            ['email' => 'hr@x.test', 'role' => 'hr_admin', 'is_active' => true],
        ]);
        DB::table('unit_users')->insert([['email' => 'unit@x.test']]);
        DB::table('admin_audit_logs')->insert([['admin_user_id' => 2]]);
    }

    public function test_dry_run_changes_nothing()
    {
        $this->artisan('pcg:purge-accounts', ['--keep-admin' => ['boss@x.test'], '--keep-user' => ['keep@x.test']])
            ->expectsOutputToContain('DRY RUN')->assertSuccessful();

        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame(2, DB::table('admin_users')->count());
    }

    public function test_apply_keeps_the_named_accounts_and_todays_registrations()
    {
        $this->artisan('pcg:purge-accounts', ['--keep-admin' => ['BOSS@x.test'], '--keep-user' => ['keep@x.test'], '--keep-since' => '2026-11-08', '--apply' => true])
            ->expectsQuestion('This cannot be undone without the backup. Type DELETE to continue', 'DELETE')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['keep@x.test', 'today@x.test'], DB::table('users')->pluck('email')->all());
        $this->assertSame(['boss@x.test'], DB::table('admin_users')->pluck('email')->all());
        $this->assertSame(0, DB::table('unit_users')->count());
        // the deleted account's records go; kept serials (even shared with a deleted twin) and unknown ones stay
        $this->assertEqualsCanonicalizing(['K-1', 'T-1', 'NOBODY'], DB::table('personnel_data')->pluck('serial_number')->all());
        $this->assertSame(1, DB::table('admin_audit_logs')->count());
    }

    public function test_refuses_without_confirmation_or_a_kept_super_admin()
    {
        $this->artisan('pcg:purge-accounts', ['--keep-admin' => ['hr@x.test'], '--apply' => true])->assertFailed();
        $this->artisan('pcg:purge-accounts', ['--keep-admin' => ['boss@x.test'], '--apply' => true])
            ->expectsQuestion('This cannot be undone without the backup. Type DELETE to continue', 'no')
            ->assertFailed();

        $this->assertSame(4, DB::table('users')->count());
        $this->assertSame(1, DB::table('unit_users')->count());
    }
}
