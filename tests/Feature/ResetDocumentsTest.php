<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** pcg:reset-documents: clears one member's required documents so they upload them again. */
class ResetDocumentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('serial_number')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::create('personnel_data', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('personnel_id')->nullable(); $t->string('serial_number')->nullable();
            foreach (\App\Support\DocumentReview::FILE_FIELDS as $c) { $t->string($c)->nullable(); }
            foreach (['nbi_clearance_expiration', 'ombudsman_clearance_expiration', 'sandiganbayan_clearance_expiration'] as $c) { $t->date($c)->nullable(); }
            $t->decimal('latitude', 10, 7)->nullable(); $t->decimal('longitude', 10, 7)->nullable();
            $t->string('status')->nullable(); $t->timestamps();
        });
        Schema::create('rejected_documents', function (Blueprint $t) {
            $t->id(); $t->string('serial_number'); $t->string('doc_type'); $t->text('comment')->nullable(); $t->string('status')->nullable(); $t->timestamps();
        });
        DB::table('users')->insert(['name' => 'Member', 'email' => 'm@x.test', 'password' => 'x', 'serial_number' => '026713', 'status' => 'Approved']);
        DB::table('personnel_data')->insert([
            ['personnel_id' => 1, 'serial_number' => '026713', 'soi' => 'documents/a.pdf', 'nbi_clearance' => 'documents/n.pdf',
                'nbi_clearance_expiration' => null, 'latitude' => 14.5, 'longitude' => 121.0, 'status' => 'approved'],
            ['personnel_id' => 2, 'serial_number' => '999999', 'soi' => 'documents/other.pdf', 'nbi_clearance' => null,
                'nbi_clearance_expiration' => null, 'latitude' => null, 'longitude' => null, 'status' => 'approved'],
        ]);
        DB::table('rejected_documents')->insert(['serial_number' => '026713', 'doc_type' => 'Sandiganbayan', 'status' => 'rejected']);
    }

    public function test_dry_run_changes_nothing()
    {
        $this->artisan('pcg:reset-documents', ['serial' => '026713'])->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame('documents/a.pdf', DB::table('personnel_data')->where('serial_number', '026713')->value('soi'));
    }

    public function test_apply_needs_the_typed_confirmation_and_clears_only_that_member()
    {
        $this->artisan('pcg:reset-documents', ['serial' => '026713', '--apply' => true])
            ->expectsQuestion('Type RESET 026713 to clear these documents', 'yes')->assertFailed();
        $this->assertSame('documents/a.pdf', DB::table('personnel_data')->where('serial_number', '026713')->value('soi'));

        $this->artisan('pcg:reset-documents', ['serial' => '026713', '--apply' => true, '--reason' => 'no valid-until dates'])
            ->expectsQuestion('Type RESET 026713 to clear these documents', 'RESET 026713')->assertSuccessful();

        $row = DB::table('personnel_data')->where('serial_number', '026713')->first();
        foreach (\App\Support\DocumentReview::FILE_FIELDS as $c) {
            $this->assertNull($row->$c, $c);
        }
        $this->assertNull($row->latitude);
        $this->assertSame('rejected', $row->status, 'returned: the member can upload again');
        $this->assertSame([], \App\Support\DocumentReview::lockedFields($row), 'every box opens');
        $this->assertSame('cleared', DB::table('rejected_documents')->value('status'));
        $this->assertSame('documents/other.pdf', DB::table('personnel_data')->where('serial_number', '999999')->value('soi'), 'other members untouched');
    }

    public function test_unknown_serial_is_reported()
    {
        $this->artisan('pcg:reset-documents', ['serial' => '000000'])->expectsOutputToContain('Nothing to clear')->assertSuccessful();
    }
}
