<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaReportTest extends TestCase
{
    public function test_prints_the_layout_without_any_rows()
    {
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->string('email'); $t->string('role')->default('unit_checker');
        });
        DB::table('unit_users')->insert(['email' => 'secret.person@example.test']);

        $this->artisan('pcg:schema-report', ['--table' => ['unit_users']])
            ->expectsOutputToContain('unit_users: id')
            ->doesntExpectOutputToContain('secret.person')
            ->assertSuccessful();
    }
}
