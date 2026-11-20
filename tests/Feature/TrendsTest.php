<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Command trends, limited to the admin's scope (owner request 2026-11-20). */
class TrendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trends_count_strength_ete_leave_and_deployments_in_scope()
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 9, 0, 0, 'Asia/Manila'));
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('serial_number')->nullable(); $t->string('status')->nullable();
            $t->string('registration_type')->nullable(); $t->date('ete')->nullable(); $t->string('mother_unit')->nullable(); $t->timestamps();
        });
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        foreach ([['S-1', 'SN1', $mepcom, '2027-02-10'], ['S-2', 'PO3', $mepcom, '2027-02-20'], ['S-3', 'SN1', $mssc, '2027-02-15']] as $i => [$serial, $rank, $unit, $ete]) {
            $p = Personnel::create(['serial_number' => $serial, 'full_name' => $serial, 'rank_id' => Rank::where('code', $rank)->value('id')]);
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => '2024-01-01']);
            DB::table('users')->insert(['id' => $i + 1, 'name' => $serial, 'email' => "$serial@x.test", 'serial_number' => $serial, 'status' => 'Approved', 'registration_type' => 'non_officer', 'ete' => $ete]);
        }
        (require base_path('database/migrations/2026_11_20_000004_create_personnel_deployments_table.php'))->up();
        DB::table('leave_requests')->insert(['user_id' => 1, 'serial_number' => 'S-1', 'leave_type' => 'rnr', 'start_date' => '2026-10-30', 'end_date' => '2026-11-02',
            'days' => 4, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('personnel_deployments')->insert(['user_id' => 3, 'serial_number' => 'S-3', 'kind' => 'sea_duty', 'assignment' => 'x', 'start_date' => '2026-09-01', 'created_at' => now(), 'updated_at' => now()]);

        $hr = AdminUser::create(['name' => 'H', 'email' => 'hr@x.test', 'password' => 'x', 'role' => 'hr_admin', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $res = $this->withSession(['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_user_id' => $hr->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'hr@x.test', 'admin_name' => 'H'])
            ->get('/admin/trends')->assertOk()
            ->assertSee('2 active members in')->assertSee('2 terms of enlistment end in the next 12 months')
            ->assertSee('February 2027: 2 ETE')->assertSee('October 2026: 2 leave days')->assertSee('November 2026: 2 leave days')
            ->assertSee('November 2026: 0 members deployed');   // S-3 is outside this command
        $sup = AdminUser::create(['name' => 'S', 'email' => 'su@x.test', 'password' => 'x', 'role' => 'super_admin', 'is_active' => true]);
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $sup->id, 'admin_email' => 'su@x.test', 'admin_name' => 'S'])
            ->get('/admin/trends')->assertSee('3 active members')->assertSee('November 2026: 1 member deployed');
        Carbon::setTestNow();
    }
}
