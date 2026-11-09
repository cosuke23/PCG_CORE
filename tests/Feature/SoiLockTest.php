<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SOI submission lock (owner decisions 2026-11-09): after "Is the data you entered
 * correct? Yes" the SOI is submitted and every save is refused; the Adjutant and the
 * HR admin of the member's command/unit (and the super admin) can reopen it.
 */
class SoiLockTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private int $ben;
    private array $hr;
    private array $adjutant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_11_09_000002_add_soi_submitted_at_to_users.php'))->up();
        Schema::create('reinlist_basic_info', function (Blueprint $t) {
            $t->id(); $t->string('serial_number')->nullable(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable();
            $t->string('middle_name')->nullable(); $t->string('extension_name')->nullable(); $t->string('rank')->nullable(); $t->string('class')->nullable();
            $t->date('birthday')->nullable(); $t->string('birth_place')->nullable(); $t->timestamps();
        });

        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        foreach ([['SN-IN', 'Ana Inside', $mepcom], ['SN-OUT', 'Ben Outside', $mssc]] as [$serial, $name, $unit]) {
            $p = Personnel::create(['serial_number' => $serial, 'full_name' => $name]);
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => '2024-01-01']);
        }
        $this->ana = User::forceCreate(['name' => 'Ana Inside', 'email' => 'ana@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-IN', 'status' => 'Approved']);
        $this->ben = DB::table('users')->insertGetId(['name' => 'Ben Outside', 'email' => 'ben@x.test', 'serial_number' => 'SN-OUT', 'status' => 'Approved', 'soi_submitted_at' => now()]);
        DB::table('reinlist_basic_info')->insert(['serial_number' => 'SN-IN', 'first_name' => 'Ana']);

        $hr = \App\Models\AdminUser::create(['name' => 'HR', 'email' => 'hr@x.test', 'password' => 'x', 'role' => 'hr_admin', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $this->hr = ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_user_id' => $hr->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'hr@x.test'];
        $adj = \App\Models\AdminUser::create(['name' => 'Adj', 'email' => 'adj@x.test', 'password' => 'x', 'role' => 'adjutant', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $this->adjutant = ['admin_logged_in' => true, 'admin_role' => 'adjutant', 'admin_user_id' => $adj->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'adj@x.test'];
    }

    private function submittedAt(int $id)
    {
        return DB::table('users')->where('id', $id)->value('soi_submitted_at');
    }

    public function test_submitting_locks_every_save()
    {
        $this->actingAs($this->ana);
        $this->postJson('/soi/submit')->assertOk()->assertJson(['status' => 'success']);
        $this->assertNotNull($this->submittedAt($this->ana->id));

        foreach (range(1, 6) as $n) {
            $this->postJson("/temp-save/step$n", [])->assertStatus(423)->assertJson(['status' => 'locked'])
                ->assertJsonFragment(['message' => \App\Support\SoiLock::MESSAGE]);
        }
        $this->post('/profile/update', ['district' => 'X'])->assertRedirect(route('profile'))->assertSessionHas('error', \App\Support\SoiLock::MESSAGE);
        $this->postJson('/soi/submit')->assertStatus(423); // already submitted
    }

    public function test_an_soi_must_exist_before_it_is_submitted()
    {
        DB::table('reinlist_basic_info')->delete();
        $this->actingAs($this->ana)->postJson('/soi/submit')->assertStatus(422);
        $this->assertNull($this->submittedAt($this->ana->id));
    }

    public function test_hr_admin_and_adjutant_reopen_only_within_their_scope()
    {
        DB::table('users')->where('id', $this->ana->id)->update(['soi_submitted_at' => now()]);

        $html = $this->withSession($this->hr)->get('/admin/soi')->assertOk()->getContent();
        $this->assertStringContainsString('Ana Inside', $html);
        $this->assertStringNotContainsString('Ben Outside', $html, 'another command');

        $this->post("/admin/soi/{$this->ana->id}/reopen", [])->assertSessionHasErrors('reason');
        $this->post("/admin/soi/{$this->ben}/reopen", ['reason' => 'Wrong unit'])->assertNotFound();
        $this->assertNotNull($this->submittedAt($this->ben));

        $this->post("/admin/soi/{$this->ana->id}/reopen", ['reason' => 'Wrong date of appointment'])->assertRedirect();
        $this->assertNull($this->submittedAt($this->ana->id));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'soi.reopened']);

        // the member can save again
        $this->flushSession();
        DB::table('users')->where('id', $this->ana->id)->update(['soi_submitted_at' => now()]);
        $this->withSession($this->adjutant)->post("/admin/soi/{$this->ana->id}/reopen", ['reason' => 'Correct the awards'])->assertRedirect();
        $this->assertNull($this->submittedAt($this->ana->id), 'the Adjutant can reopen too');
    }

    public function test_other_admin_roles_cannot_reopen()
    {
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $rev = \App\Models\AdminUser::create(['name' => 'Rev', 'email' => 'rev@x.test', 'password' => 'x', 'role' => 'records_reviewer', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $session = ['admin_logged_in' => true, 'admin_role' => 'records_reviewer', 'admin_user_id' => $rev->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'rev@x.test'];
        $this->withSession($session)->get('/admin/soi')->assertForbidden();
        $this->post("/admin/soi/{$this->ana->id}/reopen", ['reason' => 'Trying anyway'])->assertForbidden();
    }

    public function test_hr_admin_and_adjutant_edit_a_submitted_soi_within_their_scope()
    {
        DB::table('users')->where('id', $this->ana->id)->update(['soi_submitted_at' => now()]);
        $step1 = ['last_name' => 'Inside', 'first_name' => 'Anabel', 'rank' => 'SN1', 'birthday' => '1995-02-03', 'serial_number' => 'SN-OUT'];

        $this->withSession($this->adjutant)->postJson("/admin/soi/{$this->ana->id}/step1", $step1)->assertOk()->assertJson(['status' => 'success']);
        // saved for the member (the serial in the input is ignored), still submitted, audited
        $this->assertSame('Anabel', DB::table('reinlist_basic_info')->where('serial_number', 'SN-IN')->value('first_name'));
        $this->assertFalse(DB::table('reinlist_basic_info')->where('serial_number', 'SN-OUT')->exists());
        $this->assertNotNull($this->submittedAt($this->ana->id), 'editing does not reopen the SOI');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'soi.edited', 'subject' => 'users:' . $this->ana->id]);

        // invalid input: the member's validation answers, nothing audited
        $before = DB::table('admin_audit_logs')->where('action', 'soi.edited')->count();
        $this->postJson("/admin/soi/{$this->ana->id}/step1", ['first_name' => 'X'])->assertStatus(422);
        $this->assertSame($before, DB::table('admin_audit_logs')->where('action', 'soi.edited')->count());

        // outside the admin's command: not found
        $this->postJson("/admin/soi/{$this->ben}/step1", $step1)->assertNotFound();
        $this->get("/admin/soi/{$this->ben}/edit")->assertNotFound();
        $this->postJson("/admin/soi/{$this->ana->id}/step7", $step1)->assertNotFound();

        $this->flushSession();
        $this->withSession($this->hr)->postJson("/admin/soi/{$this->ana->id}/step1", ['first_name' => 'Ana'] + $step1)->assertOk();
        $this->assertSame('Ana', DB::table('reinlist_basic_info')->where('serial_number', 'SN-IN')->value('first_name'));
    }

    public function test_other_admin_roles_and_members_cannot_edit_an_soi()
    {
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $rev = \App\Models\AdminUser::create(['name' => 'Rev', 'email' => 'rev@x.test', 'password' => 'x', 'role' => 'records_reviewer', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $session = ['admin_logged_in' => true, 'admin_role' => 'records_reviewer', 'admin_user_id' => $rev->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'rev@x.test'];
        $this->withSession($session)->get("/admin/soi/{$this->ana->id}/edit")->assertForbidden();
        $this->postJson("/admin/soi/{$this->ana->id}/step1", ['first_name' => 'X'])->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->ana)->postJson("/admin/soi/{$this->ana->id}/step1", ['first_name' => 'X'])->assertUnauthorized(); // a member is not an admin
    }
}
