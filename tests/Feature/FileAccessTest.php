<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * /files/{path} must only serve personnel files to staff or their owner.
 */
class FileAccessTest extends TestCase
{
    private string $root;
    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/uploads');
        File::deleteDirectory($this->root);
        File::ensureDirectoryExists($this->root . '/documents_toupload/documents');
        File::ensureDirectoryExists($this->root . '/201_files/soi');
        File::ensureDirectoryExists($this->root . '/disciplinary_actions/disciplinary_actions');
        File::ensureDirectoryExists($this->root . '/selfies');
        File::put($this->root . '/documents_toupload/documents/1_nbi.pdf', '%PDF-owner');
        File::put($this->root . '/201_files/soi/1_soi.pdf', '%PDF-201');
        File::put($this->root . '/disciplinary_actions/disciplinary_actions/1_case.pdf', '%PDF-case');
        File::put($this->root . '/selfies/abc.png', 'png');
        File::put($this->root . '/secret.txt', 'outside any area');

        config(['pcg.upload_root' => $this->root, 'pcg.selfie_dir' => $this->root . '/selfies']);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('serial_number')->nullable();
            $table->string('selfie_image')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('personnel_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            foreach (['soi', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance', 'ofr',
                'medical_clearance', 'pft_clearance', 'whole_body_picture', 'photo_2x2', 'residential_sketch'] as $c) {
                $table->string($c)->nullable();
            }
        });
        Schema::create('personnel_201_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            foreach (['soi', 'appointment', 'leave', 'awards', 'disciplinary', 'performance',
                'legal', 'academic', 'financial', 'retirement', 'body_picture'] as $c) {
                $table->string($c)->nullable();
            }
        });

        $this->owner = User::forceCreate(['name' => 'Owner', 'email' => 'o@example.test', 'password' => Hash::make('x'), 'selfie_image' => 'selfies/abc.png']);
        $this->other = User::forceCreate(['name' => 'Other', 'email' => 'x@example.test', 'password' => Hash::make('x')]);

        DB::table('personnel_data')->insert(['personnel_id' => $this->owner->id, 'nbi_clearance' => 'documents/1_nbi.pdf']);
        DB::table('personnel_201_files')->insert(['personnel_id' => $this->owner->id, 'soi' => '201_files/soi/1_soi.pdf']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_guest_is_sent_to_login()
    {
        $this->get('/files/documents_toupload/documents/1_nbi.pdf')->assertRedirect(route('login'));
    }

    public function test_owner_can_open_own_files()
    {
        $this->actingAs($this->owner);

        $this->get('/files/documents_toupload/documents/1_nbi.pdf')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/files/201_files/soi/1_soi.pdf')->assertOk();
        $this->get('/files/selfies/abc.png')->assertOk();
    }

    public function test_other_personnel_cannot_open_someone_elses_files()
    {
        $this->actingAs($this->other);

        $this->get('/files/documents_toupload/documents/1_nbi.pdf')->assertNotFound();
        $this->get('/files/201_files/soi/1_soi.pdf')->assertNotFound();
        $this->get('/files/selfies/abc.png')->assertNotFound();
    }

    public function test_personnel_cannot_open_disciplinary_records()
    {
        $this->actingAs($this->owner)
            ->get('/files/disciplinary_actions/disciplinary_actions/1_case.pdf')->assertNotFound();
    }

    public function test_admin_and_m1_can_open_any_file()
    {
        // Admins and M1 checkers; own-unit checkers are covered in UnitAccessTest.
        foreach ([['admin_logged_in' => true], ['unit_user_id' => 1, 'unit_role' => \App\Models\UnitUser::ROLE_M1]] as $session) {
            $this->withSession($session);
            $this->get('/files/documents_toupload/documents/1_nbi.pdf')->assertOk();
            $this->get('/files/disciplinary_actions/disciplinary_actions/1_case.pdf')->assertOk();
            $this->get('/files/selfies/abc.png')->assertOk();
            $this->flushSession();
        }
    }

    public function test_paths_outside_the_upload_areas_are_rejected()
    {
        $this->withSession(['admin_logged_in' => true]);

        $this->get('/files/secret.txt')->assertNotFound();
        $this->get('/files/documents_toupload/../secret.txt')->assertNotFound();
        $this->get('/files/documents_toupload/..%2Fsecret.txt')->assertNotFound();
        $this->get('/files/selfies/missing.png')->assertNotFound();
        $this->get('/files/.env')->assertNotFound();
    }
}
