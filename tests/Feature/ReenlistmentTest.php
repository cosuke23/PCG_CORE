<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Re-enlistment module: non-officers by ETE; role and sign-in.
 */
class ReenlistmentTest extends TestCase
{
    private array $re = ['admin_logged_in' => true, 'admin_role' => 'reenlistment_admin'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 3)->setTime(9, 0));

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password')->default('x');
            $t->string('rank')->nullable(); $t->string('serial_number')->nullable(); $t->string('registration_type')->nullable();
            $t->date('date_of_appointment')->nullable(); $t->date('ete')->nullable();
            $t->string('mother_unit')->nullable(); $t->string('unit_assignment')->nullable();
            $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::create('reinlist_basic_info', function (Blueprint $t) {
            $t->id(); $t->string('serial_number'); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->timestamps();
        });
        Schema::create('admin_audit_logs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('admin_user_id')->nullable(); $t->string('admin_email')->nullable();
            $t->string('action'); $t->string('method', 10)->nullable(); $t->string('path')->nullable();
            $t->string('subject')->nullable(); $t->text('details')->nullable(); $t->unsignedSmallInteger('status')->nullable();
            $t->string('ip', 45)->nullable(); $t->string('user_agent')->nullable(); $t->timestamps();
        });

        $p = fn ($name, $serial, $type, $ete, $status = 'Approved', $unit = 'MEPLEF') => [
            'name' => $name, 'email' => strtolower($name) . '@x.test', 'rank' => 'SN1', 'serial_number' => $serial,
            'registration_type' => $type, 'date_of_appointment' => '2023-11-01', 'ete' => $ete,
            'mother_unit' => $unit, 'status' => $status,
        ];
        DB::table('users')->insert([
            $p('Soon', 'NO-1', 'non_officer', '2026-12-15'),                 // 42 days
            $p('Mid', 'NO-2', 'non_officer', '2027-06-01', 'Pending', 'NOCOP'), // ~7 months
            $p('Edge', 'NO-3', 'non_officer', '2027-11-03'),                 // exactly 12 months
            $p('Later', 'NO-4', 'non_officer', '2028-03-01'),                // over a year
            $p('Passed', 'NO-5', 'non_officer', '2026-08-01'),               // already passed
            $p('Blank', 'NO-6', 'non_officer', null),                        // no ETE
            $p('Rejected', 'NO-7', 'non_officer', '2026-12-20', 'Rejected'), // rejected account
            $p('Officer', 'OF-1', 'officer', '2026-12-10'),                  // not a non-officer
            $p('Mixed', 'NO-8', 'NON_OFFICER ', '2027-02-01', 'approved'),   // mixed case in real data
        ]);
        DB::table('reinlist_basic_info')->insert(['serial_number' => 'NO-1', 'first_name' => 'Soon', 'last_name' => 'X']);
    }

    public function test_default_list_is_non_officers_with_ete_within_twelve_months()
    {
        $res = $this->withSession($this->re)->get('/admin/reenlistment')->assertOk();

        foreach (['NO-1', 'NO-2', 'NO-3', 'NO-8'] as $in) {
            $res->assertSee($in);
        }
        foreach (['NO-4', 'NO-5', 'NO-6', 'NO-7', 'OF-1'] as $out) {
            $res->assertDontSee($out);
        }
        // soonest first, with the time left
        $this->assertTrue(strpos($res->getContent(), 'NO-1') < strpos($res->getContent(), 'NO-8'));
        $res->assertSee('42 days left');
        $this->assertSame(1, DB::table('admin_audit_logs')->where('action', 'reenlistment.list')->count());
    }

    public function test_other_lists_and_filters()
    {
        $this->withSession($this->re);

        $this->get('/admin/reenlistment?window=overdue')->assertOk()->assertSee('NO-5')->assertSee('Passed 94 days ago')->assertDontSee('NO-1');
        $this->get('/admin/reenlistment?window=no_ete')->assertOk()->assertSee('NO-6')->assertDontSee('NO-5');
        $this->get('/admin/reenlistment?window=due&months=3')->assertOk()->assertSee('NO-1')->assertDontSee('NO-2');
        $this->get('/admin/reenlistment?unit=NOCOP')->assertOk()->assertSee('NO-2')->assertDontSee('NO-1');
        $this->get('/admin/reenlistment?q=Edge')->assertOk()->assertSee('NO-3')->assertDontSee('NO-1');
        $this->get('/admin/reenlistment?status=all')->assertOk()->assertSee('NO-7');
        $this->get('/admin/reenlistment?window=bogus')->assertSessionHasErrors('window');
    }

    public function test_detail_page_and_csv_export()
    {
        $this->withSession($this->re);
        $soon = DB::table('users')->where('serial_number', 'NO-1')->value('id');
        $officer = DB::table('users')->where('serial_number', 'OF-1')->value('id');

        $this->get("/admin/reenlistment/{$soon}")->assertOk()->assertSee('42 days left')->assertSee('Submitted');
        $this->get("/admin/reenlistment/{$officer}")->assertNotFound();

        $csv = $this->get('/admin/reenlistment/export')->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('NO-1', $body);
        $this->assertStringNotContainsString('NO-4', $body);
        $this->assertSame(1, DB::table('admin_audit_logs')->where('action', 'reenlistment.csv')->count());
    }

    public function test_reenlistment_admin_sees_only_this_module()
    {
        $this->withSession($this->re);
        $this->get('/admin/dashboard')->assertForbidden();
        $this->get('/admin/accounts')->assertForbidden();

        $this->assertSame('admin.reenlistment.index', \App\Http\Controllers\AdminLoginController::homeRoute('reenlistment_admin'));
    }

    public function test_other_roles()
    {
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'lms_admin'])->get('/admin/reenlistment')->assertForbidden();
        $this->flushSession();
        // the auditor may look but not export
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'auditor']);
        $this->get('/admin/reenlistment')->assertOk();
        $this->get('/admin/reenlistment/export')->assertForbidden();
    }

    public function test_dedicated_sign_in_page()
    {
        $this->get('/reenlistment/login')->assertOk()->assertSee('Re-enlistment')->assertSee(route('admin.login.submit'), false);
        $this->get('/admin/reenlistment')->assertRedirect(route('admin.login'));
    }
}
