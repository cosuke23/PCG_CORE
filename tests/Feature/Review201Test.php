<?php

namespace Tests\Feature;

use App\Models\UnitUser;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 201 file review (owner decisions 2026-11-09): the member's 201 sections lock
 * while waiting for review; own-unit checkers (their unit) and M1 checkers (all)
 * approve the 201 file or return sections with a comment; only returned and
 * never-filled sections open again.
 */
class Review201Test extends TestCase
{
    private array $unit = ['unit_user_id' => 1, 'unit_role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF'];
    private array $m1 = ['unit_user_id' => 2, 'unit_role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'];
    private User $member;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->root = storage_path('framework/testing/review-201');
        File::deleteDirectory($this->root);
        config(['pcg.upload_root' => $this->root]);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->string('mother_unit')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        Schema::create('unit_users', function (Blueprint $t) {
            $t->id(); $t->string('full_name')->nullable(); $t->string('email'); $t->string('password')->nullable();
            $t->string('role')->nullable(); $t->string('unit_name')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('personnel_201_files', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->unsignedBigInteger('personnel_id')->nullable(); $t->string('serial_number')->nullable();
            foreach (array_keys(\App\Support\Review201::SECTIONS) as $c) { $t->string($c)->nullable(); }
        });
        // the migration under test adds the review column
        (require base_path('database/migrations/2026_11_09_000001_add_review_status_to_personnel_201_files.php'))->up();
        Schema::create('rejected_documents', function (Blueprint $t) {
            $t->id(); $t->string('serial_number'); $t->string('doc_type'); $t->string('file_path')->nullable();
            $t->text('comment')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::create('reinlist_basic_info', function (Blueprint $t) { $t->id(); $t->string('serial_number')->nullable(); });

        $this->member = User::forceCreate(['name' => 'Ana Member', 'email' => 'a@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-A', 'status' => 'Approved', 'mother_unit' => 'MEPLEF']);
        User::forceCreate(['name' => 'Ben Other', 'email' => 'b@x.test', 'password' => bcrypt('x'), 'serial_number' => 'SN-B', 'status' => 'Approved', 'mother_unit' => 'NOCOP']);
        DB::table('personnel_201_files')->insert(['user_id' => 2, 'personnel_id' => 2, 'serial_number' => 'SN-B', 'appointment' => '201_files/appointment/b.pdf', 'status' => 'Pending']);
        DB::table('unit_users')->insert([
            ['email' => 'unit@x.test', 'password' => Hash::make('pw-unit-123'), 'role' => UnitUser::ROLE_UNIT, 'unit_name' => 'MEPLEF'],
            ['email' => 'm1@x.test', 'password' => Hash::make('pw-m1-1234'), 'role' => UnitUser::ROLE_M1, 'unit_name' => 'MEPCOM'],
        ]);
    }

    private function pdf(string $name = 'f.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nsecret-member-content\n%%EOF");
    }

    private function row(): object
    {
        return DB::table('personnel_201_files')->where('personnel_id', $this->member->id)->first();
    }

    public function test_the_migration_is_reversible()
    {
        $m = require base_path('database/migrations/2026_11_09_000001_add_review_status_to_personnel_201_files.php');
        $m->down();
        $this->assertFalse(Schema::hasColumn('personnel_201_files', 'status'));
        $m->up();
        $this->assertTrue(Schema::hasColumn('personnel_201_files', 'status'));
    }

    public function test_upload_locks_the_201_file_and_the_checker_returns_one_section()
    {
        $this->actingAs($this->member);
        $this->post('/upload-201', ['soi' => $this->pdf(), 'appointment' => $this->pdf()])->assertSessionHasNoErrors();
        $this->assertSame('Pending', $this->row()->status);

        // waiting: no upload control, Upload button locked, server refuses
        $html = $this->get('/upload-201')->assertOk()->getContent();
        $this->assertStringContainsString('Your 201 file is with your unit checker.', $html);
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertMatchesRegularExpression('/id="submitBtn"[^>]*disabled/', $html);
        $this->post('/upload-201', ['leave' => $this->pdf()])->assertSessionHasErrors('leave');

        // own-unit checker sees the member (not other units) and returns the appointment
        $this->flushSession();
        $queue = $this->withSession($this->unit)->get('/unit/201-files')->assertOk()->getContent();
        $this->assertStringContainsString('SN-A', $queue);
        $this->assertStringNotContainsString('SN-B', $queue, 'another unit');
        $this->post('/unit/201-files/review', ['serial_number' => 'SN-B', 'decision' => 'approved'])->assertForbidden();
        $this->post('/unit/201-files/review', ['serial_number' => 'SN-A', 'decision' => 'rejected',
            'docs' => ['appointment' => ['checked' => '1', 'comment' => 'Unsigned appointment order']]])->assertRedirect();
        $row = $this->row();
        $this->assertSame('rejected', $row->status);
        $this->assertNull($row->appointment, 'the returned section is cleared');
        $this->assertNotNull($row->soi, 'the accepted section stays');
        $this->assertSame(['201: Appointment', 'Unsigned appointment order', 'rejected'],
            [DB::table('rejected_documents')->value('doc_type'), DB::table('rejected_documents')->value('comment'), DB::table('rejected_documents')->value('status')]);

        // member: only the returned and never-filled sections open, with the comment
        $this->flushSession();
        $this->actingAs($this->member);
        $html = $this->get('/upload-201')->getContent();
        $this->assertStringContainsString('Your unit checker returned 1 section.', $html);
        $this->assertStringContainsString('Unsigned appointment order', $html);
        $this->assertStringContainsString('id="f-appointment"', $html);
        $this->assertStringContainsString('id="f-leave"', $html, 'a never-filled section is open');
        $this->assertStringNotContainsString('id="f-soi"', $html, 'the accepted section stays locked');
        $this->post('/upload-201', ['soi' => $this->pdf()])->assertSessionHasErrors('soi');
        $this->post('/upload-201', ['appointment' => $this->pdf()])->assertSessionHasNoErrors();
        $this->assertSame('Pending', $this->row()->status, 'back to the checker');
        $this->assertSame('complied', DB::table('rejected_documents')->value('status'));

        // the M1 checker sees every unit and approves
        $this->flushSession();
        $all = $this->withSession($this->m1)->get('/unit/201-files')->getContent();
        $this->assertStringContainsString('SN-A', $all);
        $this->assertStringContainsString('SN-B', $all);
        $this->post('/unit/201-files/review', ['serial_number' => 'SN-A', 'decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $this->row()->status);

        // accepted: everything locked for the member
        $this->flushSession();
        $this->actingAs($this->member);
        $this->post('/upload-201', ['appointment' => $this->pdf()])->assertSessionHasErrors('appointment');
        $this->post('/upload-201', ['leave' => $this->pdf()])->assertSessionHasNoErrors();
    }

    public function test_own_unit_checker_opens_only_their_units_201_files()
    {
        File::ensureDirectoryExists($this->root . '/201_files/appointment');
        File::put($this->root . '/201_files/appointment/b.pdf', '%PDF-b');
        File::put($this->root . '/201_files/appointment/a.pdf', '%PDF-a');
        DB::table('personnel_201_files')->insert(['user_id' => 1, 'personnel_id' => 1, 'serial_number' => 'SN-A', 'appointment' => '201_files/appointment/a.pdf', 'status' => 'Pending']);

        $this->withSession($this->unit);
        $this->get('/files/201_files/appointment/a.pdf')->assertOk();
        $this->get('/files/201_files/appointment/b.pdf')->assertNotFound();

        $this->flushSession();
        $this->withSession($this->m1)->get('/files/201_files/appointment/b.pdf')->assertOk();
    }
}
