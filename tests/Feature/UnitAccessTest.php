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
            $t->string('serial_number')->nullable(); $t->string('mother_unit')->nullable(); $t->string('status')->default('Approved'); $t->timestamps();
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

        // the 201 review list is open to own-unit checkers (their unit only); the rest stays M1
        foreach (['/unit/dashboardM1', '/unit/201-files/SN-B'] as $uri) {
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

    public function test_a_second_account_with_someones_serial_gives_no_access()
    {
        // SN-B belongs to account 2 (NOCOP). An impostor registers SN-B in MEPLEF.
        DB::table('personnel_data')->where('serial_number', 'SN-B')->update(['personnel_id' => 2]);
        DB::table('users')->insert(['name' => 'Impostor', 'email' => 'i@x.test', 'password' => 'x', 'serial_number' => 'SN-B', 'mother_unit' => 'MEPLEF']);

        $this->withSession($this->unit);
        $this->post('/unit/documents/review', ['serial_number' => 'SN-B', 'decision' => 'approved'])->assertForbidden();
        $this->assertSame('Pending', DB::table('personnel_data')->where('serial_number', 'SN-B')->value('status'));
        $this->get('/files/documents_toupload/documents/b.pdf')->assertNotFound();
    }

    public function test_a_pending_account_cannot_pull_an_older_record_into_its_unit()
    {
        // Older rows have no personnel_id. A pending registration with SN-B in
        // MEPLEF must not make SN-B's documents visible to the MEPLEF checker.
        DB::table('users')->insert(['name' => 'Impostor', 'email' => 'i@x.test', 'password' => 'x', 'serial_number' => 'SN-B', 'mother_unit' => 'MEPLEF', 'status' => 'Pending']);

        $this->withSession($this->unit);
        $this->post('/unit/documents/review', ['serial_number' => 'SN-B', 'decision' => 'approved'])->assertForbidden();
        $this->assertSame('Pending', DB::table('personnel_data')->where('serial_number', 'SN-B')->value('status'));
        $this->get('/files/documents_toupload/documents/b.pdf')->assertNotFound();
    }

    public function test_review_changes_only_the_owners_row_when_a_serial_is_duplicated()
    {
        // An impostor (approved, other unit) uploaded under SN-A: two rows share the serial.
        DB::table('personnel_data')->where('serial_number', 'SN-A')->update(['personnel_id' => 1]);
        DB::table('users')->insert(['name' => 'Impostor', 'email' => 'i@x.test', 'password' => 'x', 'serial_number' => 'SN-A', 'mother_unit' => 'NOCOP']);
        DB::table('personnel_data')->insert(['personnel_id' => 3, 'serial_number' => 'SN-A', 'nbi_clearance' => 'documents/i.pdf', 'status' => 'Pending']);

        $this->withSession($this->unit);
        $this->post('/unit/documents/review', ['serial_number' => 'SN-A', 'decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', DB::table('personnel_data')->where('personnel_id', 1)->value('status'));
        $this->assertSame('Pending', DB::table('personnel_data')->where('personnel_id', 3)->value('status'));
    }

    public function test_rejecting_a_document_clears_that_document_only()
    {
        Schema::create('rejected_documents', function (Blueprint $t) {
            $t->id(); $t->string('serial_number'); $t->string('doc_type'); $t->string('file_path')->nullable();
            $t->text('comment')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });
        DB::table('personnel_data')->where('serial_number', 'SN-A')->update([
            'medical_clearance' => 'documents/a-med.pdf', 'ombudsman_clearance' => 'documents/a-omb.pdf',
        ]);

        $this->withSession($this->unit)->post('/unit/documents/review', [
            'serial_number' => 'SN-A',
            'decision' => 'rejected',
            'docs' => ['medical_clearance' => ['checked' => '1', 'comment' => 'Expired']],
        ])->assertRedirect();

        $row = DB::table('personnel_data')->where('serial_number', 'SN-A')->first();
        $this->assertNull($row->medical_clearance);
        $this->assertSame('documents/a-omb.pdf', $row->ombudsman_clearance);
        $this->assertSame('Medical', DB::table('rejected_documents')->value('doc_type'));
    }

    public function test_returning_the_residence_location_clears_the_pin()
    {
        Schema::create('rejected_documents', function (Blueprint $t) {
            $t->id(); $t->string('serial_number'); $t->string('doc_type'); $t->string('file_path')->nullable();
            $t->text('comment')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::table('personnel_data', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable(); $t->decimal('longitude', 10, 7)->nullable();
        });
        DB::table('personnel_data')->update(['latitude' => 14.5995, 'longitude' => 120.9842]);

        $this->withSession($this->unit)->post('/unit/documents/review', [
            'serial_number' => 'SN-A',
            'decision' => 'rejected',
            'docs' => ['location' => ['checked' => '1', 'comment' => 'Pin the house, not the barangay hall']],
        ])->assertRedirect();

        $a = DB::table('personnel_data')->where('serial_number', 'SN-A')->first();
        $this->assertNull($a->latitude);
        $this->assertNull($a->longitude);
        $this->assertSame('documents/a.pdf', $a->nbi_clearance, 'documents are untouched');
        $this->assertSame('rejected', $a->status);
        $ret = DB::table('rejected_documents')->first();
        $this->assertSame(['Residence Location', 'Pin the house, not the barangay hall', 'rejected'], [$ret->doc_type, $ret->comment, $ret->status]);
        $this->assertNotNull(DB::table('personnel_data')->where('serial_number', 'SN-B')->value('latitude'), 'other members keep their pin');

        // an unticked location box changes nothing
        DB::table('personnel_data')->where('serial_number', 'SN-A')->update(['latitude' => 14.6, 'longitude' => 121.0]);
        $this->post('/unit/documents/review', ['serial_number' => 'SN-A', 'decision' => 'rejected',
            'docs' => ['location' => ['comment' => 'not ticked']]])->assertRedirect();
        $this->assertNotNull(DB::table('personnel_data')->where('serial_number', 'SN-A')->value('latitude'));
    }

    public function test_review_windows_list_every_document_and_the_location()
    {
        // both pages use one review partial and one script
        foreach (['unit/dashboard', 'unit/dashboard_m1'] as $view) {
            $this->assertStringContainsString("@include('unit.partials.review'", file_get_contents(resource_path("views/$view.blade.php")));
        }
        $partial = file_get_contents(resource_path('views/unit/partials/review.blade.php'));
        foreach (['soi', 'ofr', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance', 'medical_clearance',
            'pft_clearance', 'photo_2x2', 'whole_body_picture', 'residential_sketch'] as $f) {
            $this->assertStringContainsString("'$f' =>", $partial, "the review lists $f");
        }
        $js = file_get_contents(public_path('js/unit-checker.js'));
        $this->assertStringContainsString("'docs[' + it.field + '][checked]'", $js);
        $this->assertStringContainsString("field: 'location'", $js, 'the location can be returned');
        $this->assertStringNotContainsString('innerHTML', $js, 'server data is placed with textContent only');
    }

    public function test_queue_leaves_out_records_with_nothing_to_review()
    {
        Schema::create('reinlist_personal_info', function (Blueprint $t) {
            $t->id(); $t->string('serial_number')->nullable();
        });
        Schema::table('personnel_data', function (Blueprint $t) {
            foreach (['nbi_clearance_expiration', 'ombudsman_clearance_expiration', 'sandiganbayan_clearance_expiration'] as $c) {
                $t->date($c)->nullable();
            }
        });
        // SN-A was cleared (pcg:reset-documents): returned, no documents, no pin
        DB::table('personnel_data')->where('serial_number', 'SN-A')->update(['nbi_clearance' => null, 'status' => 'rejected']);

        $html = $this->withSession($this->m1)->get('/unit/dashboardM1')->assertOk()->getContent();
        $this->assertStringContainsString('SN-B', $html, 'a record with documents is listed');
        $this->assertStringNotContainsString('SN-A', $html, 'an empty record waits for the member, not the checker');

        // once the member uploads again it is back
        DB::table('personnel_data')->where('serial_number', 'SN-A')->update(['nbi_clearance' => 'documents/a.pdf', 'status' => 'Pending']);
        $this->assertStringContainsString('SN-A', $this->get('/unit/dashboardM1')->getContent());
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
        $this->withSession(['admin_logged_in' => true, 'admin_role' => 'super_admin']);

        $this->post('/admin/unit-users/1/role', ['role' => UnitUser::ROLE_M1])->assertRedirect();
        $this->assertSame(UnitUser::ROLE_M1, DB::table('unit_users')->where('id', 1)->value('role'));

        $this->post('/admin/unit-users/1/role', ['role' => 'super_admin'])->assertSessionHasErrors('role');
    }
}
