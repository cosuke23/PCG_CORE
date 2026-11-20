<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** One unit per member: the mismatch report and its two fixes (owner request 2026-11-20). */
class UnitMismatchTest extends TestCase
{
    use RefreshDatabase;

    private array $super;
    private array $auditor;
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable(); $t->timestamps();
        });
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        $meplef = OrgUnit::create(['parent_id' => $mepcom->id, 'code' => 'MEPLEF', 'name' => 'MEP Law Enforcement Force', 'type' => 'unit', 'is_active' => true]);
        foreach ([['Ana', 'S-A', 'MEPLEF', $mepcom], ['Ben', 'S-B', 'MSSC-HQ', $mssc], ['Cara', 'S-C', 'MEPCOM', $meplef]] as [$name, $serial, $mother, $unit]) {
            $this->ids[$name] = DB::table('users')->insertGetId(['name' => $name, 'email' => strtolower($name) . '@x.test', 'serial_number' => $serial, 'status' => 'Approved', 'mother_unit' => $mother]);
            $p = Personnel::create(['serial_number' => $serial, 'full_name' => $name]);
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => '2024-01-01']);
        }
        $sup = AdminUser::create(['name' => 'Super', 'email' => 'su@x.test', 'password' => 'x', 'role' => 'super_admin', 'is_active' => true]);
        $aud = AdminUser::create(['name' => 'Auditor', 'email' => 'au@x.test', 'password' => 'x', 'role' => 'auditor', 'is_active' => true]);
        $this->super = ['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $sup->id, 'admin_email' => 'su@x.test', 'admin_name' => 'Super'];
        $this->auditor = ['admin_logged_in' => true, 'admin_role' => 'auditor', 'admin_user_id' => $aud->id, 'admin_email' => 'au@x.test', 'admin_name' => 'Auditor'];
    }

    public function test_the_report_finds_disagreements_and_parents_count_as_agreement()
    {
        $this->withSession($this->super)->get('/admin/unit-check')->assertOk()
            ->assertSee("2 members' units disagree")
            ->assertSee('Ana')->assertSee('Different units')
            ->assertSee('Ben')->assertSee('Sign-up unit not in the org structure')
            ->assertDontSee('Cara');                                     // MEPLEF is inside MEPCOM
        $this->get('/admin/unit-check?state=ok')->assertSee('Cara');
    }

    public function test_the_fixes_align_either_side_and_are_audited()
    {
        $this->withSession($this->super)->post('/admin/unit-check/' . $this->ids['Ben'] . '/use-assignment')->assertSessionHas('success');
        $this->assertSame('MSSC', DB::table('users')->where('id', $this->ids['Ben'])->value('mother_unit'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'unit.aligned_to_assignment']);

        $this->post('/admin/unit-check/' . $this->ids['Ana'] . '/use-mother-unit')->assertSessionHas('success');
        $p = Personnel::where('serial_number', 'S-A')->first();
        $this->assertSame('MEPLEF', $p->currentAssignment->orgUnit->code);
        $this->assertSame(2, PersonnelAssignment::where('personnel_id', $p->id)->count());   // history kept

        $this->get('/admin/unit-check')->assertSee("Every member's units agree");
        $this->post('/admin/unit-check/' . $this->ids['Cara'] . '/use-mother-unit')->assertStatus(422);
    }

    public function test_auditors_see_the_report_but_cannot_fix()
    {
        $this->withSession($this->auditor)->get('/admin/unit-check')->assertOk()->assertSee('can view this report but not change units')
            ->assertDontSee('Set sign-up unit to');
        $this->post('/admin/unit-check/' . $this->ids['Ben'] . '/use-assignment')->assertForbidden();
    }
}
