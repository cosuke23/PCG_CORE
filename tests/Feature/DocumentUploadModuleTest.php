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
 * Member "Required documents" module (/upload-documents): every field, file
 * rules, encryption, what a save changes, messages, statuses and the page.
 */
class DocumentUploadModuleTest extends TestCase
{
    private const PDF_FIELDS = ['soi', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance', 'ofr', 'medical_clearance', 'pft_clearance', 'residential_sketch'];
    private const IMAGE_FIELDS = ['whole_body_picture', 'photo_2x2'];
    private const SECTIONS_201 = ['soi', 'appointment', 'leave', 'awards', 'disciplinary', 'performance', 'legal', 'academic', 'financial', 'retirement', 'body_picture'];

    private string $root;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->root = storage_path('framework/testing/doc-module');
        File::deleteDirectory($this->root);
        config(['pcg.upload_root' => $this->root]);

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('rank')->nullable();
            $t->string('serial_number')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('personnel_data', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('personnel_id'); $t->string('serial_number')->nullable();
            foreach (array_merge(self::PDF_FIELDS, self::IMAGE_FIELDS) as $c) {
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
        Schema::create('personnel_201_files', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->unsignedBigInteger('personnel_id'); $t->string('serial_number')->nullable();
            foreach (self::SECTIONS_201 as $c) {
                $t->string($c)->nullable();
            }
        });
        Schema::create('reinlist_basic_info', function (Blueprint $t) {
            $t->id(); $t->string('serial_number');
        });

        $this->member = User::forceCreate(['name' => 'Member', 'email' => 'm@example.test', 'password' => Hash::make('x'), 'serial_number' => 'SN-1', 'rank' => 'PO1']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function row()
    {
        return DB::table('personnel_data')->where('personnel_id', $this->member->id)->first();
    }

    private function pdf(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nsecret-member-content\n%%EOF\n");
    }

    public function test_every_document_uploads_encrypted_with_an_accurate_message()
    {
        $post = [];
        foreach (self::PDF_FIELDS as $f) {
            $post[$f] = $this->pdf($f . '.pdf');
        }
        foreach (self::IMAGE_FIELDS as $f) {
            $post[$f] = UploadedFile::fake()->image($f . '.png', 300, 400);
        }

        $this->actingAs($this->member)->from('/upload-documents')->post('/documents/upload', $post)
            ->assertRedirect('/upload-documents')
            ->assertSessionHas('success', '10 files uploaded and encrypted. Your unit checker will review them.');

        $row = $this->row();
        $this->assertSame('Pending', $row->status);
        foreach (array_merge(self::PDF_FIELDS, self::IMAGE_FIELDS) as $f) {
            $this->assertNotNull($row->$f, $f);
            $this->assertStringNotContainsString($f, $row->$f, 'stored under a random name, not the uploaded one');
            $bytes = File::get($this->root . '/documents_toupload/' . $row->$f);
            $this->assertStringNotContainsString('secret-member-content', $bytes, "$f is encrypted on disk");
            $this->assertStringStartsNotWith('%PDF', $bytes);
        }
    }

    public function test_wrong_types_and_oversized_files_are_refused_by_the_server()
    {
        $this->actingAs($this->member);
        $this->post('/documents/upload', ['nbi_clearance' => UploadedFile::fake()->image('x.png')])->assertSessionHasErrors('nbi_clearance');
        $this->post('/documents/upload', ['photo_2x2' => $this->pdf()])->assertSessionHasErrors('photo_2x2');
        $this->post('/documents/upload', ['soi' => UploadedFile::fake()->create('evil.php', 5, 'application/x-php')])->assertSessionHasErrors('soi');
        $this->post('/documents/upload', ['soi' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('soi');
        $this->post('/documents/upload', ['residential_sketch' => UploadedFile::fake()->image('sketch.jpg')])->assertSessionHasNoErrors();
        $this->assertNull($this->row()->nbi_clearance ?? null);
    }

    public function test_a_save_changes_only_what_changed_and_sends_it_back_for_review()
    {
        DB::table('personnel_data')->insert(['personnel_id' => $this->member->id, 'serial_number' => 'SN-1', 'soi' => 'documents/old.pdf',
            'nbi_clearance_expiration' => '2027-01-01', 'latitude' => 14.5995, 'longitude' => 120.9842, 'status' => 'approved']);
        $this->actingAs($this->member);

        // nothing changed: refused politely, decision kept
        $this->post('/documents/upload', ['nbi_clearance_expiration' => '2027-01-01', 'latitude' => '14.5995000', 'longitude' => '120.9842000'])
            ->assertSessionHas('info');
        $this->assertSame('approved', $this->row()->status);

        // pin moved after approval: the checker reviews the residence again
        $this->post('/documents/upload', ['latitude' => '14.6', 'longitude' => '121.0'])->assertSessionHas('success', 'Changes saved. Your unit checker will review them.');
        $this->assertSame('Pending', $this->row()->status);
        $this->assertEqualsWithDelta(14.6, (float) $this->row()->latitude, 0.000001);

        // a clearance date changed: reviewed again
        DB::table('personnel_data')->update(['status' => 'approved']);
        $this->post('/documents/upload', ['nbi_clearance_expiration' => '2028-06-30'])->assertSessionHas('success', 'Changes saved. Your unit checker will review them.');
        $this->assertSame('Pending', $this->row()->status);
        $this->assertSame('documents/old.pdf', $this->row()->soi, 'other documents untouched');
    }

    public function test_reuploading_a_rejected_document_marks_it_complied()
    {
        DB::table('rejected_documents')->insert(['serial_number' => 'SN-1', 'doc_type' => 'NBI Clearance', 'status' => 'rejected']);
        $this->actingAs($this->member)->post('/documents/upload', ['nbi_clearance' => $this->pdf()])->assertSessionHas('success');
        $this->assertSame('complied', DB::table('rejected_documents')->value('status'));
    }

    public function test_uploads_are_rate_limited()
    {
        $this->actingAs($this->member);
        for ($i = 0; $i < 10; $i++) {
            $this->post('/documents/upload', ['latitude' => (string) (10 + $i), 'longitude' => '120'])->assertStatus(302);
        }
        $this->post('/documents/upload', ['latitude' => '1', 'longitude' => '120'])->assertStatus(429);
    }

    public function test_page_shows_status_of_each_document()
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-08 09:00', 'Asia/Manila'));
        DB::table('personnel_data')->insert(['personnel_id' => $this->member->id, 'serial_number' => 'SN-1',
            'nbi_clearance' => 'documents/a.pdf', 'nbi_clearance_expiration' => '2026-10-01',
            'ombudsman_clearance' => 'documents/b.pdf', 'ombudsman_clearance_expiration' => '2026-11-20',
            'sandiganbayan_clearance' => 'documents/c.pdf', 'sandiganbayan_clearance_expiration' => '2027-06-01',
            'soi' => 'documents/d.pdf']);

        $html = $this->actingAs($this->member)->get('/upload-documents')->assertOk()->getContent();
        $this->assertStringContainsString('<strong>4 of 10</strong>', $html, 'progress counts uploaded documents');
        $this->assertMatchesRegularExpression('/ud-doc--expired">\s*<div class="ud-doc__head">\s*<h3 id="lbl-nbi_clearance"/', $html);
        $this->assertMatchesRegularExpression('/ud-doc--soon">\s*<div class="ud-doc__head">\s*<h3 id="lbl-ombudsman_clearance"/', $html);
        $this->assertMatchesRegularExpression('/ud-doc--ok">\s*<div class="ud-doc__head">\s*<h3 id="lbl-sandiganbayan_clearance"/', $html);
        $this->assertMatchesRegularExpression('/ud-doc--missing">\s*<div class="ud-doc__head">\s*<h3 id="lbl-ofr"/', $html);
        $this->assertStringContainsString('AES-256', $html);
        $this->assertStringContainsString('aria-current="page"', $html, 'sidebar marks this page');
        $this->assertMatchesRegularExpression('/upload-documents\.js\?v=[0-9a-f]{10}/', $html, 'versioned script URL');
        $this->assertStringNotContainsString('class="ud-overlay is-on"', $html, 'overlay never starts visible');
    }

    public function test_201_files_upload_encrypted_with_an_accurate_message()
    {
        $post = [];
        foreach (self::SECTIONS_201 as $i => $f) {
            $post[$f] = $i % 2 ? UploadedFile::fake()->image($f . '.jpg') : $this->pdf($f . '.pdf');
        }
        $this->actingAs($this->member)->post('/upload-201', $post)
            ->assertRedirect(route('upload.201'))
            ->assertSessionHas('success', '11 files uploaded and encrypted to your 201 file.');

        $row = DB::table('personnel_201_files')->where('personnel_id', $this->member->id)->first();
        foreach (self::SECTIONS_201 as $f) {
            $this->assertStringStartsWith('201_files/' . $f . '/', $row->$f);
            $this->assertStringNotContainsString('secret-member-content', File::get($this->root . '/' . $row->$f), "$f encrypted on disk");
        }
    }

    public function test_201_files_refuse_wrong_types_oversize_and_empty_submits()
    {
        $this->actingAs($this->member);
        $this->post('/upload-201', ['legal' => UploadedFile::fake()->create('evil.php', 5, 'application/x-php')])->assertSessionHasErrors('legal');
        $this->post('/upload-201', ['legal' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('legal');
        $this->post('/upload-201', [])->assertSessionHasErrors('files');
        $this->assertSame(0, DB::table('personnel_201_files')->count());
    }

    public function test_201_page_shows_each_section_with_the_sidebar()
    {
        DB::table('personnel_201_files')->insert(['user_id' => $this->member->id, 'personnel_id' => $this->member->id, 'serial_number' => 'SN-1',
            'soi' => '201_files/soi/a.pdf', 'awards' => '201_files/awards/b.pdf']);

        $html = $this->actingAs($this->member)->get('/upload-201')->assertOk()->getContent();
        $this->assertStringContainsString('<strong>2 of 11</strong>', $html);
        $this->assertMatchesRegularExpression('/ud-doc--ok">\\s*<div class="ud-doc__head">\\s*<h3 id="lbl-awards"/', $html);
        $this->assertMatchesRegularExpression('/ud-doc--missing">\\s*<div class="ud-doc__head">\\s*<h3 id="lbl-legal"/', $html);
        $this->assertStringContainsString('AES-256', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*upload-201"[^>]*aria-current="page"|aria-current="page"[^>]*href="[^"]*upload-201"/', $html, 'sidebar marks 201 files');
        $this->assertMatchesRegularExpression('/upload-documents\\.js\\?v=[0-9a-f]{10}/', $html);
    }
}
