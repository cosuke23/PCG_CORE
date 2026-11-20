<?php

namespace Tests\Feature;

use App\Models\UnitUser;
use App\Models\User;
use App\Support\LeaveCalendar;
use App\Support\LeaveCredits;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Leave credits and balances (owner request 2026-11-20): opening balance plus 2.5
 * days a month for ordinary leave, RNR days granted after duty, yearly solo parent
 * and VAWC days; filing and approval need enough credits, approval takes the days,
 * cancelling approved leave gives them back; unit checkers manage their unit.
 */
class LeaveCreditsTest extends TestCase
{
    private array $unit = ['unit_user_id' => 1, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF'];
    private array $otherUnit = ['unit_user_id' => 3, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'NOCOP'];
    private array $m1 = ['unit_user_id' => 2, 'unit_role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'];
    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 9, 0, 0, LeaveCalendar::TZ));

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->string('full_name')->nullable(); $t->string('email'); $t->string('password')->nullable();
            $t->string('role')->nullable(); $t->string('unit_name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_20_000001_create_leave_requests_table.php'))->up();
        (require base_path('database/migrations/2026_11_20_000002_create_leave_credit_entries_table.php'))->up();

        $this->ana = User::forceCreate(['name' => 'Ana Reyes', 'email' => 'a@x.test', 'password' => bcrypt('x'), 'rank' => 'SN2', 'serial_number' => 'SN-A', 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        User::forceCreate(['name' => 'Ben Cruz', 'email' => 'b@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-B', 'status' => 'Approved', 'mother_unit' => 'NOCOP']);
        DB::table('unit_users')->insert([
            ['full_name' => 'Unit Checker', 'email' => 'unit@x.test', 'password' => Hash::make('pw-unit-123'), 'role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF'],
            ['full_name' => 'M1 Checker', 'email' => 'm1@x.test', 'password' => Hash::make('pw-m1-1234'), 'role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'],
            ['full_name' => 'Other Checker', 'email' => 'nocop@x.test', 'password' => Hash::make('pw-nocop-12'), 'role' => UnitUser::ROLE_UNIT, 'unit_name' => 'NOCOP'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function credit(string $action, array $data)
    {
        return $this->withSession($this->unit)->post('/unit/leave/credits/' . $this->ana->id, ['action' => $action] + $data);
    }

    private function file(string $type, string $start, string $end)
    {
        return $this->actingAs($this->ana)->post('/leave', ['leave_type' => $type, 'start_date' => $start, 'end_date' => $end]);
    }

    private function avail(string $pool): ?float
    {
        return LeaveCredits::balances($this->ana->id)[$pool]['available'];
    }

    public function test_the_migration_is_reversible_and_never_drops_credit_history()
    {
        $m = require base_path('database/migrations/2026_11_20_000002_create_leave_credit_entries_table.php');
        LeaveCredits::post($this->ana->id, 'SN-A', 'rnr', 'grant', 5, '2026-11-01');
        try {
            $m->down();
            $this->fail('down() must refuse while credits are recorded');
        } catch (\RuntimeException $e) {
            $this->assertTrue(Schema::hasTable('leave_credit_entries'));
        }
        DB::table('leave_credit_entries')->delete();
        $m->down();
        $this->assertFalse(Schema::hasTable('leave_credit_entries'));
    }

    public function test_ordinary_leave_needs_an_opening_balance_then_accrues_monthly()
    {
        $this->file('ordinary', '2026-12-01', '2026-12-03')->assertSessionHasErrors('leave_type');
        $this->assertNull($this->avail('annual'));

        // 10 days as of Sep 15: Oct 1 and Nov 1 add 2.5 each
        $this->credit('opening', ['days' => 10, 'effective_date' => '2026-09-15'])->assertSessionHas('success');
        $this->assertSame(15.0, $this->avail('annual'));
        $this->assertSame(2, LeaveCredits::monthsAccrued('2026-09-15'));

        $this->actingAs($this->ana)->get('/leave?month=2026-11')->assertOk()
            ->assertSee('My leave credits')->assertSee('15 days available', false)->assertSee('Monthly credit');
    }

    public function test_filing_holds_days_approval_takes_them_and_cancelling_gives_them_back()
    {
        $this->credit('opening', ['days' => 10, 'effective_date' => '2026-11-01']);
        $this->file('ordinary', '2026-12-01', '2026-12-04')->assertSessionHasNoErrors();
        $b = LeaveCredits::balances($this->ana->id)['annual'];
        $this->assertSame([10.0, 4.0, 6.0], [$b['balance'], $b['held'], $b['available']]);

        $this->file('ordinary', '2026-12-10', '2026-12-16')->assertSessionHasErrors('leave_type');   // 7 > 6 available

        $id = DB::table('leave_requests')->value('id');
        $this->withSession($this->unit)->post("/unit/leave/$id/review", ['decision' => 'approve'])->assertSessionHas('success');
        $b = LeaveCredits::balances($this->ana->id)['annual'];
        $this->assertSame([6.0, 0.0, 6.0], [$b['balance'], $b['held'], $b['available']]);
        $this->assertSame(1, DB::table('leave_credit_entries')->where('kind', 'used')->where('leave_request_id', $id)->count());

        $this->actingAs($this->ana)->post("/leave/$id/cancel")->assertSessionHas('success');
        $this->assertSame(10.0, $this->avail('annual'));
        $this->assertSame(1, DB::table('leave_credit_entries')->where('kind', 'restored')->count());
    }

    public function test_declined_leave_does_not_touch_the_credits()
    {
        $this->credit('opening', ['days' => 10, 'effective_date' => '2026-11-01']);
        $this->file('ordinary', '2026-12-01', '2026-12-04');
        $id = DB::table('leave_requests')->value('id');
        $this->withSession($this->unit)->post("/unit/leave/$id/review", ['decision' => 'decline', 'note' => 'Deployment']);
        $this->assertSame(10.0, $this->avail('annual'));
        $this->assertSame(0, DB::table('leave_credit_entries')->where('kind', 'used')->count());
    }

    public function test_approval_is_refused_when_the_credits_no_longer_cover_the_leave()
    {
        $this->credit('opening', ['days' => 5, 'effective_date' => '2026-11-01']);
        $this->file('ordinary', '2026-12-01', '2026-12-04');
        $this->credit('adjustment', ['pool' => 'annual', 'days' => -3, 'effective_date' => '2026-11-20', 'note' => 'Correction from the leave card']);

        $id = DB::table('leave_requests')->value('id');
        $this->withSession($this->unit)->post("/unit/leave/$id/review", ['decision' => 'approve'])->assertSessionHas('error');
        $this->assertSame('pending', DB::table('leave_requests')->value('status'));
    }

    public function test_rnr_is_earned_by_duty()
    {
        $this->file('rnr', '2026-12-01', '2026-12-03')->assertSessionHasErrors('leave_type');

        $this->credit('grant', ['days' => 10, 'effective_date' => '2026-11-20'])->assertSessionHasErrors('note');
        $this->credit('grant', ['days' => 10, 'effective_date' => '2026-11-20', 'note' => 'WPS patrol 1-20 Nov'])->assertSessionHas('success');

        $this->file('rnr', '2026-12-01', '2026-12-04')->assertSessionHasNoErrors();
        $id = DB::table('leave_requests')->value('id');
        $this->withSession($this->unit)->get('/unit/leave')->assertSee('RNR credits: 10 days')->assertSee('6 left after this');
        $this->withSession($this->unit)->post("/unit/leave/$id/review", ['decision' => 'approve']);
        $this->assertSame(6.0, $this->avail('rnr'));
    }

    public function test_solo_parent_days_are_yearly()
    {
        $this->file('solo_parent', '2026-11-23', '2026-11-29')->assertSessionHasNoErrors();
        $this->withSession($this->unit)->post('/unit/leave/' . DB::table('leave_requests')->value('id') . '/review', ['decision' => 'approve']);
        $this->assertSame(0.0, $this->avail('solo_parent'));
        $this->file('solo_parent', '2026-12-07', '2026-12-07')->assertSessionHasErrors('leave_type');

        Carbon::setTestNow(Carbon::create(2027, 1, 5, 9, 0, 0, LeaveCalendar::TZ));
        $this->assertSame(7.0, $this->avail('solo_parent'));
    }

    public function test_types_without_credits_are_not_limited_by_balances()
    {
        $this->file('sick', '2026-11-23', '2026-11-25')->assertSessionHasNoErrors();
        $this->file('emergency', '2026-11-26', '2026-11-26')->assertSessionHasNoErrors();
    }

    public function test_credit_changes_are_validated()
    {
        $this->credit('opening', ['days' => -1, 'effective_date' => '2026-11-01'])->assertSessionHasErrors('days');
        $this->credit('adjustment', ['pool' => 'annual', 'days' => 0, 'effective_date' => '2026-11-01', 'note' => 'x'])->assertSessionHasErrors('days');
        $this->credit('adjustment', ['pool' => 'nope', 'days' => 1, 'effective_date' => '2026-11-01', 'note' => 'x'])->assertSessionHasErrors('pool');
        $this->credit('opening', ['days' => 5, 'effective_date' => '2027-06-01'])->assertSessionHasErrors('effective_date');
        $this->assertSame(0, DB::table('leave_credit_entries')->count());
    }

    public function test_checkers_manage_credits_of_their_unit_only()
    {
        $this->withSession($this->unit)->get('/unit/leave/credits')->assertOk()->assertSee('Ana Reyes')->assertDontSee('Ben Cruz')
            ->assertSee('no opening balance yet');
        $this->withSession($this->unit)->get('/unit/leave/credits/' . $this->ana->id)->assertOk()->assertSee('Set opening balance');

        $this->withSession($this->otherUnit)->get('/unit/leave/credits/' . $this->ana->id)->assertForbidden();
        $this->withSession($this->otherUnit)->post('/unit/leave/credits/' . $this->ana->id, ['action' => 'grant', 'days' => 5, 'effective_date' => '2026-11-20', 'note' => 'x'])->assertForbidden();

        $this->withSession($this->m1)->get('/unit/leave/credits')->assertOk()->assertSee('Ana Reyes')->assertSee('Ben Cruz');
        $this->withSession($this->m1)->get('/unit/leave/credits?unit=NOCOP')->assertOk()->assertSee('Ben Cruz')->assertDontSee('Ana Reyes');
        $this->withSession($this->unit)->get('/unit/leave/credits?unit=NOCOP')->assertOk()->assertSee('Ana Reyes')->assertDontSee('Ben Cruz');   // own-unit checkers ignore it
        $this->withSession($this->m1)->post('/unit/leave/credits/' . $this->ana->id, ['action' => 'grant', 'days' => 5, 'effective_date' => '2026-11-20', 'note' => 'x'])->assertRedirect();
        $this->assertSame(5.0, $this->avail('rnr'));

        $this->flushSession();
        $this->actingAs($this->ana)->get('/unit/leave/credits')->assertRedirect();
    }

    public function test_a_new_opening_balance_replaces_the_old_one()
    {
        $this->credit('opening', ['days' => 10, 'effective_date' => '2026-08-01']);
        $this->credit('adjustment', ['pool' => 'annual', 'days' => 2, 'effective_date' => '2026-09-01', 'note' => 'x']);
        $this->credit('opening', ['days' => 20, 'effective_date' => '2026-11-15']);
        $this->assertSame(20.0, $this->avail('annual'));
    }
}
