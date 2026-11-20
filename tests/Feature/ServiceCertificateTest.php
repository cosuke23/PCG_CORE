<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Service certificates: request, issue within scope, print with QR, public check, revoke (owner request 2026-11-20). */
class ServiceCertificateTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private array $hr;
    private array $hrOther;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable();
            $t->date('date_of_appointment')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $mssc = OrgUnit::where('code', 'MSSC')->first();
        $this->ana = User::forceCreate(['name' => 'Ana Reyes', 'email' => 'ana@x.test', 'password' => bcrypt('x'), 'serial_number' => 'S-1', 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        $p = Personnel::create(['serial_number' => 'S-1', 'full_name' => 'Ana M. Reyes', 'user_id' => $this->ana->id, 'rank_id' => Rank::where('code', 'PO2')->value('id'), 'date_entered_service' => '2015-06-01']);
        PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $mepcom->id, 'position' => 'Duty clerk', 'start_date' => '2024-01-01']);
        $hr = AdminUser::create(['name' => 'HR One', 'email' => 'hr@x.test', 'password' => 'x', 'role' => 'hr_admin', 'org_unit_id' => $mepcom->id, 'is_active' => true]);
        $hr2 = AdminUser::create(['name' => 'HR Two', 'email' => 'hr2@x.test', 'password' => 'x', 'role' => 'hr_admin', 'org_unit_id' => $mssc->id, 'is_active' => true]);
        $this->hr = ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_user_id' => $hr->id, 'admin_org_unit_id' => $mepcom->id, 'admin_email' => 'hr@x.test', 'admin_name' => 'HR One'];
        $this->hrOther = ['admin_logged_in' => true, 'admin_role' => 'hr_admin', 'admin_user_id' => $hr2->id, 'admin_org_unit_id' => $mssc->id, 'admin_email' => 'hr2@x.test', 'admin_name' => 'HR Two'];
    }

    public function test_request_issue_print_and_check()
    {
        $this->actingAs($this->ana)->post('/certificates', ['kind' => 'service', 'purpose' => 'Housing loan'])->assertSessionHas('success');
        $id = DB::table('service_certificates')->value('id');
        $this->get("/certificates/$id/print")->assertNotFound();                        // not issued yet

        $this->withSession($this->hrOther)->post("/admin/certificates/$id", ['decision' => 'issue'])->assertForbidden();   // other command
        $this->withSession($this->hr)->get('/admin/certificates')->assertSee('1 certificate request is waiting')->assertSee('Housing loan');
        $this->post("/admin/certificates/$id", ['decision' => 'decline'])->assertSessionHasErrors('note');
        $this->post("/admin/certificates/$id", ['decision' => 'issue'])->assertSessionHas('success');

        $c = DB::table('service_certificates')->find($id);
        $this->assertSame('issued', $c->status);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $c->code);
        $this->assertDatabaseHas('member_notifications', ['user_id' => $this->ana->id, 'kind' => 'certificate.issued']);

        $this->actingAs($this->ana)->get("/certificates/$id/print")->assertOk()
            ->assertSee('PO2 Ana M. Reyes')->assertSee('entered the service on June 1, 2015')->assertSee('Housing loan')
            ->assertSee('data-pcg-qr="' . url('/verify/certificate/' . $c->code) . '"', false);

        auth()->logout();
        $this->get('/verify/certificate/' . $c->code)->assertOk()->assertSee('genuine and valid')->assertSee('PO2 Ana M. Reyes')->assertDontSee('S-1');
        $this->get('/verify/certificate?code=AAAA-BBBB-CCCC')->assertSee('No issued certificate has the number');

        $this->withSession($this->hr)->post("/admin/certificates/$id", ['decision' => 'revoke', 'note' => 'Issued in error']);
        $this->get('/verify/certificate/' . $c->code)->assertSee('revoked and is no longer valid');
    }

    public function test_members_print_only_their_own()
    {
        $bob = User::forceCreate(['name' => 'Bob', 'email' => 'bob@x.test', 'password' => bcrypt('x'), 'serial_number' => 'S-2', 'status' => 'Approved']);
        $id = DB::table('service_certificates')->insertGetId(['user_id' => $this->ana->id, 'serial_number' => 'S-1', 'kind' => 'service', 'purpose' => 'x',
            'status' => 'issued', 'code' => 'AAAA-BBBB-CCCC', 'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($bob)->get("/certificates/$id/print")->assertNotFound();
    }
}
