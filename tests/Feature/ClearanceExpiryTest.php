<?php

namespace Tests\Feature;

use App\Mail\ClearanceExpiring;
use App\Support\ClearanceExpiry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Clearance expiry reminders 30 / 7 / 0 days before, and the checker dashboard card (owner request 2026-11-20). */
class ClearanceExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 7, 30, 0, 'Asia/Manila'));
        Cache::flush();
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('serial_number')->nullable();
            $t->string('rank')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable(); $t->timestamps();
        });
        Schema::create('personnel_data', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('personnel_id')->nullable(); $t->string('serial_number')->nullable(); $t->string('status')->nullable();
            foreach (['nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance'] as $f) {
                $t->string($f)->nullable(); $t->date($f . '_expiration')->nullable();
            }
        });
        $u = fn ($name, $email, $serial, $unit) => DB::table('users')->insertGetId(['name' => $name, 'email' => $email, 'serial_number' => $serial, 'status' => 'Approved', 'mother_unit' => $unit, 'rank' => 'SN1']);
        $ana = $u('Ana', 'ana@x.test', 'S1', 'MEPLEF');
        $ben = $u('Ben', 'ben@x.test', 'S2', 'MEPLEF');
        $cara = $u('Cara', 'cara@x.test', 'S3', 'MSSC');
        DB::table('personnel_data')->insert([
            // old row of Ana's, replaced by the newer one below: ignored
            ['personnel_id' => $ana, 'serial_number' => 'S1', 'status' => 'Approved', 'nbi_clearance' => 'a.enc', 'nbi_clearance_expiration' => '2026-11-27', 'ombudsman_clearance' => null, 'ombudsman_clearance_expiration' => null, 'sandiganbayan_clearance' => null, 'sandiganbayan_clearance_expiration' => null],
            ['personnel_id' => $ana, 'serial_number' => 'S1', 'status' => 'Approved', 'nbi_clearance' => 'b.enc', 'nbi_clearance_expiration' => '2026-12-20', 'ombudsman_clearance' => 'o.enc', 'ombudsman_clearance_expiration' => '2026-11-20', 'sandiganbayan_clearance' => null, 'sandiganbayan_clearance_expiration' => '2026-11-27'],
            ['personnel_id' => $ben, 'serial_number' => 'S2', 'status' => 'Approved', 'nbi_clearance' => 'c.enc', 'nbi_clearance_expiration' => '2026-11-10', 'ombudsman_clearance' => null, 'ombudsman_clearance_expiration' => null, 'sandiganbayan_clearance' => 'd.enc', 'sandiganbayan_clearance_expiration' => '2027-06-01'],
            ['personnel_id' => $cara, 'serial_number' => 'S3', 'status' => 'Approved', 'nbi_clearance' => 'e.enc', 'nbi_clearance_expiration' => '2026-11-27', 'ombudsman_clearance' => null, 'ombudsman_clearance_expiration' => null, 'sandiganbayan_clearance' => null, 'sandiganbayan_clearance_expiration' => null],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_members_are_emailed_30_7_and_0_days_before_once()
    {
        Mail::fake();
        $this->artisan('pcg:clearance-reminders')->assertSuccessful();
        // Ana: NBI in 30 days, Ombudsman today (Sandiganbayan has a date but no file); Cara: NBI in 7 days
        Mail::assertSent(ClearanceExpiring::class, 3);
        Mail::assertSent(ClearanceExpiring::class, fn ($m) => $m->hasTo('ana@x.test') && $m->label === 'NBI Clearance' && $m->daysLeft === 30);
        Mail::assertSent(ClearanceExpiring::class, fn ($m) => $m->hasTo('ana@x.test') && $m->label === 'Ombudsman Clearance' && $m->daysLeft === 0);
        Mail::assertSent(ClearanceExpiring::class, fn ($m) => $m->hasTo('cara@x.test') && $m->daysLeft === 7);
        Mail::assertNotSent(ClearanceExpiring::class, fn ($m) => $m->hasTo('ben@x.test'));   // already expired: no daily nagging

        $this->artisan('pcg:clearance-reminders')->assertSuccessful();
        Mail::assertSent(ClearanceExpiring::class, 3);
    }

    public function test_the_checker_card_lists_their_unit_only_and_m1_sees_all()
    {
        $this->app['request']->setLaravelSession($this->app['session']->driver());
        session(['unit_role' => 'unit_checker', 'unit_name' => 'meplef ']);
        $cs = ClearanceExpiry::forUnitChecker();
        $this->assertSame(1, $cs['expired']);                       // Ben's NBI
        $this->assertSame(2, $cs['soon']);                          // Ana's NBI and Ombudsman
        $this->assertNotContains('Cara', $cs['list']->pluck('name')->all());
        $this->assertSame('expired 10 days ago', ClearanceExpiry::when($cs['list']->first()->days_left));

        $this->app['request']->attributes->remove('unit_clearance_summary');
        session(['unit_role' => \App\Models\UnitUser::ROLE_M1]);
        $this->assertSame(4, ClearanceExpiry::forUnitChecker()['list']->count());
    }
}
