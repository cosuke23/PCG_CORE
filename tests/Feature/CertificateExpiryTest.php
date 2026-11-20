<?php

namespace Tests\Feature;

use App\Mail\CertificateExpiring;
use App\Models\AdminUser;
use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Support\CertificateExpiry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Training certificate and 201 document expiry: reminders, checker card, admin list (owner request 2026-11-20). */
class CertificateExpiryTest extends TestCase
{
    use RefreshDatabase;

    private int $ana;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 7, 30, 0, 'Asia/Manila'));
        Cache::flush();
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable(); $t->timestamps();
        });
        $mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $this->ana = DB::table('users')->insertGetId(['name' => 'Ana', 'email' => 'ana@x.test', 'serial_number' => 'S-1', 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        $ben = DB::table('users')->insertGetId(['name' => 'Ben', 'email' => 'ben@x.test', 'serial_number' => 'S-2', 'status' => 'Approved', 'mother_unit' => 'NOCOP']);
        $pa = Personnel::create(['serial_number' => 'S-1', 'full_name' => 'Ana', 'user_id' => $this->ana]);
        $pb = Personnel::create(['serial_number' => 'S-2', 'full_name' => 'Ben', 'user_id' => $ben]);
        foreach ([$pa, $pb] as $p) {
            PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $mepcom->id, 'start_date' => '2024-01-01']);
        }
        DB::table('personnel_trainings')->insert([
            ['personnel_id' => $pa->id, 'title' => 'Basic Life Support', 'kind' => 'qualification', 'valid_until' => '2026-12-20', 'created_at' => now(), 'updated_at' => now()],  // 30 days
            ['personnel_id' => $pb->id, 'title' => 'Oil Spill Responder', 'kind' => 'qualification', 'valid_until' => '2026-11-27', 'created_at' => now(), 'updated_at' => now()], // 7 days
            ['personnel_id' => $pb->id, 'title' => 'Old cert', 'kind' => 'course', 'valid_until' => '2026-11-10', 'created_at' => now(), 'updated_at' => now()],              // expired
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_members_are_reminded_at_30_7_and_0_days_once()
    {
        Mail::fake();
        $this->artisan('pcg:certificate-reminders')->assertSuccessful();
        Mail::assertSent(CertificateExpiring::class, 2);
        Mail::assertSent(CertificateExpiring::class, fn ($m) => $m->hasTo('ana@x.test') && $m->label === 'Basic Life Support' && $m->daysLeft === 30);
        $this->assertSame(2, DB::table('member_notifications')->where('kind', 'certificate.expiring')->count());
        $this->artisan('pcg:certificate-reminders');
        Mail::assertSent(CertificateExpiring::class, 2);
    }

    public function test_checker_card_is_scoped_and_admins_see_the_list()
    {
        $this->app['request']->setLaravelSession($this->app['session']->driver());
        session(['unit_role' => 'unit_checker', 'unit_name' => 'NOCOP']);
        $c = CertificateExpiry::forUnitChecker();
        $this->assertSame(1, $c['expired']);
        $this->assertSame(['Oil Spill Responder', 'Old cert'], $c['list']->pluck('title')->sort()->values()->all());

        $sup = AdminUser::create(['name' => 'S', 'email' => 'su@x.test', 'password' => 'x', 'role' => 'super_admin', 'is_active' => true]);
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin', 'admin_user_id' => $sup->id, 'admin_email' => 'su@x.test', 'admin_name' => 'S'])
            ->get('/admin/expiring?days=30')->assertOk()
            ->assertSee('2 certificates expire within 30 days in all units, 1 already expired')->assertSee('Basic Life Support');
    }
}
