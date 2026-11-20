<?php

namespace Tests\Feature;

use App\Mail\LeaveDecided;
use App\Mail\LeaveWaiting;
use App\Models\UnitUser;
use App\Models\User;
use App\Support\LeaveCalendar;
use App\Support\LeaveCredits;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Leave follow-ups (owner request 2026-11-20): rules from config (working days,
 * limits, accrual), opening balances from a CSV, decision emails and reminders,
 * the printable leave form, manpower readiness, and the compressed 3D model.
 */
class LeaveExtrasTest extends TestCase
{
    private array $unit = ['unit_user_id' => 1, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF', 'unit_full_name' => 'Unit Checker'];
    private array $m1 = ['unit_user_id' => 2, 'unit_role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'];
    private array $otherUnit = ['unit_user_id' => 3, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'NOCOP'];
    private User $ana;
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
        Schema::create('training_courses', function (Blueprint $t) { $t->id(); $t->string('course_name')->nullable(); });
        Schema::create('course_students', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('course_id')->nullable(); $t->string('serial_number')->nullable();
            $t->date('date_attended')->nullable(); $t->date('date_ended')->nullable();
        });
        (require base_path('database/migrations/2026_11_20_000001_create_leave_requests_table.php'))->up();
        (require base_path('database/migrations/2026_11_20_000002_create_leave_credit_entries_table.php'))->up();

        $this->ana = User::forceCreate(['name' => 'Ana Reyes', 'email' => 'ana@x.test', 'password' => bcrypt('x'), 'rank' => 'SN2', 'serial_number' => 'SN-A', 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        User::forceCreate(['name' => 'Carl Santos', 'email' => 'carl@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-C', 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        $this->ben = User::forceCreate(['name' => 'Ben Cruz', 'email' => 'ben@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-B', 'status' => 'Approved', 'mother_unit' => 'NOCOP']);
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

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cards.csv', $content);
    }

    // ---------------------------------------------------------------- rules from config

    public function test_working_days_skip_weekends_and_holidays()
    {
        config(['pcg.leave.count' => 'working', 'pcg.leave.holidays' => ['2026-11-30']]);
        // Fri 27 .. Tue Dec 1: Fri, Mon (holiday), Tue = 2 working days
        $this->assertSame(2, LeaveCalendar::countDays('2026-11-27', '2026-12-01'));
        config(['pcg.leave.count' => 'calendar']);
        $this->assertSame(5, LeaveCalendar::countDays('2026-11-27', '2026-12-01'));

        config(['pcg.leave.count' => 'working']);
        $this->actingAs($this->ana)->post('/leave', ['leave_type' => 'sick', 'start_date' => '2026-11-28', 'end_date' => '2026-11-29'])
            ->assertSessionHasErrors('end_date');       // a weekend only
        $this->post('/leave', ['leave_type' => 'sick', 'start_date' => '2026-11-27', 'end_date' => '2026-12-01'])->assertSessionHasNoErrors();
        $this->assertSame(2, (int) DB::table('leave_requests')->value('days'));
    }

    public function test_limits_and_accrual_come_from_config()
    {
        config(['pcg.leave.max_days' => ['sick' => 2], 'pcg.leave.monthly_credit' => 1.25, 'pcg.leave.per_year.solo_parent' => 5]);
        $this->actingAs($this->ana)->post('/leave', ['leave_type' => 'sick', 'start_date' => '2026-11-23', 'end_date' => '2026-11-25'])
            ->assertSessionHasErrors('end_date');
        LeaveCredits::post($this->ana->id, 'SN-A', 'annual', 'opening', 10, '2026-09-15');
        $b = LeaveCredits::balances($this->ana->id);
        $this->assertSame(12.5, $b['annual']['balance']);         // + 2 months x 1.25
        $this->assertSame(5.0, $b['solo_parent']['balance']);
        $this->assertStringContainsString('1.25 days are added', $b['annual']['rule']);
    }

    // ---------------------------------------------------------------- CSV import

    public function test_opening_balances_import_from_a_csv_after_a_preview()
    {
        $file = "serial_number,name,unit,current_balance,days,as_of\nSN-Q,Blank,MEPLEF,not set,,\nSN-A,Ana,MEPLEF,,12.5,2026-11-01\nSN-C,Carl,MEPLEF,,8,11/15/2026\nSN-B,Ben,NOCOP,,5,2026-11-01\nSN-X,Nobody,,,3,2026-11-01\nSN-A,Again,,,4,2026-11-01\nSN-C2,Bad,,,x,2026-11-01\n";
        $this->withSession($this->unit)->post('/unit/leave/credits/import', ['file' => $this->csv($file)])->assertOk()
            ->assertSee('2 rows are ready to import, 4 need fixing (1 left blank)')
            ->assertSee('This member is not in your unit or command.')
            ->assertSee('No member with this serial number.')
            ->assertSee('This serial number is already on line 3.')
            ->assertSee('Days must be a number from 0 to 365.');
        $this->assertSame(0, DB::table('leave_credit_entries')->count());       // nothing before confirming

        $token = session('leave_import')['token'];
        $this->post('/unit/leave/credits/import/confirm', ['token' => $token])->assertRedirect(route('unit.leave.credits'))
            ->assertSessionHas('success', '2 opening balances imported.');
        $this->assertSame(12.5, LeaveCredits::balances($this->ana->id)['annual']['balance']);
        $this->assertSame(2, DB::table('leave_credit_entries')->where('kind', 'opening')->count());

        // the same preview cannot be imported twice
        $this->post('/unit/leave/credits/import/confirm', ['token' => $token])->assertSessionHas('error');
        $this->assertSame(2, DB::table('leave_credit_entries')->count());
    }

    public function test_the_import_template_lists_the_checkers_members()
    {
        User::forceCreate(['name' => '=HYPERLINK("x")', 'email' => 'h@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-H', 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        $res = $this->withSession($this->unit)->get('/unit/leave/credits/import/template')->assertOk();
        $body = $res->getContent();
        $this->assertStringContainsString('serial_number,name,unit,current_balance,days,as_of', $body);
        $this->assertStringContainsString('SN-A', $body);
        $this->assertStringNotContainsString('SN-B', $body);
        $this->assertStringContainsString("'=HYPERLINK", $body);      // formula guard
    }

    // ---------------------------------------------------------------- emails

    public function test_members_are_emailed_when_their_leave_is_decided()
    {
        Mail::fake();
        DB::table('leave_requests')->insert(['user_id' => $this->ana->id, 'serial_number' => 'SN-A', 'leave_type' => 'sick',
            'start_date' => '2026-11-23', 'end_date' => '2026-11-24', 'days' => 2, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('leave_requests')->value('id');
        $this->withSession($this->unit)->post("/unit/leave/$id/review", ['decision' => 'decline', 'note' => 'Ship deploys'])->assertSessionHas('success');

        Mail::assertSent(LeaveDecided::class, fn ($m) => $m->hasTo('ana@x.test') && !$m->approved && $m->note === 'Ship deploys');

        config(['pcg.leave.notify_members' => false]);
        DB::table('leave_requests')->insert(['user_id' => $this->ana->id, 'serial_number' => 'SN-A', 'leave_type' => 'sick',
            'start_date' => '2026-11-26', 'end_date' => '2026-11-26', 'days' => 1, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->withSession($this->unit)->post('/unit/leave/' . DB::table('leave_requests')->max('id') . '/review', ['decision' => 'approve']);
        Mail::assertSent(LeaveDecided::class, 1);
    }

    public function test_checkers_get_a_daily_reminder_of_leave_waiting_too_long()
    {
        Mail::fake();
        foreach ([[$this->ana, 'SN-A', 3], [$this->ben, 'SN-B', 5], [$this->ana, 'SN-A', 0]] as [$u, $s, $ago]) {
            DB::table('leave_requests')->insert(['user_id' => $u->id, 'serial_number' => $s, 'leave_type' => 'ordinary',
                'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'days' => 2, 'status' => 'pending',
                'created_at' => now()->subDays($ago), 'updated_at' => now()]);
        }
        $this->artisan('pcg:leave-reminders', ['--dry-run' => true])->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('pcg:leave-reminders')->assertSuccessful();
        Mail::assertSent(LeaveWaiting::class, fn ($m) => $m->hasTo('unit@x.test') && count($m->requests) === 1);   // only the old one
        Mail::assertSent(LeaveWaiting::class, fn ($m) => $m->hasTo('nocop@x.test') && count($m->requests) === 1);
        Mail::assertSent(LeaveWaiting::class, fn ($m) => $m->hasTo('m1@x.test') && count($m->requests) === 2);
        Mail::assertSent(LeaveWaiting::class, 3);
    }

    // ---------------------------------------------------------------- print and readiness

    public function test_the_leave_form_prints_for_the_member_and_their_checkers_only()
    {
        DB::table('leave_requests')->insert(['user_id' => $this->ana->id, 'serial_number' => 'SN-A', 'leave_type' => 'rnr',
            'start_date' => '2026-11-23', 'end_date' => '2026-11-27', 'days' => 5, 'status' => 'approved', 'reviewer_name' => 'Unit Checker',
            'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('leave_requests')->value('id');

        $this->actingAs($this->ana)->get("/leave/$id/print")->assertOk()
            ->assertSee('Application for leave')->assertSee('LV-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT))
            ->assertSee('Rest and recreation (RNR)')->assertSee('Unit Checker')->assertSee('pcg.print', false);
        $this->actingAs($this->ben)->get("/leave/$id/print")->assertNotFound();
        $this->withSession($this->unit)->get("/unit/leave/$id/print")->assertOk();
        $this->withSession($this->otherUnit)->get("/unit/leave/$id/print")->assertForbidden();
        $this->withSession($this->m1)->get("/unit/leave/$id/print")->assertOk();
    }

    public function test_readiness_counts_strength_leave_and_training()
    {
        DB::table('leave_requests')->insert(['user_id' => $this->ana->id, 'serial_number' => 'SN-A', 'leave_type' => 'rnr',
            'start_date' => '2026-11-19', 'end_date' => '2026-11-22', 'days' => 4, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('training_courses')->insert(['id' => 1, 'course_name' => 'Oil Spill Response']);
        DB::table('course_students')->insert(['course_id' => 1, 'serial_number' => 'sn-c', 'date_attended' => '2026-11-16', 'date_ended' => '2026-11-27']);

        $this->withSession($this->unit)->get('/unit/leave/readiness')->assertOk()
            ->assertSee('0 of 2 members of MEPLEF are available today')
            ->assertSee('Oil Spill Response')->assertSee('Rest and recreation (RNR)')->assertDontSee('Ben Cruz');
        $this->withSession($this->unit)->get('/unit/leave/readiness?date=2026-11-28')->assertSee('2 of 2 members of MEPLEF are available on November 28');
        $this->withSession($this->m1)->get('/unit/leave/readiness')->assertOk()->assertSee('1 of 3 members are available today')->assertSee('NOCOP');
    }

    // ---------------------------------------------------------------- monthly summary and CSV

    private function reportLeave(): void
    {
        $row = fn ($u, $serial, $type, $from, $to, $days, $status, $note = null) => ['user_id' => $u->id, 'serial_number' => $serial, 'leave_type' => $type,
            'start_date' => $from, 'end_date' => $to, 'days' => $days, 'status' => $status, 'reason' => 'private reason', 'review_note' => $note,
            'created_at' => now(), 'updated_at' => now()];
        DB::table('leave_requests')->insert([
            $row($this->ana, 'SN-A', 'rnr', '2026-11-28', '2026-12-03', 6, 'approved', '=HYPERLINK("x")'),
            $row($this->ana, 'SN-A', 'vacation', '2026-11-25', '2026-11-26', 2, 'pending'),
            $row($this->ben, 'SN-B', 'rnr', '2026-11-10', '2026-11-11', 2, 'approved'),
            $row($this->ben, 'SN-B', 'rnr', '2026-10-01', '2026-10-02', 2, 'approved'),
        ]);
    }

    public function test_the_monthly_summary_counts_days_inside_the_month_for_the_checkers_scope()
    {
        $this->reportLeave();
        $this->withSession($this->unit)->get('/unit/leave/summary?month=2026-11')->assertOk()
            ->assertSee('1 member of MEPLEF took 3 leave days in November 2026')   // Nov 28-30 of the RNR
            ->assertDontSee('NOCOP');
        $this->withSession($this->m1)->get('/unit/leave/summary?month=2026-11')->assertOk()
            ->assertSee('2 members took 5 leave days in November 2026')->assertSee('NOCOP');
        $this->withSession($this->unit)->get('/unit/leave/summary?month=2026-12')->assertSee('took 3 leave days in December 2026');
    }

    public function test_the_csv_export_has_the_scope_and_no_reasons_and_is_audited()
    {
        $this->reportLeave();
        $res = $this->withSession($this->unit)->get('/unit/leave/export?from=2026-11-01&to=2026-11-30&unit=NOCOP');
        $res->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Ana Reyes', $csv);
        $this->assertStringNotContainsString('Ben Cruz', $csv);               // own-unit checker: the unit filter is ignored
        $this->assertStringNotContainsString('private reason', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);               // formula neutralised
        $this->assertStringContainsString('2026-11-28,2026-12-03,6,3,Approved', $csv);

        $m1 = $this->withSession($this->m1)->get('/unit/leave/export?from=2026-11-01&to=2026-11-30&status=approved')->streamedContent();
        $this->assertStringContainsString('Ben Cruz', $m1);
        $this->assertStringNotContainsString('Waiting', $m1);
        $this->assertSame(2, substr_count($m1, ',Approved,'));
    }

    // ---------------------------------------------------------------- 3D model

    public function test_the_ship_model_is_sent_compressed_and_cached()
    {
        $plain = file_get_contents(public_path('models/brp-gabriela-silang.glb'));
        $res = $this->get('/3d/ship-model', ['Accept-Encoding' => 'gzip, br']);
        $res->assertOk()->assertHeader('Content-Encoding', 'gzip')->assertHeader('Content-Type', 'model/gltf-binary');
        $this->assertSame($plain, gzdecode($res->getContent()));
        $this->assertLessThan(strlen($plain) / 2, strlen($res->getContent()));

        $this->get('/3d/ship-model', ['If-None-Match' => $res->headers->get('ETag')])->assertStatus(304);
        $this->get('/3d/ship-model', ['Accept-Encoding' => 'identity'])->assertOk()->assertHeaderMissing('Content-Encoding');
    }
}
