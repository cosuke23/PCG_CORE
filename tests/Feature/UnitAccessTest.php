<?php

namespace Tests\Feature;

use App\Models\UnitUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Own-unit checkers vs M1 (all units) checkers.
 */
class UnitAccessTest extends TestCase
{
    private array $unit = ['unit_user_id' => 1, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF'];
    private array $m1 = ['unit_user_id' => 2, 'unit_role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->string('serial_number')->nullable(); $t->string('mother_unit')->nullable(); $t->timestamps();
        });
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->string('full_name')->nullable(); $t->string('email'); $t->string('password')->nullable();
            $t->string('role')->nullable(); $t->string('unit_name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('personnel_data', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('personnel_id')->nullable(); $t->string('serial_number');
            foreach (['soi', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance', 'ofr', 'medical_clearance',
                'pft_clearance', 'whole_body_picture', 'photo_2x2', 'residential_sketch'] as $c) {
                $t->string($c)->nullable();
            }
            $t->string('status')->nullable(); $t->timestamps();
        });

        DB::table('users')->insert([
            ['name' => 'In Unit', 'email' => 'a@x.test', 'password' => 'x', 'serial_number' => 'SN-A', 'mother_unit' => 'MEPLEF'],
            ['name' => 'Other Unit', 'email' => 'b@x.test', 'password' => 'x', 'serial_number' => 'SN-B', 'mother_unit' => 'NOCOP'],
        ]);
        DB::table('personnel_data')->insert([
            ['serial_number' => 'SN-A', 'nbi_clearance' => 'documents/a.pdf', 'status' => 'Pending'],
            ['serial_number' => 'SN-B', 'nbi_clearance' => 'documents/b.pdf', 'status' => 'Pending'],
        ]);
        DB::table('unit_users')->insert([
            ['email' => 'unit@x.test', 'password' => Hash::make('pw-unit-123'), 'role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF'],
            ['email' => 'm1@x.test', 'password' => Hash::make('pw-m1-1234'), 'role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'],
        ]);

        $root = storage_path('framework/testing/unit-uploads');
        File::ensureDirectoryExists("$root/documents_toupload/documents");
        File::put("$root/documents_toupload/documents/a.pdf", '%PDF-a');
        File::put("$root/documents_toupload/documents/b.pdf", '%PDF-b');
        config(['pcg.upload_root' => $root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/unit-uploads'));
        parent::tearDown();
    }

    public function test_m1_login_requires_the_m1_role()
    {
        $this->post('/unit/loginM1', ['email' => 'unit@x.test', 'password' => 'pw-unit-123'])
            ->assertSessionHas('error');
        $this->assertFalse(session()->has('unit_user_id'));

        $this->post('/unit/loginM1', ['email' => 'm1@x.test', 'password' => 'pw-m1-1234'])
            ->assertRedirect('/unit/dashboardM1');
    }

    public function test_own_unit_checker_cannot_open_m1_pages()
    {
        $this->withSession($this->unit);

        foreach (['/unit/dashboardM1', '/unit/201-files', '/unit/201-files/SN-B'] as $uri) {
            $this->get($uri)->assertRedirect('/unit/dashboard');
        }
        $this->getJson('/unit/search-serial/SN-B')->assertForbidden();
        $this->post('/unit/disciplinary/store', ['serial_number' => 'SN-B'])->assertRedirect('/unit/dashboard');
    }

    public function test_own_unit_checker_can_only_review_own_unit()
    {
        $this->withSession($this->unit);

        $this->post('/unit/documents/review', ['serial_number' => 'SN-B', 'decision' => 'approved'])->assertForbidden();
        $this->assertSame('Pending', DB::table('personnel_data')->where('serial_number', 'SN-B')->value('status'));

        $this->post('/unit/documents/review', ['serial_number' => 'SN-A', 'decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', DB::table('personnel_data')->where('serial_number', 'SN-A')->value('status'));
    }

    public function test_m1_checker_can_review_any_unit()
    {
        $this->withSession($this->m1)
            ->post('/unit/documents/review', ['serial_number' => 'SN-B', 'decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', DB::table('personnel_data')->where('serial_number', 'SN-B')->value('status'));
    }

    public function test_unit_file_access_is_limited_to_own_unit()
    {
        $this->withSession($this->unit);
        $this->get('/files/documents_toupload/documents/a.pdf')->assertOk();
        $this->get('/files/documents_toupload/documents/b.pdf')->assertNotFound();

        $this->flushSession();
        $this->withSession($this->m1);
        $this->get('/files/documents_toupload/documents/b.pdf')->assertOk();
    }

    public function test_admin_can_grant_and_remove_m1_access()
    {
        $this->withSession(['admin_logged_in' => true]);

        $this->post('/admin/unit-users/1/role', ['role' => UnitUser::ROLE_M1])->assertRedirect();
        $this->assertSame(UnitUser::ROLE_M1, DB::table('unit_users')->where('id', 1)->value('role'));

        $this->post('/admin/unit-users/1/role', ['role' => 'super_admin'])->assertSessionHasErrors('role');
    }
}
