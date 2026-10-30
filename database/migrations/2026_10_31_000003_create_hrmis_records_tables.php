<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRMIS records (new tables only):
 * - personnel_registrations + registration_events: controlled registration
 *   workflow (draft -> submitted -> returned/approved/rejected) with history;
 * - document_categories (seeded, configurable) + personnel_documents
 *   (private files, versions, verification, expiry) + document_access_logs;
 * - promotions: rank history (recording one updates personnel.rank_id);
 * - personnel_trainings: completed courses and qualifications.
 *
 * Rollback refuses while any of these tables holds records (except the
 * seeded categories).
 */
return new class extends Migration
{
    private const TABLES = ['document_access_logs', 'personnel_documents', 'registration_events', 'personnel_registrations', 'promotions', 'personnel_trainings'];

    public function up(): void
    {
        Schema::create('personnel_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('draft')->index(); // draft | submitted | returned | approved | rejected
            $table->string('serial_number', 100)->index();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->restrictOnDelete();
            $table->date('rank_date')->nullable();
            $table->date('date_entered_service')->nullable();
            $table->foreignId('org_unit_id')->constrained('org_units')->restrictOnDelete(); // proposed assignment; decides scope
            $table->string('position')->nullable();
            $table->date('assignment_start_date')->nullable();
            $table->string('order_reference')->nullable();
            $table->boolean('confirmed_not_duplicate')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_remarks')->nullable();
            $table->foreignId('personnel_id')->nullable()->constrained('personnel')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('registration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('personnel_registrations')->restrictOnDelete();
            $table->string('action', 30);              // created | updated | submitted | endorsed | returned | approved | rejected
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->string('admin_email')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->boolean('is_restricted')->default(false);   // extra permission to view
            $table->boolean('tracks_expiry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $categories = [
            ['identification', 'Personal identification documents', false, true],
            ['appointment', 'Appointment and service documents', false, false],
            ['promotion_order', 'Promotion orders', false, false],
            ['assignment_order', 'Assignment orders', false, false],
            ['education', 'Educational records', false, false],
            ['training', 'Training certificates', false, true],
            ['performance', 'Performance documents', false, false],
            ['medical', 'Medical records (restricted)', true, true],
            ['other', 'Other personnel documents', false, false],
        ];
        foreach ($categories as $i => [$code, $name, $restricted, $expiry]) {
            DB::table('document_categories')->insert([
                'code' => $code, 'name' => $name, 'is_restricted' => $restricted, 'tracks_expiry' => $expiry,
                'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::create('personnel_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('document_categories')->restrictOnDelete();
            $table->string('title');
            $table->string('stored_path');                       // relative to config('pcg.document_root'); random name
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('personnel_documents')->restrictOnDelete();
            $table->boolean('is_current')->default(true)->index();
            $table->string('status', 20)->default('pending')->index(); // pending | verified | rejected
            $table->text('review_remarks')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->index(['personnel_id', 'category_id']);
        });

        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('personnel_documents')->restrictOnDelete();
            $table->string('action', 20);                        // upload | download | verify | reject
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('from_rank_id')->nullable()->constrained('ranks')->restrictOnDelete();
            $table->foreignId('to_rank_id')->constrained('ranks')->restrictOnDelete();
            $table->date('effective_date');
            $table->string('order_reference')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['personnel_id', 'effective_date']);
        });

        Schema::create('personnel_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->string('title');
            $table->string('kind', 20)->default('course');       // course | qualification
            $table->string('provider')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->date('valid_until')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            if (Schema::hasTable($t) && DB::table($t)->exists()) {
                throw new RuntimeException("{$t} contains records; not dropping it. Export or remove them deliberately first.");
            }
        }
        foreach (self::TABLES as $t) {
            Schema::dropIfExists($t);
        }
        Schema::dropIfExists('document_categories');
    }
};
