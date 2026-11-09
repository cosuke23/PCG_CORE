<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Uploading one document must not unlink the member's other documents,
 * nor wipe saved dates the form did not send.
 */
class UploadKeepsFilesTest extends TestCase
{
    private const FILES_201 = ['soi', 'appointment', 'leave', 'awards', 'disciplinary', 'performance',
        'legal', 'academic', 'financial', 'retirement', 'body_picture'];

    private string $root;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->root = storage_path('framework/testing/keep-uploads');
        File::deleteDirectory($this->root);
        config(['pcg.upload_root' => $this->root]);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->string('serial_number')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('personnel_201_files', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->unsignedBigInteger('personnel_id'); $t->string('serial_number')->nullable();
            foreach (self::FILES_201 as $c) {
                $t->string($c)->nullable();
            }
        });
        Schema::create('personnel_data', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('personnel_id'); $t->string('serial_number')->nullable();
            foreach (['soi', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance', 'ofr', 'medical_clearance',
                'pft_clearance', 'whole_body_picture', 'photo_2x2', 'residential_sketch'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['nbi_clearance_expiration', 'ombudsman_clearance_expiration', 'sandiganbayan_clearance_expiration'] as $c) {
                $t->date($c)->nullable();
            }
            $t->decimal('latitude', 10, 7)->nullable(); $t->decimal('longitude', 10, 7)->nullable();
            $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::create('rejected_documents', function (Blueprint $t) {
            $t->id(); $t->string('serial_number'); $t->string('doc_type'); $t->string('status')->nullable(); $t->timestamps();
        });

        $this->member = User::forceCreate(['name' => 'Member', 'email' => 'm@example.test', 'password' => Hash::make('x'), 'serial_number' => 'SN-1']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_uploading_one_201_file_keeps_the_others()
    {
        DB::table('personnel_201_files')->insert(['user_id' => $this->member->id, 'personnel_id' => $this->member->id,
            'serial_number' => 'SN-1', 'soi' => '201_files/soi/old_soi.pdf', 'awards' => '201_files/awards/old_awards.pdf']);

        $this->actingAs($this->member)->post('/upload-201', [
            'appointment' => UploadedFile::fake()->create('order.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('upload.201'))->assertSessionHas('success');

        $row = DB::table('personnel_201_files')->where('personnel_id', $this->member->id)->first();
        $this->assertSame('201_files/soi/old_soi.pdf', $row->soi);
        $this->assertSame('201_files/awards/old_awards.pdf', $row->awards);
        $this->assertStringStartsWith('201_files/appointment/', $row->appointment);
        $this->assertSame(1, DB::table('personnel_201_files')->count());
    }

    public function test_first_201_upload_creates_the_row_and_an_empty_upload_is_refused()
    {
        $this->actingAs($this->member)->post('/upload-201', [])->assertSessionHasErrors('files');
        $this->assertSame(0, DB::table('personnel_201_files')->count());

        $this->actingAs($this->member)->post('/upload-201', [
            'soi' => UploadedFile::fake()->create('soi.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('success');
        $row = DB::table('personnel_201_files')->first();
        $this->assertSame('SN-1', $row->serial_number);
        $this->assertStringStartsWith('201_files/soi/', $row->soi);
        $this->assertNull($row->appointment);
    }

    public function test_required_document_upload_keeps_dates_the_form_did_not_send()
    {
        // after a review that returned the NBI clearance (cleared); the Ombudsman one was accepted
        DB::table('personnel_data')->insert(['personnel_id' => $this->member->id, 'serial_number' => 'SN-1',
            'ombudsman_clearance' => 'documents/old_omb.pdf',
            'ombudsman_clearance_expiration' => '2027-01-31', 'sandiganbayan_clearance_expiration' => '2027-02-28',
            'latitude' => 14.5, 'longitude' => 121.0, 'status' => 'rejected']);

        // a form without the Ombudsman/Sandiganbayan section and without a location
        $this->actingAs($this->member)->post('/documents/upload', [
            'nbi_clearance' => UploadedFile::fake()->create('nbi.pdf', 10, 'application/pdf'),
            'nbi_clearance_expiration' => '2027-06-30',
        ])->assertSessionHas('success');

        $row = DB::table('personnel_data')->where('personnel_id', $this->member->id)->first();
        $this->assertSame('documents/old_omb.pdf', $row->ombudsman_clearance);
        $this->assertStringStartsWith('documents/', $row->nbi_clearance);
        $this->assertSame('2027-06-30', substr((string) $row->nbi_clearance_expiration, 0, 10));
        $this->assertSame('2027-01-31', substr((string) $row->ombudsman_clearance_expiration, 0, 10));
        $this->assertSame('2027-02-28', substr((string) $row->sandiganbayan_clearance_expiration, 0, 10));
        $this->assertEquals(14.5, (float) $row->latitude);

        // a field the form did send empty is still cleared (an open box: no file yet)
        DB::table('personnel_data')->update(['status' => 'rejected']);
        $this->actingAs($this->member)->post('/documents/upload', [
            'sandiganbayan_clearance_expiration' => '',
        ])->assertSessionHas('success');
        $this->assertNull(DB::table('personnel_data')->value('sandiganbayan_clearance_expiration'));
        $this->assertSame('2027-01-31', substr((string) DB::table('personnel_data')->value('ombudsman_clearance_expiration'), 0, 10));
    }
}
