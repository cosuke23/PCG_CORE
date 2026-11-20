<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Support\LeaveCalendar;
use App\Support\LeaveCredits;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Leave in the admin console (owner request 2026-11-20): leave.view sees requests,
 * the calendar and credits of the admin's scope (OrgScope); leave.approve decides
 * requests and leave.credits changes credits, with the unit checkers' rules.
 */
class AdminLeaveTest extends TestCase
{
    use RefreshDatabase;

    private array $hr;
    private array $auditor;
    private array $super;
    private int $ana;
    private int $ben;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 9, 0, 0, LeaveCalendar::TZ));

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->nullable();
            $t->string('serial_number')->nullable(); $t->string('rank')->nullable(); $t->string('status')->nullable();
            $t->string('mother_unit')->nullable(); $t->string('unit_assignment')->nullable(); $t->timestamps();
        });

        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        foreach ([['SN-IN', 'Ana Inside', $mepcom], ['SN-OUT', 'Ben Outside', $mssc]] as [$serial, $name, $unit]) {
            $p = Personnel::create(['serial_number' => $serial, 'full_name' => $name]);
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => '2024-01-01']);
        }
        $this->ana = DB::table('users')->insertGetId(['name' => 'Ana Inside', 'email' => 'ana@x.test', 'serial_number' => 'sn-in ', 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        $this->ben = DB::table('users')->insertGetId(['name' => 'Ben Outside', 'email' => 'ben@x.test', 'serial_number' => 'SN-OUT', 'status' => 'Approved', 'mother_unit' => 'MSSC-HQ']);

        $hr = AdminUser::create(['name' => 'HR Admin', 'email' => 'hr@x.test', 'password' => 'x', 'role' => 'hr_admin', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $aud = AdminUser::create(['name' => 'Auditor', 'email' => 'aud@x.test', 'password' => 'x', 'role' => 'auditor', 'is_active' => true]);
        $sup = AdminUser::create(['name' => 'Super', 'email' => 'su@x.test', 'password' => 'x', 'role' => 'super_admin', 'is_active' => true]);
        $this->hr = ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_user_id' => $hr->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'hr@x.test', 'admin_name' => 'HR Admin'];
        $this->auditor = ['admin_logged_in' => true, 'admin_role' => 'auditor', 'admin_user_id' => $aud->id, 'admin_email' => 'aud@x.test', 'admin_name' => 'Auditor'];
        $this->super = ['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $sup->id, 'admin_email' => 'su@x.test', 'admin_name' => 'Super'];

        foreach ([[$this->ana, 'sn-in '], [$this->ben, 'SN-OUT']] as [$uid, $serial]) {
            LeaveCredits::post($uid, $serial, 'rnr', 'grant', 10, '2026-11-01', 'Patrol');
            DB::table('leave_requests')->insert(['user_id' => $uid, 'serial_number' => $serial, 'leave_type' => 'rnr',
                'start_date' => '2026-11-24', 'end_date' => '2026-11-27', 'days' => 4, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function leaveOf(int $userId): object
    {
        return DB::table('leave_requests')->where('user_id', $userId)->first();
    }

    public function test_a_scoped_hr_admin_decides_leave_of_their_command_only()
    {
        $this->withSession($this->hr)->get('/admin/leave')->assertOk()
            ->assertSee('1 leave request is waiting for approval')
            ->assertSee('Ana Inside')->assertDontSee('Ben Outside')
            ->assertSee('RNR credits: 10 days');

        $this->post('/admin/leave/' . $this->leaveOf($this->ben)->id . '/review', ['decision' => 'approve'])->assertForbidden();
        $this->post('/admin/leave/' . $this->leaveOf($this->ana)->id . '/review', ['decision' => 'approve'])->assertSessionHas('success');

        $row = $this->leaveOf($this->ana);
        $this->assertSame('approved', $row->status);
        $this->assertSame('HR Admin (admin)', $row->reviewer_name);
        $this->assertNull($row->reviewed_by);
        $this->assertSame(6.0, LeaveCredits::balances($this->ana)['rnr']['available']);
        $this->assertSame('pending', $this->leaveOf($this->ben)->status);

        // approved leave is on the calendar of the admin's scope
        $this->get('/admin/leave?month=2026-11&status=approved')->assertSee('Rest and recreation (RNR)')->assertSee('Approved by HR Admin (admin)');
    }

    public function test_a_scoped_hr_admin_manages_credits_of_their_command_only()
    {
        $this->withSession($this->hr)->get('/admin/leave/credits')->assertOk()->assertSee('Ana Inside')->assertDontSee('Ben Outside');
        $this->get('/admin/leave/credits/' . $this->ben)->assertForbidden();
        $this->post('/admin/leave/credits/' . $this->ben, ['action' => 'grant', 'days' => 5, 'effective_date' => '2026-11-20', 'note' => 'x'])->assertForbidden();

        $this->post('/admin/leave/credits/' . $this->ana, ['action' => 'opening', 'days' => 12, 'effective_date' => '2026-11-01', 'note' => 'Opening balance'])
            ->assertRedirect('/admin/leave/credits/' . $this->ana);
        $this->assertSame(12.0, LeaveCredits::balances($this->ana)['annual']['available']);
        $this->get('/admin/leave/credits/' . $this->ana)->assertOk()->assertSee('12 days of ordinary leave available')->assertSee('by HR Admin');
    }

    public function test_auditors_view_but_cannot_decide_or_change_credits()
    {
        $this->withSession($this->auditor)->get('/admin/leave')->assertOk()->assertSee('Ana Inside')->assertSee('Ben Outside')
            ->assertSee('view only for your role')->assertDontSee('name="decision"', false);
        $this->get('/admin/leave/credits/' . $this->ana)->assertOk()->assertSee('can view credits but not change them');

        $this->post('/admin/leave/' . $this->leaveOf($this->ana)->id . '/review', ['decision' => 'approve'])->assertForbidden();
        $this->post('/admin/leave/credits/' . $this->ana, ['action' => 'grant', 'days' => 5, 'effective_date' => '2026-11-20', 'note' => 'x'])->assertForbidden();
        $this->assertSame('pending', $this->leaveOf($this->ana)->status);
    }

    public function test_the_super_admin_sees_every_unit_and_declining_needs_a_note()
    {
        $this->withSession($this->super)->get('/admin/leave?unit=MSSC-HQ')->assertOk()->assertSee('Ben Outside')->assertDontSee('Ana Inside');
        $id = $this->leaveOf($this->ben)->id;
        $this->post("/admin/leave/$id/review", ['decision' => 'decline'])->assertSessionHasErrors('note');
        $this->post("/admin/leave/$id/review", ['decision' => 'decline', 'note' => 'Ship deploys'])->assertSessionHas('success');
        $this->assertSame('declined', $this->leaveOf($this->ben)->status);
        $this->assertSame(10.0, LeaveCredits::balances($this->ben)['rnr']['available']);
    }

    public function test_other_roles_do_not_see_leave()
    {
        $lms = AdminUser::create(['name' => 'LMS', 'email' => 'lms@x.test', 'password' => 'x', 'role' => 'lms_admin', 'is_active' => true]);
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'lms_admin', 'admin_user_id' => $lms->id, 'admin_email' => 'lms@x.test'])
            ->get('/admin/leave')->assertForbidden();
    }
}
