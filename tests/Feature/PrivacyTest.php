<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Data Privacy Act basics (owner request 2026-11-20): notice, consent at registration, retention report. */
class PrivacyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->string('status')->nullable();
            $t->string('serial_number')->nullable(); $t->timestamps();
        });
    }

    public function test_the_notice_is_public_and_linked_from_registration()
    {
        $this->get('/privacy')->assertOk()->assertSee('Data Privacy Act of 2012')->assertSee('version 1.0');
        $this->get('/register?type=non_officer')->assertOk()->assertSee('name="privacy_consent"', false)->assertSee(route('privacy'), false);
    }

    public function test_registration_needs_the_consent_box()
    {
        $this->from('/register?type=non_officer')->post('/register.submit', ['email' => 'new@x.test', 'name' => 'New Member'])
            ->assertSessionHasErrors('privacy_consent');
        $this->from('/register?type=non_officer')->post('/register.submit', ['email' => 'new@x.test', 'name' => 'New Member', 'privacy_consent' => '1'])
            ->assertSessionDoesntHaveErrors('privacy_consent');
    }

    public function test_the_migration_adds_and_removes_only_its_columns()
    {
        $m = require base_path('database/migrations/2026_11_20_000003_add_privacy_consent_to_users.php');
        $m->up();
        $this->assertTrue(Schema::hasColumns('users', ['privacy_consent_at', 'privacy_notice_version']));
        $m->up();                                                   // idempotent
        $m->down();
        $this->assertFalse(Schema::hasColumn('users', 'privacy_consent_at'));
        $this->assertTrue(Schema::hasColumn('users', 'email'));
    }

    public function test_the_retention_report_counts_without_names()
    {
        DB::table('users')->insert([
            ['name' => 'Old Pending', 'email' => 'a@x.test', 'status' => 'Pending', 'created_at' => now()->subDays(400)],
            ['name' => 'New Pending', 'email' => 'b@x.test', 'status' => 'Pending', 'created_at' => now()->subDays(3)],
            ['name' => 'Approved', 'email' => 'c@x.test', 'status' => 'Approved', 'created_at' => now()->subDays(900)],
        ]);
        $this->artisan('pcg:retention-report')->assertSuccessful()
            ->expectsOutputToContain('Read-only')
            ->doesntExpectOutputToContain('Old Pending');
    }
}
