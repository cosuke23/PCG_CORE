<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Personnel master, effective-dated assignments and organisational scope
 * (a MEPCOM HR admin must not see or change MSSC personnel).
 */
class PersonnelTest extends TestCase
{
    use RefreshDatabase;

    private OrgUnit $mepcom;
    private OrgUnit $mssc;
    private OrgUnit $meplef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
                $t->string('rank')->nullable(); $t->string('serial_number')->nullable(); $t->string('registration_type')->nullable();
                $t->date('date_of_birth')->nullable(); $t->date('date_of_appointment')->nullable();
                $t->string('mother_unit')->nullable(); $t->string('status')->nullable(); $t->timestamps();
            });
        }

        $this->mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $this->mssc = OrgUnit::where('code', 'MSSC')->first();
        $this->meplef = OrgUnit::create(['parent_id' => $this->mepcom->id, 'code' => 'MEPLEF', 'name' => 'MEPLEF', 'type' => 'unit']);
    }

    private function hq(): array
    {
        return ['admin_logged_in' => true, 'admin_role' => 'hr_admin'];
    }

    private function scoped(OrgUnit $unit): array
    {
        return ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_org_unit_id' => $unit->id];
    }

    private function person(string $serial, ?OrgUnit $unit = null, string $name = 'Juan Dela Cruz'): Personnel
    {
        $p = Personnel::create(['serial_number' => $serial, 'full_name' => $name, 'rank_id' => Rank::where('code', 'PO2')->value('id'), 'classification' => 'non_officer']);
        if ($unit) {
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => '2024-01-01']);
        }

        return $p;
    }

    private function newPerson(array $overrides = []): array
    {
        return array_merge([
            'serial_number' => 'po-12345', 'last_name' => 'Santos', 'first_name' => 'Maria',
            'rank_id' => Rank::where('code', 'LT')->value('id'), 'org_unit_id' => $this->meplef->id, 'start_date' => '2025-02-01',
        ], $overrides);
    }

    public function test_ranks_are_seeded_without_civilian_classifications()
    {
        $this->assertSame(20, Rank::count());
        $this->assertEqualsCanonicalizing(['officer', 'non_officer'], Rank::distinct()->pluck('classification')->all());
    }

    public function test_hr_creates_one_record_per_serial_number()
    {
        $this->withSession($this->hq())->post('/admin/personnel', $this->newPerson())->assertRedirect();

        $p = Personnel::firstOrFail();
        $this->assertSame('PO-12345', $p->serial_number);
        $this->assertSame('Maria Santos', $p->full_name);
        $this->assertSame('officer', $p->classification);
        $this->assertSame($this->meplef->id, $p->currentAssignment->org_unit_id);

        // same serial with other spacing/case is a duplicate
        $this->withSession($this->hq())->post('/admin/personnel', $this->newPerson(['serial_number' => ' Po-12345 ', 'first_name' => 'Other']))
            ->assertSessionHasErrors('serial_number');
        $this->assertSame(1, Personnel::count());
    }

    public function test_same_name_needs_an_explicit_not_duplicate_confirmation()
    {
        $this->withSession($this->hq())->post('/admin/personnel', $this->newPerson());

        $this->withSession($this->hq())->post('/admin/personnel', $this->newPerson(['serial_number' => 'NEW-1']))
            ->assertSessionHasErrors('last_name');
        $this->assertSame(1, Personnel::count());

        $this->withSession($this->hq())->post('/admin/personnel', $this->newPerson(['serial_number' => 'NEW-1', 'confirm_not_duplicate' => 1]))
            ->assertRedirect();
        $this->assertSame(2, Personnel::count());
    }

    public function test_mepcom_admin_sees_only_mepcom_personnel()
    {
        $ours = $this->person('MEP-1', $this->meplef, 'Ana Mepcom');
        $theirs = $this->person('MSS-1', $this->mssc, 'Ben Mssc');
        $unassigned = $this->person('NONE-1', null, 'Cara Unassigned');

        $this->withSession($this->scoped($this->mepcom))->get('/admin/personnel')
            ->assertOk()->assertSee('Ana Mepcom')->assertDontSee('Ben Mssc')->assertDontSee('Cara Unassigned');
        $this->withSession($this->scoped($this->mepcom))->get("/admin/personnel/{$ours->id}")->assertOk();
        $this->withSession($this->scoped($this->mepcom))->get("/admin/personnel/{$theirs->id}")->assertNotFound();
        $this->withSession($this->scoped($this->mepcom))->get("/admin/personnel/{$unassigned->id}")->assertNotFound();
        // filtering by an out-of-scope unit does not widen the scope
        $this->withSession($this->scoped($this->mepcom))->get('/admin/personnel?unit=' . $this->mssc->id)->assertDontSee('Ben Mssc');

        $this->flushSession();
        $this->withSession($this->hq())->get('/admin/personnel')->assertSee('Ana Mepcom')->assertSee('Ben Mssc')->assertSee('Cara Unassigned');
    }

    public function test_scoped_admin_cannot_assign_outside_their_scope()
    {
        $ours = $this->person('MEP-1', $this->meplef);
        $theirs = $this->person('MSS-1', $this->mssc);

        $this->withSession($this->scoped($this->mepcom))->post("/admin/personnel/{$theirs->id}/assignments", ['org_unit_id' => $this->mepcom->id, 'start_date' => '2025-01-01'])
            ->assertNotFound();
        $this->withSession($this->scoped($this->mepcom))->post("/admin/personnel/{$ours->id}/assignments", ['org_unit_id' => $this->mssc->id, 'start_date' => '2025-01-01'])
            ->assertSessionHasErrors('org_unit_id');
        $this->withSession($this->scoped($this->mepcom))->post('/admin/personnel', $this->newPerson(['org_unit_id' => $this->mssc->id]))
            ->assertSessionHasErrors('org_unit_id');
        $this->withSession($this->scoped($this->mepcom))->post('/admin/personnel', $this->newPerson(['org_unit_id' => null]))
            ->assertSessionHasErrors('org_unit_id');

        $this->assertSame(1, PersonnelAssignment::where('personnel_id', $ours->id)->count());
        $this->assertSame(2, Personnel::count());
    }

    public function test_transfer_closes_the_current_assignment_and_keeps_history()
    {
        $p = $this->person('MEP-1', $this->meplef);

        $this->withSession($this->hq())->post("/admin/personnel/{$p->id}/assignments", [
            'org_unit_id' => $this->mssc->id, 'start_date' => '2025-03-01', 'order_reference' => 'GO-123',
        ])->assertSessionHas('success');

        $rows = PersonnelAssignment::where('personnel_id', $p->id)->orderBy('start_date')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('2025-02-28', $rows[0]->end_date->format('Y-m-d'));
        $this->assertSame($this->meplef->id, $rows[0]->org_unit_id);
        $this->assertNull($rows[1]->end_date);
        $this->assertSame(1, PersonnelAssignment::where('personnel_id', $p->id)->whereNull('end_date')->count());

        // back-dated or same-day transfers would overlap: rejected, nothing changed
        $this->withSession($this->hq())->post("/admin/personnel/{$p->id}/assignments", ['org_unit_id' => $this->mepcom->id, 'start_date' => '2025-03-01'])
            ->assertSessionHasErrors('start_date');
        $this->assertCount(2, PersonnelAssignment::where('personnel_id', $p->id)->get());

        // the person moved to MSSC: MEPCOM admin no longer sees them
        $this->withSession($this->scoped($this->mepcom))->get("/admin/personnel/{$p->id}")->assertNotFound();
        $this->withSession($this->scoped($this->mssc))->get("/admin/personnel/{$p->id}")->assertOk()->assertSee('GO-123')->assertSee('MEPLEF');
    }

    public function test_scoped_admin_cannot_open_organisation_wide_pages_or_files()
    {
        foreach (['/admin/dashboard', '/admin/hr-analytics', '/admin/unit-users', '/admin/messages', '/admin/training-applications', '/operation-tracks'] as $url) {
            $this->withSession($this->scoped($this->mepcom))->get($url)->assertForbidden();
        }
        $this->withSession($this->scoped($this->mepcom))->get('/files/documents_toupload/x/y.pdf')->assertRedirect(route('login'));
        $this->withSession($this->scoped($this->mepcom))->get('/admin/organization')->assertOk();
    }

    public function test_super_admin_sets_scope_for_hr_admins_only_and_it_applies_immediately()
    {
        $super = AdminUser::create(['name' => 'S', 'email' => 's@example.test', 'password' => Hash::make('x'), 'role' => 'super_admin']);
        $hr = AdminUser::create(['name' => 'H', 'email' => 'h@example.test', 'password' => Hash::make('x'), 'role' => 'hr_admin']);
        $superSession = ['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $super->id];

        $this->withSession($superSession)->post("/admin/accounts/{$hr->id}/role", ['role' => 'hr_admin', 'org_unit_id' => $this->mepcom->id])
            ->assertSessionHas('success');
        $this->assertSame($this->mepcom->id, $hr->fresh()->org_unit_id);
        $this->withSession($superSession)->post("/admin/accounts/{$super->id}/role", ['role' => 'super_admin', 'org_unit_id' => $this->mepcom->id])
            ->assertSessionHas('error');

        // the HR admin's existing session picks the scope up on the next request
        $this->flushSession();
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_user_id' => $hr->id])
            ->get('/admin/dashboard')->assertForbidden();

        // deactivation ends the session
        $hr->update(['is_active' => false]);
        $this->get('/admin/personnel')->assertRedirect(route('admin.login'));
    }

    public function test_sync_is_a_dry_run_by_default_and_skips_unsafe_rows()
    {
        $now = now();
        DB::table('users')->insert([
            ['name' => 'A One', 'email' => 'a@x.test', 'password' => 'x', 'serial_number' => 'S-1', 'rank' => 'PO1', 'registration_type' => 'non_officer', 'status' => 'Approved', 'mother_unit' => 'Operating Unit', 'created_at' => $now],
            ['name' => 'B Two', 'email' => 'b@x.test', 'password' => 'x', 'serial_number' => 's-2 ', 'rank' => 'LT', 'registration_type' => 'officer', 'status' => 'approved', 'mother_unit' => null, 'created_at' => $now],
            ['name' => 'Dup A', 'email' => 'c@x.test', 'password' => 'x', 'serial_number' => 'S-3', 'rank' => 'PO1', 'registration_type' => null, 'status' => 'Approved', 'mother_unit' => null, 'created_at' => $now],
            ['name' => 'Dup B', 'email' => 'd@x.test', 'password' => 'x', 'serial_number' => 's-3', 'rank' => 'PO1', 'registration_type' => null, 'status' => 'Pending', 'mother_unit' => null, 'created_at' => $now],
            ['name' => 'No Serial', 'email' => 'e@x.test', 'password' => 'x', 'serial_number' => '', 'rank' => 'PO1', 'registration_type' => null, 'status' => 'Approved', 'mother_unit' => null, 'created_at' => $now],
            ['name' => 'Pending', 'email' => 'f@x.test', 'password' => 'x', 'serial_number' => 'S-4', 'rank' => 'XYZ', 'registration_type' => null, 'status' => 'Pending', 'mother_unit' => null, 'created_at' => $now],
        ]);

        $this->artisan('pcg:personnel-sync')->expectsOutputToContain('DRY RUN')->assertSuccessful();
        $this->assertSame(0, Personnel::count());

        $this->artisan('pcg:personnel-sync', ['--apply' => true, '--assign-to' => 'mepcom', '--start-date' => '2026-01-01'])->assertSuccessful();
        $this->assertEqualsCanonicalizing(['S-1', 'S-2'], Personnel::pluck('serial_number')->all());
        $this->assertSame('officer', Personnel::where('serial_number', 'S-2')->value('classification'));
        $this->assertSame(2, PersonnelAssignment::where('org_unit_id', $this->mepcom->id)->whereNull('end_date')->count());
        $this->assertSame(6, DB::table('users')->count()); // users untouched

        // running again creates nothing new
        $this->artisan('pcg:personnel-sync', ['--apply' => true])->assertSuccessful();
        $this->assertSame(2, Personnel::count());
    }
}
