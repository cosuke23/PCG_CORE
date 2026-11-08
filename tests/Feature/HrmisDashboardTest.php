<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HRMIS overview figures come from real records and respect the admin's scope. */
class HrmisDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $serial, ?OrgUnit $unit, string $rank, string $name, string $start = '2024-01-01'): Personnel
    {
        $r = Rank::where('code', $rank)->first();
        $p = Personnel::create(['serial_number' => $serial, 'full_name' => $name, 'rank_id' => $r->id, 'classification' => $r->classification,
            'date_of_birth' => '1990-01-01', 'date_entered_service' => '2015-01-01']);
        if ($unit) {
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => $start]);
        }

        return $p;
    }

    public function test_figures_are_limited_to_the_admins_command()
    {
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        $meplef = OrgUnit::create(['parent_id' => $mepcom->id, 'code' => 'MEPLEF', 'name' => 'Law Enforcement Force', 'type' => 'unit']);
        $nocop = OrgUnit::create(['parent_id' => $mepcom->id, 'code' => 'NOCOP', 'name' => 'NOCOP', 'type' => 'unit']);

        $this->person('A1', $meplef, 'LT', 'Ana Officer', now()->subDays(3)->toDateString());
        $this->person('A2', $meplef, 'PO1', 'Ben Enlisted');
        $this->person('A3', $mepcom, 'PO2', 'Cy Direct');
        $this->person('B1', $mssc, 'CAPT', 'Dan Mssc', now()->subDays(2)->toDateString());
        $incomplete = Personnel::create(['serial_number' => 'C1', 'full_name' => 'Eve Unassigned']);

        $scoped = ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_org_unit_id' => $mepcom->id];
        $response = $this->withSession($scoped)->get('/admin/hrmis')->assertOk();

        $this->assertSame(3, $response->viewData('total'));
        $this->assertSame(1, $response->viewData('officers'));
        $this->assertSame(2, $response->viewData('nonOfficers'));
        $this->assertSame(1, $response->viewData('movementCount'));
        $this->assertSame(0, $response->viewData('incomplete'));
        $this->assertNull($response->viewData('unassigned'));
        $this->assertSame(
            ['MEPLEF' => 2, 'NOCOP' => 0, 'MEPCOM (directly)' => 1],
            collect($response->viewData('byUnit'))->pluck('count', 'label')->all()
        );
        $this->assertSame(['LT', 'PO1', 'PO2'], collect($response->viewData('byRank'))->pluck('label')->all());
        $response->assertSee('Ana Officer')->assertDontSee('Dan Mssc')->assertDontSee('CAPT');

        // organisation-wide: every command, unassigned and incomplete records included
        $this->flushSession();
        $hq = $this->withSession(['admin_logged_in' => true, 'admin_role' => 'hr_admin'])->get('/admin/hrmis')->assertOk();
        $this->assertSame(5, $hq->viewData('total'));
        $this->assertSame(1, $hq->viewData('unassigned'));
        $this->assertSame(1, $hq->viewData('incomplete'));
        $this->assertSame(['MSSC' => 1, 'MEPCOM' => 3, 'MARSLEC' => 0], collect($hq->viewData('byUnit'))->pluck('count', 'label')->all());
        $this->assertSame('No rank', collect($hq->viewData('byRank'))->last()['label']);

        // the "needing attention" list shows exactly those records
        $this->get('/admin/personnel?incomplete=1')->assertSee($incomplete->full_name)->assertDontSee('Ana Officer');
    }

    public function test_empty_state_has_no_invented_figures()
    {
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin'])->get('/admin/hrmis')
            ->assertOk()->assertSee('No personnel records yet')->assertViewHas('total', 0);
    }

    public function test_explorer_rows_follow_the_scope_and_carry_no_birth_dates_or_contacts()
    {
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        $meplef = OrgUnit::create(['parent_id' => $mepcom->id, 'code' => 'MEPLEF', 'name' => 'Law Enforcement Force', 'type' => 'unit']);
        $a = $this->person('A1', $meplef, 'LT', 'Ana Officer', now()->subDays(3)->toDateString());
        $a->update(['email' => 'ana.private@example.test', 'contact_number' => '09170000000']);
        $this->person('B1', $mssc, 'CAPT', 'Dan Mssc');
        Personnel::create(['serial_number' => 'C1', 'full_name' => 'Eve Unassigned']);

        $scoped = $this->withSession(['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_org_unit_id' => $mepcom->id])->get('/admin/hrmis')->assertOk();
        $rows = collect($scoped->viewData('explorer')['rows']);
        $this->assertSame(['A1'], $rows->pluck('serial')->all());
        $row = $rows->first();
        $this->assertSame('MEPLEF', $row['bucket']);
        $this->assertTrue($row['moved']);
        $this->assertSame([], $row['missing']);
        $this->assertArrayNotHasKey('date_of_birth', $row);
        $scoped->assertDontSee('1990-01-01')->assertDontSee('ana.private@example.test')->assertDontSee('09170000000')->assertDontSee('Dan Mssc');

        $this->flushSession();
        $hq = $this->withSession(['admin_logged_in' => true, 'admin_role' => 'hr_admin'])->get('/admin/hrmis')->assertOk();
        $all = collect($hq->viewData('explorer')['rows'])->keyBy('serial');
        $this->assertCount(3, $all);
        $this->assertNull($all['C1']['unit']);
        $this->assertSame(['rank', 'birth date', 'service entry date'], $all['C1']['missing']);
        // rows add up to the figures shown on the cards
        $this->assertSame($hq->viewData('total'), $all->count());
        $this->assertSame($hq->viewData('incomplete'), $all->filter(fn ($r) => $r['missing'])->count());
        $this->assertSame($hq->viewData('unassigned'), $all->filter(fn ($r) => !$r['unit'])->count());
    }

    public function test_export_is_limited_to_the_scope_and_audited()
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        $a = $this->person('A1', $mepcom, 'LT', 'Ana Officer');
        $b = $this->person('B1', $mssc, 'CAPT', 'Dan Mssc');

        $scoped = ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'hr@example.test'];
        $csv = $this->withSession($scoped)->post('/admin/hrmis/export', ['ids' => $a->id . ',' . $b->id, 'filters' => 'Officers'])
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Ana Officer', $csv);
        $this->assertStringNotContainsString('Dan Mssc', $csv); // outside the admin's command
        $this->assertStringNotContainsString('1990-01-01', $csv);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'hrmis.overview.csv']);

        $this->withSession($scoped)->post('/admin/hrmis/export', ['ids' => '1;DROP'])->assertSessionHasErrors('ids');
        // re-enlistment admins cannot see personnel at all
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'reenlistment_admin'])
            ->post('/admin/hrmis/export', ['ids' => (string) $a->id])->assertForbidden();
    }
}
