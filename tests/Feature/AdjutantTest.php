<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Adjutant module (owner decision 2026-11-08): records SOI 1.a Coast Guard
 * awards and decorations and 3.a Coast Guard schooling, limited to the
 * Adjutant's command/unit.
 */
class AdjutantTest extends TestCase
{
    use RefreshDatabase;

    private array $adjutant;
    private int $inside;
    private int $outside;
    private int $adjutantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->nullable();
            $t->string('serial_number')->nullable(); $t->string('rank')->nullable(); $t->string('status')->nullable();
            $t->string('mother_unit')->nullable(); $t->string('unit_assignment')->nullable(); $t->timestamps();
        });
        Schema::create('reinlist_cg_awards_decorations', function (Blueprint $t) {
            $t->id(); $t->string('serial_number')->nullable(); $t->unsignedBigInteger('applicant_id')->nullable();
            $t->string('type')->nullable(); $t->string('authority')->nullable(); $t->unsignedBigInteger('added_by')->nullable(); $t->timestamps();
        });
        Schema::create('reinlist_trainings', function (Blueprint $t) {
            $t->id(); $t->string('personnel_id')->nullable(); $t->string('serial_number')->nullable(); $t->string('category')->nullable();
            $t->string('course')->nullable(); $t->string('type')->nullable(); $t->string('authority')->nullable(); $t->string('school')->nullable();
            $t->string('period')->nullable(); $t->text('remarks')->nullable(); $t->unsignedBigInteger('added_by')->nullable(); $t->timestamps();
        });
        Schema::create('reinlist_personal_info', function (Blueprint $t) {
            $t->id(); $t->string('personnel_id')->nullable(); $t->string('serial_number');
        });

        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        foreach ([['SN-IN', 'Ana Inside', $mepcom], ['SN-OUT', 'Ben Outside', $mssc]] as [$serial, $name, $unit]) {
            $p = Personnel::create(['serial_number' => $serial, 'full_name' => $name]);
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => '2024-01-01']);
        }
        $this->inside = DB::table('users')->insertGetId(['name' => 'Ana Inside', 'email' => 'ana@x.test', 'serial_number' => 'sn-in ', 'status' => 'Approved']);
        $this->outside = DB::table('users')->insertGetId(['name' => 'Ben Outside', 'email' => 'ben@x.test', 'serial_number' => 'SN-OUT', 'status' => 'approved']);
        DB::table('users')->insert(['name' => 'Cy Pending', 'email' => 'cy@x.test', 'serial_number' => 'SN-IN', 'status' => 'Pending']);
        DB::table('reinlist_personal_info')->insert(['personnel_id' => 'MEPCOM-SN-IN', 'serial_number' => 'sn-in ']);

        $adj = \App\Models\AdminUser::create(['name' => 'Adjutant', 'email' => 'adj@x.test', 'password' => 'x', 'role' => 'adjutant', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $this->adjutantId = $adj->id;
        $this->adjutant = ['admin_logged_in' => true, 'admin_role' => 'adjutant', 'admin_user_id' => $adj->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'adj@x.test'];
    }

    public function test_adjutant_sees_and_records_only_its_command()
    {
        $this->withSession($this->adjutant)->get('/admin/adjutant')->assertOk()
            ->assertSee('Ana Inside')->assertDontSee('Ben Outside')->assertDontSee('Cy Pending');
        $this->get('/admin/adjutant/' . $this->outside)->assertNotFound();
        $this->post('/admin/adjutant/' . $this->outside . '/awards', ['type' => 'Coast Guard Merit Medal', 'authority' => 'GO 1'])->assertNotFound();
        $this->assertSame(0, DB::table('reinlist_cg_awards_decorations')->count());

        $this->get('/admin/adjutant/' . $this->inside)->assertOk()->assertSee('Coast Guard Merit Medal'); // the shared award list

        $this->post('/admin/adjutant/' . $this->inside . '/awards', ['type' => 'Coast Guard Merit Medal', 'authority' => 'GO 12 s. 2026'])
            ->assertRedirect('/admin/adjutant/' . $this->inside);
        $award = DB::table('reinlist_cg_awards_decorations')->first();
        $this->assertSame('sn-in ', $award->serial_number);
        $this->assertSame($this->inside, (int) $award->applicant_id);
        $this->assertSame($this->adjutantId, (int) $award->added_by);

        $this->post('/admin/adjutant/' . $this->inside . '/trainings', ['type' => 'Advance Training', 'course' => 'Officer Advance Course', 'period' => '2026'])
            ->assertRedirect();
        $tr = DB::table('reinlist_trainings')->first();
        $this->assertSame('coastguard', $tr->category);
        $this->assertSame('MEPCOM-SN-IN', $tr->personnel_id);
        $this->assertSame($this->adjutantId, (int) $tr->added_by);

        $this->post('/admin/adjutant/' . $this->inside . '/trainings', ['type' => 'Made-up type', 'course' => 'X'])->assertSessionHasErrors('type');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'adjutant.award.added']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'adjutant.training.added']);
    }

    public function test_only_adjutant_entries_can_be_removed()
    {
        $old = DB::table('reinlist_cg_awards_decorations')->insertGetId(['serial_number' => 'sn-in ', 'type' => 'Self-entered', 'authority' => 'x']);
        $mine = DB::table('reinlist_cg_awards_decorations')->insertGetId(['serial_number' => 'sn-in ', 'type' => 'By adjutant', 'authority' => 'y', 'added_by' => $this->adjutantId]);
        $theirs = DB::table('reinlist_trainings')->insertGetId(['serial_number' => 'SN-OUT', 'category' => 'coastguard', 'course' => 'C', 'added_by' => 5]);

        $this->withSession($this->adjutant)->post("/admin/adjutant/{$this->inside}/awards/$old/delete")->assertNotFound();
        $this->post("/admin/adjutant/{$this->inside}/trainings/$theirs/delete")->assertNotFound(); // another member's row
        $this->post("/admin/adjutant/{$this->inside}/awards/$mine/delete")->assertRedirect();

        $this->assertSame(['Self-entered'], DB::table('reinlist_cg_awards_decorations')->pluck('type')->all());
        $this->assertSame(1, DB::table('reinlist_trainings')->count());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'adjutant.award.removed']);
    }

    public function test_other_roles_are_refused_and_the_sign_in_page_exists()
    {
        foreach (['hr_admin', 'records_approver', 'reenlistment_admin', 'lms_admin', 'auditor'] as $role) {
            $this->flushSession();
            $this->withSession(['admin_logged_in' => true, 'admin_role' => $role])->get('/admin/adjutant')->assertForbidden();
        }
        // and the Adjutant sees nothing of the HR modules
        $this->flushSession();
        $this->withSession($this->adjutant)->get('/admin/personnel')->assertForbidden();
        $this->get('/admin/hrmis')->assertForbidden();

        $this->flushSession();
        $this->get('/adjutant/login')->assertOk()->assertSee('Adjutant')->assertSee('one-time code');
        $this->assertSame('admin.adjutant.index', \App\Http\Controllers\AdminLoginController::homeRoute('adjutant'));
        $this->assertNotContains('adjutant', config('pcg.admin.password_only_roles')); // keeps the OTP step
    }

    public function test_member_save_keeps_coast_guard_entries_and_cannot_add_them()
    {
        Schema::create('reinlist_academic', function (Blueprint $t) {
            $t->id(); $t->string('personnel_id')->nullable(); $t->string('serial_number')->nullable(); $t->string('level')->nullable();
            $t->string('course')->nullable(); $t->string('school')->nullable(); $t->string('year_start')->nullable(); $t->string('year_end')->nullable();
            $t->text('remarks')->nullable(); $t->timestamps();
        });
        DB::table('reinlist_trainings')->insert([
            ['serial_number' => 'sn-in ', 'category' => 'coastguard', 'course' => 'By adjutant', 'added_by' => $this->adjutantId],
            ['serial_number' => 'sn-in ', 'category' => 'coastguard', 'course' => 'Old self-entry', 'added_by' => null],
            ['serial_number' => 'sn-in ', 'category' => 'others_local', 'course' => 'Old local', 'added_by' => null],
        ]);
        DB::table('users')->where('id', $this->inside)->update(['password' => bcrypt('secret-pass-1')]);
        $member = \App\Models\User::find($this->inside);

        $this->actingAs($member)->postJson('/temp-save/step4', ['trainings' => [
            'coastguard' => [['course' => 'Sneaked', 'type' => 'Advance Training']],
            'others_local' => [['course' => 'New local seminar', 'school' => 'X']],
        ]])->assertOk();

        $this->assertEqualsCanonicalizing(['By adjutant', 'Old self-entry', 'New local seminar'], DB::table('reinlist_trainings')->pluck('course')->all());
    }
}
