<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin -> Organization: database-driven hierarchy, super admin changes only,
 * no deletion, no cycles, audited.
 */
class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private array $super = ['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_email' => 'boss@example.test'];
    private array $hr = ['admin_logged_in' => true, 'admin_role' => 'hr_admin'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function unit(string $code): OrgUnit
    {
        return OrgUnit::where('code', $code)->firstOrFail();
    }

    public function test_migration_seeds_headquarters_and_the_three_functional_commands()
    {
        $hq = $this->unit('NHQ-PCG');
        $this->assertSame(['MSSC', 'MEPCOM', 'MARSLEC'], $hq->children()->pluck('code')->all());
        $this->assertSame(['functional'], OrgUnit::where('type', 'command')->distinct()->pluck('category')->all());
    }

    public function test_hr_admin_can_view_but_not_change_the_structure()
    {
        $this->withSession($this->hr)->get('/admin/organization')
            ->assertOk()->assertSee('MEPCOM')->assertDontSee('Add unit');

        $this->withSession($this->hr)->post('/admin/organization', ['code' => 'X', 'name' => 'X', 'type' => 'unit'])->assertForbidden();
        $this->withSession($this->hr)->post('/admin/organization/' . $this->unit('MEPCOM')->id . '/status', ['is_active' => 0])->assertForbidden();
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'lms_admin'])->get('/admin/organization')->assertForbidden();
    }

    public function test_super_admin_adds_a_unit_under_a_command_and_it_is_audited()
    {
        $mepcom = $this->unit('MEPCOM');

        $this->withSession($this->super)->post('/admin/organization', [
            'parent_id' => $mepcom->id, 'code' => 'MEPLEF', 'name' => 'Marine Environmental Protection Law Enforcement Force',
            'type' => 'unit', 'effective_from' => '2024-01-15',
        ])->assertSessionHas('success');

        $unit = $this->unit('MEPLEF');
        $this->assertSame($mepcom->id, $unit->parent_id);
        $this->assertSame('2024-01-15', $unit->effective_from->format('Y-m-d'));
        $this->assertContains($unit->id, $mepcom->subtreeIds());
        $this->assertSame(1, DB::table('admin_audit_logs')->where('action', 'admin.organization.store')->count());

        $this->withSession($this->super)->get('/admin/organization?unit=' . $unit->id)
            ->assertOk()->assertSee('Edit MEPLEF')->assertSee('Marine Environmental Protection Law Enforcement Force');
    }

    public function test_codes_are_unique_and_input_is_validated()
    {
        $this->withSession($this->super)->post('/admin/organization', ['code' => 'mepcom', 'name' => 'Duplicate', 'type' => 'command'])
            ->assertSessionHasErrors('code');
        $this->withSession($this->super)->post('/admin/organization', ['code' => '<b>', 'name' => 'X', 'type' => 'unit'])
            ->assertSessionHasErrors('code');
        $this->withSession($this->super)->post('/admin/organization', ['code' => 'NEW', 'name' => 'X', 'type' => 'galaxy'])
            ->assertSessionHasErrors('type');
        $this->withSession($this->super)->post('/admin/organization', [
            'code' => 'NEW', 'name' => 'X', 'type' => 'unit', 'effective_from' => '2025-01-01', 'effective_to' => '2024-01-01',
        ])->assertSessionHasErrors('effective_to');
        $this->assertSame(4, OrgUnit::count());
    }

    public function test_a_unit_cannot_be_moved_under_itself_or_its_subordinates()
    {
        $mepcom = $this->unit('MEPCOM');
        $child = OrgUnit::create(['parent_id' => $mepcom->id, 'code' => 'MEPLEF', 'name' => 'MEPLEF', 'type' => 'unit']);

        foreach ([$mepcom->id, $child->id] as $parent) {
            $this->withSession($this->super)->post("/admin/organization/{$mepcom->id}", [
                'parent_id' => $parent, 'code' => 'MEPCOM', 'name' => $mepcom->name, 'type' => 'command',
            ])->assertSessionHasErrors('parent_id');
        }
        $this->assertSame($this->unit('NHQ-PCG')->id, $mepcom->fresh()->parent_id);
    }

    public function test_deactivation_keeps_the_record_and_respects_the_hierarchy()
    {
        $mepcom = $this->unit('MEPCOM');
        $child = OrgUnit::create(['parent_id' => $mepcom->id, 'code' => 'MEPLEF', 'name' => 'MEPLEF', 'type' => 'unit']);

        // parent with an active subordinate cannot be deactivated
        $this->withSession($this->super)->post("/admin/organization/{$mepcom->id}/status", ['is_active' => 0])->assertSessionHas('error');
        $this->assertTrue($mepcom->fresh()->is_active);

        $this->withSession($this->super)->post("/admin/organization/{$child->id}/status", ['is_active' => 0])->assertSessionHas('success');
        $this->withSession($this->super)->post("/admin/organization/{$mepcom->id}/status", ['is_active' => 0])->assertSessionHas('success');
        $this->assertFalse($mepcom->fresh()->is_active);
        $this->assertSame(5, OrgUnit::count()); // nothing deleted

        // nothing new under an inactive unit; child cannot be activated before its parent
        $this->withSession($this->super)->post('/admin/organization', ['parent_id' => $mepcom->id, 'code' => 'NOCOP', 'name' => 'NOCOP', 'type' => 'unit'])
            ->assertSessionHasErrors('parent_id');
        $this->withSession($this->super)->post("/admin/organization/{$child->id}/status", ['is_active' => 1])->assertSessionHas('error');
    }

    public function test_no_route_deletes_a_unit()
    {
        $this->withSession($this->super)->delete('/admin/organization/' . $this->unit('MSSC')->id)->assertStatus(405);
        $this->assertSame(4, OrgUnit::count());
    }
}
