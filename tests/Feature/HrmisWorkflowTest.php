<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\DocumentCategory;
use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\PersonnelDocument;
use App\Models\PersonnelRegistration;
use App\Models\Rank;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Registration workflow, roles, 201 documents, promotions, training,
 * controlled editing and reports, including organisational isolation.
 */
class HrmisWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private OrgUnit $mepcom;
    private OrgUnit $mssc;
    private string $docRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('email'); $t->string('password');
                $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->timestamps();
            });
        }

        $this->mepcom = OrgUnit::where('code', 'MEPCOM')->first();
        $this->mssc = OrgUnit::where('code', 'MSSC')->first();
        $this->docRoot = sys_get_temp_dir() . '/pcg-docs-' . uniqid();
        config(['pcg.document_root' => $this->docRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->docRoot);
        parent::tearDown();
    }

    /** Session of a real admin account (the session middleware re-reads it from the database). */
    private function as(string $role, ?OrgUnit $scope = null, string $email = null): array
    {
        $admin = AdminUser::create([
            'name' => $role, 'email' => $email ?? $role . uniqid() . '@example.test', 'password' => Hash::make('x'),
            'role' => $role, 'org_unit_id' => $scope?->id,
        ]);
        $this->flushSession();

        return ['admin_logged_in' => true, 'admin_role' => $role, 'admin_user_id' => $admin->id, 'admin_email' => $admin->email, 'admin_org_unit_id' => $scope?->id];
    }

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'serial_number' => 'mep-100', 'last_name' => 'Santos', 'first_name' => 'Maria',
            'rank_id' => Rank::where('code', 'LT')->value('id'), 'org_unit_id' => $this->mepcom->id,
            'assignment_start_date' => '2025-02-01', 'date_of_birth' => '1990-05-05',
        ], $overrides);
    }

    private function person(string $serial, OrgUnit $unit): Personnel
    {
        $p = Personnel::create(['serial_number' => $serial, 'full_name' => "Person {$serial}", 'rank_id' => Rank::where('code', 'PO2')->value('id'), 'classification' => 'non_officer']);
        PersonnelAssignment::create(['personnel_id' => $p->id, 'org_unit_id' => $unit->id, 'start_date' => '2024-01-01']);

        return $p;
    }

    // ------------------------------------------------------------ registrations

    public function test_registration_goes_draft_submit_approve_and_creates_one_record()
    {
        $hr = $this->as('hr_admin', $this->mepcom);
        $this->withSession($hr)->post('/admin/registrations', $this->registration(['intent' => 'draft']))->assertRedirect();
        $reg = PersonnelRegistration::firstOrFail();
        $this->assertSame('draft', $reg->status);
        $this->assertSame('MEP-100', $reg->serial_number);
        $this->assertSame(0, Personnel::count()); // no automatic approval

        $this->withSession($hr)->post("/admin/registrations/{$reg->id}/submit")->assertSessionHas('success');
        $this->assertSame('submitted', $reg->fresh()->status);

        // the author cannot approve their own registration
        $this->withSession($hr)->post("/admin/registrations/{$reg->id}/decide", ['action' => 'approve'])->assertSessionHasErrors('status');
        $this->assertSame(0, Personnel::count());

        // a reviewer cannot approve at all
        $reviewer = $this->as('records_reviewer', $this->mepcom);
        $this->withSession($reviewer)->post("/admin/registrations/{$reg->id}/decide", ['action' => 'approve'])->assertForbidden();
        $this->withSession($reviewer)->post("/admin/registrations/{$reg->id}/review", ['action' => 'endorse'])->assertSessionHas('success');

        $approver = $this->as('records_approver', $this->mepcom);
        $this->withSession($approver)->post("/admin/registrations/{$reg->id}/decide", ['action' => 'approve', 'remarks' => 'OK'])->assertRedirect();

        $person = Personnel::firstOrFail();
        $this->assertSame('MEP-100', $person->serial_number);
        $this->assertSame('officer', $person->classification);
        $this->assertSame($this->mepcom->id, $person->currentAssignment->org_unit_id);
        $this->assertSame('approved', $reg->fresh()->status);
        $this->assertSame($person->id, $reg->fresh()->personnel_id);
        $this->assertSame(['created', 'submitted', 'endorsed', 'approved'], $reg->events()->pluck('action')->all());

        // an approved registration cannot be decided again
        $other = $this->as('hr_admin');
        $this->withSession($other)->post("/admin/registrations/{$reg->id}/decide", ['action' => 'reject', 'remarks' => 'x'])->assertSessionHasErrors('status');
        $this->assertSame(1, Personnel::count());
    }

    public function test_return_for_correction_and_rejection_need_remarks()
    {
        $hr = $this->as('hr_admin');
        $this->withSession($hr)->post('/admin/registrations', $this->registration(['intent' => 'submit']));
        $reg = PersonnelRegistration::firstOrFail();
        $this->assertSame('submitted', $reg->status);

        $reviewer = $this->as('records_reviewer');
        $this->withSession($reviewer)->post("/admin/registrations/{$reg->id}/review", ['action' => 'return'])->assertSessionHasErrors('remarks');
        $this->withSession($reviewer)->post("/admin/registrations/{$reg->id}/review", ['action' => 'return', 'remarks' => 'Fix the birth date'])->assertSessionHas('success');
        $this->assertSame('returned', $reg->fresh()->status);

        // correction and resubmission by HR
        $this->withSession($hr)->post("/admin/registrations/{$reg->id}", $this->registration(['date_of_birth' => '1991-05-05', 'intent' => 'submit']));
        $this->assertSame('submitted', $reg->fresh()->status);

        $approver = $this->as('records_approver');
        $this->withSession($approver)->post("/admin/registrations/{$reg->id}/decide", ['action' => 'reject'])->assertSessionHasErrors('remarks');
        $this->withSession($approver)->post("/admin/registrations/{$reg->id}/decide", ['action' => 'reject', 'remarks' => 'Not PCG personnel']);
        $this->assertSame('rejected', $reg->fresh()->status);
        $this->assertSame(0, Personnel::count());
    }

    public function test_duplicates_block_submission_and_approval()
    {
        $this->person('MEP-100', $this->mepcom);
        $hr = $this->as('hr_admin');

        $this->withSession($hr)->post('/admin/registrations', $this->registration(['intent' => 'submit']))
            ->assertSessionHasErrors('serial_number');
        $reg = PersonnelRegistration::firstOrFail();
        $this->assertSame('draft', $reg->status); // saved, not submitted

        // same name and birth date under another serial needs the explicit confirmation
        Personnel::where('serial_number', 'MEP-100')->update(['last_name' => 'Cruz', 'first_name' => 'Ana', 'date_of_birth' => '1990-01-01']);
        $this->withSession($hr)->post('/admin/registrations', $this->registration(['serial_number' => 'NEW-1', 'last_name' => 'Cruz', 'first_name' => 'Ana', 'date_of_birth' => '1990-01-01', 'intent' => 'submit']))
            ->assertSessionHasErrors('confirmed_not_duplicate');
    }

    public function test_registrations_are_scoped_by_proposed_unit()
    {
        $hrMssc = $this->as('hr_admin', $this->mssc);
        $this->withSession($hrMssc)->post('/admin/registrations', $this->registration(['org_unit_id' => $this->mepcom->id]))
            ->assertSessionHasErrors('org_unit_id');

        $hrMep = $this->as('hr_admin', $this->mepcom);
        $this->withSession($hrMep)->post('/admin/registrations', $this->registration());
        $reg = PersonnelRegistration::firstOrFail();

        $this->withSession($hrMssc)->get("/admin/registrations/{$reg->id}")->assertNotFound();
        $this->withSession($hrMssc)->get('/admin/registrations')->assertDontSee('MEP-100');
        $approverMssc = $this->as('records_approver', $this->mssc);
        $this->withSession($approverMssc)->post("/admin/registrations/{$reg->id}/decide", ['action' => 'approve'])->assertNotFound();
    }

    // ------------------------------------------------------------ roles

    public function test_auditor_reads_but_changes_nothing()
    {
        $p = $this->person('MEP-1', $this->mepcom);
        $auditor = $this->as('auditor');

        $this->withSession($auditor)->get('/admin/personnel')->assertOk();
        $this->withSession($auditor)->get("/admin/personnel/{$p->id}")->assertOk()->assertDontSee('Record an assignment')->assertDontSee('Edit details');
        $this->withSession($auditor)->get('/admin/reports?type=roster')->assertOk();
        $this->withSession($auditor)->get('/admin/accounts/audit-log')->assertOk();
        $this->withSession($auditor)->post("/admin/personnel/{$p->id}/assignments", ['org_unit_id' => $this->mssc->id, 'start_date' => '2025-01-01'])->assertForbidden();
        $this->withSession($auditor)->post('/admin/registrations', $this->registration())->assertForbidden();
        $this->withSession($auditor)->get('/admin/accounts')->assertForbidden();
        $this->withSession($auditor)->get('/admin/dashboard')->assertForbidden();
    }

    // ------------------------------------------------------------ 201 documents

    public function test_documents_are_private_scoped_versioned_and_verified_by_someone_else()
    {
        $ours = $this->person('MEP-1', $this->mepcom);
        $theirs = $this->person('MSS-1', $this->mssc);
        $cat = DocumentCategory::where('code', 'promotion_order')->first();
        $hr = $this->as('hr_admin', $this->mepcom);

        $this->withSession($hr)->post("/admin/personnel/{$ours->id}/documents", [
            'category_id' => $cat->id, 'title' => 'GO-1', 'file' => UploadedFile::fake()->create('order.pdf', 20, 'application/pdf'),
        ])->assertSessionHas('success');
        $doc = PersonnelDocument::firstOrFail();
        $this->assertFileExists($this->docRoot . '/' . $doc->stored_path);
        $this->assertStringNotContainsString('order', $doc->stored_path); // random name
        $this->assertStringStartsNotWith(public_path(), config('pcg.document_root'));

        // wrong type and out-of-scope person are refused
        $this->withSession($hr)->post("/admin/personnel/{$ours->id}/documents", [
            'category_id' => $cat->id, 'title' => 'x', 'file' => UploadedFile::fake()->create('evil.php', 5, 'application/x-php'),
        ])->assertSessionHasErrors('file');
        $this->withSession($hr)->post("/admin/personnel/{$theirs->id}/documents", [
            'category_id' => $cat->id, 'title' => 'x', 'file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'),
        ])->assertNotFound();

        // download: in scope works and is logged; another command gets 404
        $this->withSession($hr)->get("/admin/documents/{$doc->id}/download")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(1, DB::table('document_access_logs')->where('document_id', $doc->id)->where('action', 'download')->count());
        $this->withSession($this->as('hr_admin', $this->mssc))->get("/admin/documents/{$doc->id}/download")->assertNotFound();

        // uploader cannot verify; another admin can
        $this->withSession($hr)->post("/admin/documents/{$doc->id}/verify", ['status' => 'verified'])->assertSessionHas('error');
        $this->withSession($this->as('records_reviewer', $this->mepcom))->post("/admin/documents/{$doc->id}/verify", ['status' => 'verified'])->assertSessionHas('success');
        $this->assertSame('verified', $doc->fresh()->status);

        // a replacement becomes version 2; version 1 is kept
        $this->withSession($hr)->post("/admin/personnel/{$ours->id}/documents", [
            'category_id' => $cat->id, 'title' => 'GO-1 corrected', 'replaces_id' => $doc->id,
            'file' => UploadedFile::fake()->create('order2.pdf', 20, 'application/pdf'),
        ]);
        $this->assertSame(2, PersonnelDocument::count());
        $this->assertFalse($doc->fresh()->is_current);
        $this->assertSame(2, PersonnelDocument::where('is_current', true)->value('version'));
    }

    public function test_restricted_documents_need_the_restricted_permission()
    {
        $p = $this->person('MEP-1', $this->mepcom);
        $medical = DocumentCategory::where('code', 'medical')->first();
        $approver = $this->as('records_approver');

        $this->withSession($approver)->get("/admin/personnel/{$p->id}"); // approver may see medical, but cannot upload
        $hr = $this->as('hr_admin');
        $this->withSession($hr)->post("/admin/personnel/{$p->id}/documents", [
            'category_id' => $medical->id, 'title' => 'Medical', 'file' => UploadedFile::fake()->create('m.pdf', 5, 'application/pdf'),
        ])->assertSessionHasErrors('category_id');

        $super = $this->as('super_admin');
        $this->withSession($super)->post("/admin/personnel/{$p->id}/documents", [
            'category_id' => $medical->id, 'title' => 'Medical', 'file' => UploadedFile::fake()->create('m.pdf', 5, 'application/pdf'),
        ])->assertSessionHas('success');
        $doc = PersonnelDocument::firstOrFail();

        $this->withSession($hr)->get("/admin/documents/{$doc->id}/download")->assertNotFound();
        $this->withSession($hr)->get("/admin/personnel/{$p->id}")->assertDontSee('Medical</strong>', false)->assertSee('1 restricted document');
        $this->withSession($approver)->get("/admin/documents/{$doc->id}/download")->assertOk();
    }

    // ------------------------------------------------------------ career records

    public function test_promotion_keeps_rank_history_and_editing_never_touches_serial_or_rank()
    {
        $p = $this->person('MEP-1', $this->mepcom);
        $hr = $this->as('hr_admin', $this->mepcom);
        $po1 = Rank::where('code', 'PO1')->first();

        $this->withSession($hr)->post("/admin/personnel/{$p->id}/promotions", ['to_rank_id' => $po1->id, 'effective_date' => '2025-07-01', 'order_reference' => 'PO-55'])
            ->assertSessionHas('success');
        $this->assertSame($po1->id, $p->fresh()->rank_id);
        $this->assertSame(Rank::where('code', 'PO2')->value('id'), DB::table('promotions')->value('from_rank_id'));

        $this->withSession($hr)->post("/admin/personnel/{$p->id}/promotions", ['to_rank_id' => $po1->id, 'effective_date' => '2025-08-01'])->assertSessionHasErrors('to_rank_id');
        $this->withSession($hr)->post("/admin/personnel/{$p->id}/promotions", ['to_rank_id' => Rank::where('code', 'CPO')->value('id'), 'effective_date' => '2025-06-01'])
            ->assertSessionHasErrors('effective_date');
        $this->assertSame(1, DB::table('promotions')->count());

        $this->withSession($hr)->post("/admin/personnel/{$p->id}", [
            'last_name' => 'Reyes', 'first_name' => 'Jo', 'service_status' => 'active', 'serial_number' => 'HACK', 'rank_id' => 1,
        ])->assertSessionHasErrors('change_reason');
        $this->withSession($hr)->post("/admin/personnel/{$p->id}", [
            'last_name' => 'Reyes', 'first_name' => 'Jo', 'service_status' => 'active', 'serial_number' => 'HACK', 'rank_id' => 1, 'change_reason' => 'Correction',
        ])->assertSessionHas('success');
        $fresh = $p->fresh();
        $this->assertSame('MEP-1', $fresh->serial_number);
        $this->assertSame($po1->id, $fresh->rank_id);
        $this->assertSame('Jo Reyes', $fresh->full_name);
        $this->assertNotNull(DB::table('admin_audit_logs')->where('action', 'personnel.updated')->first());

        $this->withSession($hr)->post("/admin/personnel/{$p->id}/trainings", ['title' => 'Oil Spill Response', 'kind' => 'course', 'end_date' => '2025-03-01'])
            ->assertSessionHas('success');
        $this->assertSame(1, $fresh->trainings()->count());
    }

    // ------------------------------------------------------------ reports

    public function test_reports_respect_scope_and_export_csv()
    {
        $this->person('MEP-1', $this->mepcom);
        $this->person('MSS-1', $this->mssc);
        $hr = $this->as('hr_admin', $this->mepcom);

        $this->withSession($hr)->get('/admin/reports?type=roster')->assertOk()->assertSee('MEP-1')->assertDontSee('MSS-1');
        // a unit filter outside the scope does not widen it
        $this->withSession($hr)->get('/admin/reports?type=roster&unit=' . $this->mssc->id)->assertDontSee('MSS-1');

        $csv = $this->withSession($hr)->get('/admin/reports?type=roster&format=csv');
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('MEP-1', $body);
        $this->assertStringNotContainsString('MSS-1', $body);

        foreach (array_keys(\App\Http\Controllers\ReportController::REPORTS) as $type) {
            $this->withSession($hr)->get("/admin/reports?type={$type}")->assertOk();
        }
    }
}
