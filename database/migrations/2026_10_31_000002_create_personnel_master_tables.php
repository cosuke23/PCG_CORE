<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centralised personnel master (owner decisions 2026-10-31):
 * - ranks: reference list (uniformed personnel only; no civilian ranks),
 *   seeded from the ranks the registration form already offers;
 * - personnel: one permanent record per person, serial number UNIQUE;
 *   linked to the existing login in users (users is not changed);
 * - personnel_assignments: effective-dated assignments to org_units; a
 *   transfer closes the current row, history is never overwritten;
 * - admin_users.org_unit_id: organisational scope of an HR admin
 *   (NULL = organisation-wide).
 *
 * New tables / one nullable column only. Existing data is copied into
 * personnel by `php artisan pcg:personnel-sync --apply`, never here.
 * Rollback refuses to drop tables that contain personnel or assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('classification', 20)->index();   // officer | non_officer
            $table->unsignedSmallInteger('seniority');      // higher = more senior, within classification
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $ranks = [
            ['non_officer', 'ASN', 'Apprentice Seaman/Seawoman'],
            ['non_officer', 'SN2', 'Seaman/Seawoman Second Class'],
            ['non_officer', 'SN1', 'Seaman/Seawoman First Class'],
            ['non_officer', 'PO3', 'Petty Officer Third Class'],
            ['non_officer', 'PO2', 'Petty Officer Second Class'],
            ['non_officer', 'PO1', 'Petty Officer First Class'],
            ['non_officer', 'CPO', 'Chief Petty Officer'],
            ['non_officer', 'SCPO', 'Senior Chief Petty Officer'],
            ['non_officer', 'MCPO', 'Master Chief Petty Officer'],
            ['non_officer', 'FMCPO', 'First Master Chief Petty Officer'],
            ['officer', 'ENS', 'Ensign'],
            ['officer', 'LTJG', 'Lieutenant Junior Grade'],
            ['officer', 'LT', 'Lieutenant'],
            ['officer', 'LCDR', 'Lieutenant Commander'],
            ['officer', 'CDR', 'Commander'],
            ['officer', 'CAPT', 'Captain'],
            ['officer', 'COMMO', 'Commodore'],
            ['officer', 'RADM', 'Rear Admiral'],
            ['officer', 'VADM', 'Vice Admiral'],
            ['officer', 'ADM', 'Admiral'],
        ];
        $seniority = ['officer' => 0, 'non_officer' => 0];
        foreach ($ranks as [$class, $code, $name]) {
            DB::table('ranks')->insert([
                'code' => $code, 'name' => $name, 'classification' => $class,
                'seniority' => ++$seniority[$class], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 100)->unique();   // permanent identifier (owner decision)
            $table->string('last_name', 100)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->string('full_name');                      // as registered; legacy rows have only this
            $table->date('date_of_birth')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('classification', 20)->nullable()->index(); // officer | non_officer
            $table->foreignId('rank_id')->nullable()->constrained('ranks')->restrictOnDelete();
            $table->date('rank_date')->nullable();
            $table->date('date_entered_service')->nullable();
            $table->string('service_status', 30)->default('active')->index();
            $table->unsignedBigInteger('user_id')->nullable()->unique();  // users.id (login), if any
            $table->string('source', 30)->default('manual');  // manual | legacy_users
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('personnel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units')->restrictOnDelete();
            $table->string('position')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();             // NULL = current assignment
            $table->string('order_reference')->nullable();    // transfer / assignment order no.
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->timestamps();
            $table->index(['personnel_id', 'end_date']);
            $table->index(['org_unit_id', 'end_date']);
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->foreignId('org_unit_id')->nullable()->after('role')->constrained('org_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['personnel_assignments', 'personnel'] as $t) {
            if (Schema::hasTable($t) && DB::table($t)->exists()) {
                throw new RuntimeException("{$t} contains records; not dropping it. Export or remove them deliberately first.");
            }
        }

        if (Schema::hasColumn('admin_users', 'org_unit_id')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('org_unit_id');
            });
        }
        Schema::dropIfExists('personnel_assignments');
        Schema::dropIfExists('personnel');
        Schema::dropIfExists('ranks');
    }
};
