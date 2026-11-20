<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Promotion eligibility: time in grade, schooling, scope, CSV (owner request 2026-11-20). */
class PromotionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private array $super;
    private array $hr;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pcg.promotion.ranks' => ['PO3' => ['years' => 3, 'courses' => ['Petty Officer Course']]]]);
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        $rank = fn ($c) => Rank::where('code', $c)->value('id');
        $make = function ($serial, $name, $code, $since, $unit) use ($rank) {
            $p = Personnel::create(['serial_number' => $serial, 'full_name' => $name, 'rank_id' => $rank($code), 'rank_date' => $since, 'classification' => 'non_officer']);
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => '2020-01-01']);
            return $p;
        };
        $make('S-1', 'Ana Due', 'SN1', '2024-06-01', $mepcom);          // 2 years by 2026-12-31: eligible
        $make('S-2', 'Ben New', 'SN1', '2026-03-01', $mepcom);          // not yet
        $po = $make('S-3', 'Cara Course', 'PO3', '2023-01-01', $mepcom); // 3 years met, course missing
        $make('S-4', 'Dan Other', 'SN1', '2020-01-01', $mssc);          // other command
        $make('S-5', 'Eve NoDate', 'SN2', null, $mepcom);
        DB::table('personnel_trainings')->insert(['personnel_id' => $po->id, 'title' => 'Basic Seamanship', 'kind' => 'course', 'created_at' => now(), 'updated_at' => now()]);

        $sup = AdminUser::create(['name' => 'S', 'email' => 'su@x.test', 'password' => 'x', 'role' => 'super_admin', 'is_active' => true]);
        $hr = AdminUser::create(['name' => 'H', 'email' => 'hr@x.test', 'password' => 'x', 'role' => 'hr_admin', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $this->super = ['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $sup->id, 'admin_email' => 'su@x.test', 'admin_name' => 'S'];
        $this->hr = ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_user_id' => $hr->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'hr@x.test', 'admin_name' => 'H'];
    }

    public function test_the_list_applies_time_in_grade_schooling_and_scope()
    {
        $this->withSession($this->hr)->get('/admin/promotions?as_of=2026-12-31')->assertOk()
            ->assertSee('1 member is eligible for promotion by December 31, 2026')
            ->assertSee('Ana Due')->assertDontSee('Ben New')->assertDontSee('Dan Other');
        $this->get('/admin/promotions?as_of=2026-12-31&state=schooling')->assertSee('Cara Course')->assertSee('Needs: Petty Officer Course');
        $this->get('/admin/promotions?as_of=2026-12-31&state=no_rank_date')->assertSee('Eve NoDate');

        DB::table('personnel_trainings')->insert(['personnel_id' => Personnel::where('serial_number', 'S-3')->value('id'), 'title' => 'Petty Officer Course Class 12', 'kind' => 'course', 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/admin/promotions?as_of=2026-12-31')->assertSee('2 members are eligible');

        $this->withSession($this->super)->get('/admin/promotions?as_of=2026-12-31')->assertSee('Dan Other');
    }

    public function test_the_csv_is_audited()
    {
        $csv = $this->withSession($this->super)->get('/admin/promotions/export?as_of=2026-12-31&state=all')->streamedContent();
        $this->assertStringContainsString('S-1,"Ana Due",SN1,PO3', $csv);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'promotion.exported']);
    }
}
