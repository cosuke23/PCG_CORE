<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DbError;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Fixes from the reverse-engineering review of 2026-11-08 (docs/SECURITY_AUDIT.md 0j). */
class ReviewNovember8Test extends TestCase
{
    public function test_the_one_shot_soi_save_is_gone()
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name')->nullable(); $t->string('email')->unique(); $t->string('password');
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        $member = User::forceCreate(['name' => 'M', 'email' => 'm@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-1', 'status' => 'Approved']);

        $this->actingAs($member)->post('/reinlist/store', ['basic' => ['last_name' => 'X']])->assertNotFound();
    }

    public function test_database_error_reasons_keep_names_but_not_values()
    {
        $e = new QueryException('mysql', 'insert into users (email) values (?)', ['juan@x.ph'], new \PDOException('x'));
        $e->errorInfo = ['23000', 1062, "Duplicate entry 'juan@x.ph' for key 'users_email_unique'"];
        $reason = DbError::reason($e);
        $this->assertStringNotContainsString('juan@x.ph', $reason);
        $this->assertStringContainsString("key 'users_email_unique'", $reason);
        $this->assertStringStartsWith('SQLSTATE 23000 (1062)', $reason);

        $e->errorInfo = ['22007', 1366, "Incorrect integer value: 'Dela Cruz' for column 'age' at row 1"];
        $reason = DbError::reason($e);
        $this->assertStringNotContainsString('Dela Cruz', $reason);
        $this->assertStringContainsString("column 'age'", $reason);

        $e->errorInfo = ['22001', 1406, "Data too long for column 'sub_unit' at row 1"];
        $this->assertSame("SQLSTATE 22001 (1406): Data too long for column 'sub_unit' at row 1", DbError::reason($e));
    }
}
