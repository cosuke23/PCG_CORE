<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use App\Models\Personnel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** pcg:import-org-units: dry run by default, insert-only, never changes existing units. */
class ImportOrgUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mepcom_file_is_valid_and_imports_once()
    {
        $this->artisan('pcg:import-org-units', ['file' => 'database/data/mepcom_units.json'])
            ->expectsOutputToContain('DRY RUN')->assertSuccessful();
        $this->assertSame(4, OrgUnit::count());

        $this->artisan('pcg:import-org-units', ['file' => 'database/data/mepcom_units.json', '--apply' => true])->assertSuccessful();
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $meplef = OrgUnit::where('code', 'MEPLEF')->first();
        $this->assertSame($mepcom->id, $meplef->parent_id);
        $this->assertSame(OrgUnit::where('code', 'HMEPLEF')->value('id'), OrgUnit::where('code', 'MEPLEF O/C')->value('parent_id'));
        $this->assertSame(144, OrgUnit::count());
        $this->assertContains(OrgUnit::where('code', 'MEPUnit Manila')->value('id'), $mepcom->subtreeIds());

        // re-running creates nothing and changes nothing
        $before = OrgUnit::orderBy('id')->get(['id', 'parent_id', 'name', 'updated_at'])->toArray();
        $this->artisan('pcg:import-org-units', ['file' => 'database/data/mepcom_units.json', '--apply' => true])->assertSuccessful();
        $this->assertSame($before, OrgUnit::orderBy('id')->get(['id', 'parent_id', 'name', 'updated_at'])->toArray());
    }

    public function test_existing_units_are_kept_where_they_are()
    {
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        OrgUnit::create(['parent_id' => $mssc->id, 'code' => 'NOCOP', 'name' => 'Kept name', 'type' => 'unit']);

        $this->artisan('pcg:import-org-units', ['file' => 'database/data/mepcom_units.json', '--apply' => true])
            ->expectsOutputToContain('exists under another parent')->assertSuccessful();

        $nocop = OrgUnit::where('code', 'NOCOP')->first();
        $this->assertSame($mssc->id, $nocop->parent_id);
        $this->assertSame('Kept name', $nocop->name);
        $this->assertSame($nocop->id, OrgUnit::where('code', 'HNOCOP')->value('parent_id'));
    }

    public function test_invalid_files_change_nothing()
    {
        $file = tempnam(sys_get_temp_dir(), 'units');
        file_put_contents($file, json_encode(['parent' => 'MEPCOM', 'units' => [
            ['code' => 'OK-1', 'name' => 'Fine', 'type' => 'unit', 'children' => []],
            ['code' => 'bad,code', 'name' => 'X', 'type' => 'galaxy', 'children' => []],
        ]]));

        $this->artisan('pcg:import-org-units', ['file' => $file, '--apply' => true])->assertFailed();
        $this->assertSame(4, OrgUnit::count());
        unlink($file);
    }

    public function test_sync_places_people_at_the_unit_they_registered_with()
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('registration_type')->nullable(); $t->date('date_of_birth')->nullable();
            $t->date('date_of_appointment')->nullable(); $t->string('mother_unit')->nullable(); $t->string('sub_unit')->nullable();
            $t->string('unit_assignment')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });
        $row = fn ($serial, $sub, $assignment) => ['name' => $serial, 'email' => "{$serial}@x.test", 'password' => 'x', 'rank' => 'PO1', 'serial_number' => $serial,
            'status' => 'Approved', 'mother_unit' => 'Operational Support Units', 'sub_unit' => $sub, 'unit_assignment' => $assignment];
        DB::table('users')->insert([
            $row('A-1', 'Marine Environmental Protection Law Enforcement Force (MEPLEF)', 'HMEPLEF - LEF-1'),
            $row('A-2', 'Marine Environmental Protection Group (MEPG)', 'MEPGroup NCRCL - MEPUnit Malacañang'),
            $row('A-3', 'National Operations Center for Oil Pollution (NOCOP)', 'OSRU - OSRU PAL'),
            $row('A-4', 'Something unknown', 'X - Y'),
            $row('A-5', 'Marine Environmental Protection Law Enforcement Force (MEPLEF)', 'MEPLEU - not in the list'),
        ]);
        $this->artisan('pcg:import-org-units', ['file' => 'database/data/mepcom_units.json', '--apply' => true]);

        $this->artisan('pcg:personnel-sync', ['--apply' => true, '--assign-to' => 'MEPCOM', '--map-units' => 'database/data/mepcom_units.json'])->assertSuccessful();

        $at = fn ($serial) => Personnel::where('serial_number', $serial)->first()->currentAssignment->orgUnit->code;
        $this->assertSame('MEPLEF LEF-1', $at('A-1'));
        $this->assertSame('MEPUnit Malacanang', $at('A-2'));
        $this->assertSame('OSRU PAL', $at('A-3'));
        $this->assertSame('MEPCOM', $at('A-4'));   // unknown: fallback
        $this->assertSame('MEPLEF', $at('A-5'));   // unit known, entry unknown
    }
}
