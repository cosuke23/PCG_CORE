<?php

namespace Tests\Feature;

use App\Models\UnitUser;
use App\Models\User;
use App\Support\LeaveCalendar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Leave module (owner request 2026-11-20): members file leave on the calendar, the
 * unit checker of their unit (or an M1 checker) approves or declines it, approved
 * leave shows on the calendar of everyone in the unit; sensitive types show only
 * as "On leave" to others.
 */
class LeaveTest extends TestCase
{
    private array $unit = ['unit_user_id' => 1, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF'];
    private array $otherUnit = ['unit_user_id' => 3, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'NOCOP'];
    private array $m1 = ['unit_user_id' => 2, 'unit_role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'];
    private User $ana;
    private User $carl;
    private User $ben;

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
        $this->carl = User::forceCreate(['name' => 'Carl Santos', 'email' => 'c@x.test', 'password' => bcrypt('x'), 'rank' => 'PO3', 'serial_number' => 'SN-C', 'status' => 'Approved', 'mother_unit' => 'meplef']);
        $this->ben = User::forceCreate(['name' => 'Ben Cruz', 'email' => 'b@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-B', 'status' => 'Approved', 'mother_unit' => 'NOCOP']);
        DB::table('unit_users')->insert([
            ['full_name' => 'Unit Checker', 'email' => 'unit@x.test', 'password' => Hash::make('pw-unit-123'), 'role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF'],
            ['full_name' => 'M1 Checker', 'email' => 'm1@x.test', 'password' => Hash::make('pw-m1-1234'), 'role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'],
            ['full_name' => 'Other Checker', 'email' => 'nocop@x.test', 'password' => Hash::make('pw-nocop-12'), 'role' => UnitUser::ROLE_UNIT, 'unit_name' => 'NOCOP'],
        ]);
        $this->giveRnr();
    }

    /** RNR credits for the members who file RNR in these tests (credits are tested in LeaveCreditsTest). */
    private function giveRnr(): void
    {
        foreach ([$this->ana, $this->ben, $this->carl] as $u) {
            \App\Support\LeaveCredits::post($u->id, $u->serial_number, 'rnr', 'grant', 30, '2026-11-01', 'Patrol');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function file(User $u, array $over = [])
    {
        return $this->actingAs($u)->post('/leave', $over + [
            'leave_type' => 'rnr', 'start_date' => '2026-11-24', 'end_date' => '2026-11-27', 'reason' => 'After the patrol',
        ]);
    }

    public function test_the_migration_is_reversible_and_never_drops_filed_leave()
    {
        $m = require base_path('database/migrations/2026_11_20_000001_create_leave_requests_table.php');
        $this->file($this->ana);
        try {
            $m->down();
            $this->fail('down() must refuse while leave is filed');
        } catch (\RuntimeException $e) {
            $this->assertTrue(Schema::hasTable('leave_requests'));
        }
        DB::table('leave_requests')->delete();
        $m->down();
        $this->assertFalse(Schema::hasTable('leave_requests'));
        $m->up();
        $this->assertTrue(Schema::hasTable('leave_requests'));
    }

    public function test_a_member_files_leave_for_themselves()
    {
        $this->file($this->ana, ['serial_number' => 'SN-B', 'user_id' => $this->ben->id])
            ->assertRedirect(route('leave.index', ['month' => '2026-11']))->assertSessionHas('success');

        $row = DB::table('leave_requests')->first();
        $this->assertSame($this->ana->id, (int) $row->user_id);
        $this->assertSame('SN-A', $row->serial_number);
        $this->assertSame('pending', $row->status);
        $this->assertSame(4, (int) $row->days);

        $this->actingAs($this->ana)->get('/leave?month=2026-11')->assertOk()
            ->assertSee('1 leave request is waiting for your unit')
            ->assertSee('Rest and recreation (RNR)')
            ->assertSee('Waiting for approval');
    }

    public function test_dates_types_and_overlaps_are_checked()
    {
        $this->file($this->ana, ['end_date' => '2026-11-20'])->assertSessionHasErrors('end_date');
        $this->file($this->ana, ['start_date' => '2026-10-01', 'end_date' => '2026-10-02'])->assertSessionHasErrors('start_date');
        $this->file($this->ana, ['leave_type' => 'paternity', 'start_date' => '2026-12-01', 'end_date' => '2026-12-08'])->assertSessionHasErrors('end_date');
        $this->file($this->ana, ['leave_type' => 'made_up'])->assertSessionHasErrors('leave_type');
        $this->assertSame(0, DB::table('leave_requests')->count());

        $this->file($this->ana)->assertSessionHasNoErrors();
        $this->file($this->ana, ['start_date' => '2026-11-26', 'end_date' => '2026-11-30'])->assertSessionHasErrors('start_date');
        $this->assertSame(1, DB::table('leave_requests')->count());
    }

    public function test_a_member_cancels_only_their_own_leave()
    {
        $this->file($this->ana);
        $id = DB::table('leave_requests')->value('id');

        $this->actingAs($this->ben)->post("/leave/$id/cancel")->assertNotFound();
        $this->actingAs($this->ana)->post("/leave/$id/cancel")->assertSessionHas('success');
        $this->assertSame('cancelled', DB::table('leave_requests')->value('status'));
    }

    public function test_the_unit_checker_approves_leave_of_their_unit_only()
    {
        $this->file($this->ana);
        $this->file($this->ben);
        [$anaId, $benId] = DB::table('leave_requests')->orderBy('id')->pluck('id')->all();

        $this->withSession($this->unit)->get('/unit/leave?month=2026-11')->assertOk()
            ->assertSee('1 leave request is waiting for your approval')
            ->assertSee('Ana Reyes')->assertDontSee('Ben Cruz');

        $this->withSession($this->unit)->post("/unit/leave/$benId/review", ['decision' => 'approve'])->assertForbidden();
        $this->withSession($this->unit)->post("/unit/leave/$anaId/review", ['decision' => 'approve'])->assertSessionHas('success');

        $row = DB::table('leave_requests')->find($anaId);
        $this->assertSame('approved', $row->status);
        $this->assertSame(1, (int) $row->reviewed_by);
        $this->assertSame('pending', DB::table('leave_requests')->find($benId)->status);

        // a decided request cannot be decided again
        $this->withSession($this->unit)->post("/unit/leave/$anaId/review", ['decision' => 'decline', 'note' => 'x'])->assertSessionHas('error');
        $this->assertSame('approved', DB::table('leave_requests')->find($anaId)->status);
    }

    public function test_declining_needs_a_note_that_the_member_sees()
    {
        $this->file($this->ana);
        $id = DB::table('leave_requests')->value('id');

        $this->withSession($this->unit)->post("/unit/leave/$id/review", ['decision' => 'decline'])->assertSessionHasErrors('note');
        $this->withSession($this->unit)->post("/unit/leave/$id/review", ['decision' => 'decline', 'note' => 'The ship deploys on the 25th'])->assertSessionHas('success');

        $this->assertSame('declined', DB::table('leave_requests')->value('status'));
        $this->actingAs($this->ana)->get('/leave?month=2026-11')->assertSee('Declined')->assertSee('The ship deploys on the 25th');
    }

    public function test_m1_checkers_decide_leave_of_any_unit()
    {
        $this->file($this->ben);
        $id = DB::table('leave_requests')->value('id');

        $this->withSession($this->m1)->get('/unit/leave')->assertOk()->assertSee('Ben Cruz');
        $this->withSession($this->m1)->post("/unit/leave/$id/review", ['decision' => 'approve'])->assertSessionHas('success');
        $this->assertSame('approved', DB::table('leave_requests')->value('status'));
    }

    public function test_approved_leave_shows_on_the_unit_calendar_with_sensitive_types_hidden()
    {
        $this->file($this->ana, ['leave_type' => 'sick', 'start_date' => '2026-11-23', 'end_date' => '2026-11-24']);
        $this->file($this->ana, ['leave_type' => 'rnr', 'start_date' => '2026-11-25', 'end_date' => '2026-11-28']);
        foreach (DB::table('leave_requests')->pluck('id') as $id) {
            $this->withSession($this->unit)->post("/unit/leave/$id/review", ['decision' => 'approve']);
        }

        // Carl is in the same unit (case differs): sees Ana, the RNR, and only "On leave" for the sick leave
        $this->actingAs($this->carl)->get('/leave?month=2026-11')->assertOk()
            ->assertSee('Ana Reyes')->assertSee('Rest and recreation (RNR)')->assertSee('On leave')->assertDontSee('Sick leave</span>', false);

        // Ana sees her own leave with the real type
        $this->actingAs($this->ana)->get('/leave?month=2026-11')->assertSee('Sick leave');

        // Ben is in another unit: nobody from MEPLEF on his calendar
        $this->actingAs($this->ben)->get('/leave?month=2026-11')->assertOk()->assertDontSee('Ana Reyes');

        // the unit checker sees the real types
        $this->withSession($this->unit)->get('/unit/leave?month=2026-11')->assertSee('Sick leave');
    }

    public function test_guests_and_members_cannot_open_the_approval_page()
    {
        $this->get('/leave')->assertRedirect(route('login'));
        $this->post('/leave')->assertRedirect(route('login'));
        $this->actingAs($this->ana)->get('/unit/leave')->assertRedirect();
        $this->assertNotSame(200, $this->actingAs($this->ana)->get('/unit/leave')->status());
    }

    public function test_the_dashboard_shows_leave_at_a_glance()
    {
        (require base_path('database/migrations/2026_11_09_000002_add_soi_submitted_at_to_users.php'))->up();
        Schema::create('rejected_documents', function (Blueprint $t) { $t->id(); $t->string('serial_number'); $t->string('doc_type'); $t->text('comment')->nullable(); $t->string('status')->nullable(); });
        Schema::create('training_courses', function (Blueprint $t) { $t->id(); $t->string('course_name')->nullable(); $t->string('status')->nullable(); });
        Schema::create('course_students', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('course_id')->nullable(); $t->string('serial_number')->nullable(); $t->date('date_attended')->nullable(); $t->date('date_ended')->nullable(); });
        Schema::create('personnel_data', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('personnel_id')->nullable(); $t->string('status')->nullable(); });

        $this->actingAs($this->ana)->get('/dashboard')->assertOk()->assertSee('No leave filed')->assertSee('RNR 30 days available');
        $this->file($this->ana);
        $this->get('/dashboard')->assertSee('1 request waiting')->assertSee(route('leave.index'));
        $this->withSession($this->unit)->post('/unit/leave/' . DB::table('leave_requests')->value('id') . '/review', ['decision' => 'approve']);
        $this->actingAs($this->ana)->get('/dashboard')->assertSee('Next: Nov 24–27, 2026')->assertSee('RNR 26 days available');
    }

    public function test_calendar_colours_and_initials_tell_people_apart()
    {
        $this->assertSame('AR', LeaveCalendar::initials('Ana Reyes'));
        $this->assertSame('JC', LeaveCalendar::initials('Juan Dela Cruz Jr.'));
        $this->assertSame(LeaveCalendar::colour('SN-A'), LeaveCalendar::colour(' sn-a '));
        $this->assertGreaterThanOrEqual(0, LeaveCalendar::colour('SN-X'));
        $this->assertLessThan(LeaveCalendar::COLOURS, LeaveCalendar::colour('SN-X'));
        $this->assertCount(5, LeaveCalendar::grid(LeaveCalendar::month('2026-11')));  // Nov 2026: Sun 1 .. Mon 30 + Dec 1-5
        $this->assertSame('2026-11', LeaveCalendar::month('2026-13')->format('Y-m'));
    }
}
