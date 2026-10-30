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
}
