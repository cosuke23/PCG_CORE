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
        Schema::create('reinlist_basic_info', function (Blueprint $t) { $t->id(); $t->string('serial_number')->nullable(); $t->string('first_name')->nullable(); });

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
}
