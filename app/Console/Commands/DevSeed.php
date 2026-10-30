<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Fills a DEVELOPMENT database with clearly-marked sample data so every
 * page can be exercised without the real pcg_core data.
 *
 * Refuses to run unless APP_ENV=local and the database name ends in "_dev".
 * Load the schema first: database/dev/schema_inferred.sql
 */
class DevSeed extends Command
{
    protected $signature = 'pcg:dev-seed';

    protected $description = 'Insert sample data into a *_dev database (never the real pcg_core)';

    public const PASSWORD = 'Demo@12345';

    // Development only: fixed authenticator secret so scripts can compute codes.
    public const ADMIN_TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    public const ADMIN_PASSWORD = 'Admin@Demo12345';

    public function handle()
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (!app()->environment('local') || !str_ends_with($database, '_dev')) {
            $this->error("Refusing to seed '$database': requires APP_ENV=local and a database name ending in _dev.");
            return 1;
        }

        if (DB::table('users')->where('email', 'member@demo.test')->exists()) {
            $this->info('Sample data already present.');
            return 0;
        }

        $now = now();
        $hash = Hash::make(self::PASSWORD);
        $units = ['MEPLEF', 'NOCOP', 'MEPTDI', 'MEPG', 'MSFIS', 'MEPRCGS'];
        $ranks = ['SN1', 'SN2', 'PO1', 'PO2', 'PO3', 'CPO', 'ENS', 'LTJG'];
        $first = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Liza', 'Ramon', 'Carla', 'Mark', 'Joy', 'Paolo', 'Grace'];
        $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Aquino', 'Castillo', 'Navarro'];

        DB::transaction(function () use ($now, $hash, $units, $ranks, $first, $last) {
            // ---------------- users ----------------
            $users = [];
            foreach ($first as $i => $fn) {
                $serial = sprintf('DEMO-%04d', $i + 1);
                $unit = $units[$i % count($units)];
                $status = $i === 0 ? 'Approved' : ['Approved', 'Approved', 'Pending', 'Rejected'][$i % 4];
                $id = DB::table('users')->insertGetId([
                    'name' => "$fn {$last[$i]}",
                    'email' => $i === 0 ? 'member@demo.test' : strtolower($fn) . ".{$last[$i]}@demo.test",
                    'password' => $hash,
                    'rank' => $ranks[$i % count($ranks)],
                    'serial_number' => $serial,
                    'registration_type' => $i % 3 === 0 ? 'officer' : 'non_officer',
                    'date_of_birth' => $now->copy()->subYears(24 + $i)->format('Y-m-d'),
                    'date_of_appointment' => $now->copy()->subYears(2 + $i % 5)->format('Y-m-d'),
                    'ete' => $now->copy()->addYears(1 + $i % 3)->format('Y-m-d'),
                    'mother_unit' => $unit,
                    'sub_unit' => 'Sample Sub-unit',
                    'unit_assignment' => "$unit - Sample Sub-unit",
                    'command' => 'MEPCOM',
                    'unit' => $unit,
                    'status' => $status,
                    'created_at' => $now->copy()->subMonths(11 - $i % 12),
                    'updated_at' => $now,
                ]);
                $users[] = ['id' => $id, 'serial' => $serial, 'name' => "$fn {$last[$i]}", 'first' => $fn, 'last' => $last[$i], 'rank' => $ranks[$i % count($ranks)], 'unit' => $unit];
            }

            // ---------------- admin accounts (2FA enrolled with the dev secret) ----------------
            if (\Illuminate\Support\Facades\Schema::hasTable('admin_users')) {
                foreach ([['admin@demo.test', 'Sample Super Admin', 'super_admin'], ['hr@demo.test', 'Sample HR Admin', 'hr_admin'], ['lms@demo.test', 'Sample LMS Admin', 'lms_admin']] as [$email, $name, $role]) {
                    $admin = \App\Models\AdminUser::create(['name' => $name, 'email' => $email, 'password' => Hash::make(self::ADMIN_PASSWORD), 'role' => $role, 'is_active' => true]);
                    $admin->two_factor_secret = self::ADMIN_TOTP_SECRET;
                    $admin->two_factor_confirmed_at = $now;
                    $admin->regenerateRecoveryCodes();
                    $admin->save();
                }
            }

            // ---------------- unit checkers ----------------
            foreach ([['unit@demo.test', 'Sample Unit Checker', 'MEPLEF', 'unit_checker'], ['m1@demo.test', 'Sample M1 Checker', 'MEPCOM', 'm1_checker']] as [$email, $name, $unit, $role]) {
                DB::table('unit_users')->insert([
                    'user_id' => 'DEMO-UNIT', 'personnel_id' => 'DEMO-UNIT', 'serial_number' => null,
                    'full_name' => $name, 'email' => $email, 'username' => strstr($email, '@', true),
                    'password' => $hash, 'role' => $role, 'unit_name' => $unit, 'unit_code' => $unit,
                    'permissions' => json_encode([]), 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            // ---------------- documents + files ----------------
            $root = config('pcg.upload_root');
            $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 300 144]/Parent 2 0 R/Resources<<>>>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkqPpfDwAEhgHBDtpHwQAAAABJRU5ErkJggg==');

            foreach (array_slice($users, 0, 8) as $k => $u) {
                $row = ['personnel_id' => $u['id'], 'serial_number' => $u['serial'], 'status' => $k % 3 === 2 ? 'approved' : 'Pending', 'created_at' => $now, 'updated_at' => $now,
                    'nbi_clearance_expiration' => $now->copy()->addMonths(6 - $k)->format('Y-m-d'),
                    'ombudsman_clearance_expiration' => $now->copy()->addMonths(8)->format('Y-m-d'),
                    'sandiganbayan_clearance_expiration' => $now->copy()->addMonths(3)->format('Y-m-d'),
                    'latitude' => 14.5995 + $k / 100, 'longitude' => 120.9842 + $k / 100];
                foreach (['soi', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance', 'medical_clearance', 'pft_clearance'] as $field) {
                    $rel = "documents/demo_{$u['serial']}_$field.pdf";
                    File::ensureDirectoryExists("$root/documents_toupload/documents");
                    File::put("$root/documents_toupload/$rel", $pdf);
                    $row[$field] = $rel;
                }
                foreach (['photo_2x2', 'whole_body_picture'] as $field) {
                    $rel = "photos/demo_{$u['serial']}_$field.png";
                    File::ensureDirectoryExists("$root/documents_toupload/photos");
                    File::put("$root/documents_toupload/$rel", $png);
                    $row[$field] = $rel;
                }
                DB::table('personnel_data')->insert($row);
            }

            $member = $users[0];
            DB::table('rejected_documents')->insert([
                'serial_number' => $member['serial'], 'doc_type' => 'Medical', 'comment' => 'Sample: medical certificate is unreadable, please re-upload.',
                'status' => 'rejected', 'created_at' => $now, 'updated_at' => $now,
            ]);

            File::ensureDirectoryExists("$root/201_files/soi");
            File::put("$root/201_files/soi/demo_{$member['serial']}_soi.pdf", $pdf);
            DB::table('personnel_201_files')->insert([
                'user_id' => $member['id'], 'personnel_id' => $member['id'], 'serial_number' => $member['serial'],
                'soi' => "201_files/soi/demo_{$member['serial']}_soi.pdf", 'created_at' => $now, 'updated_at' => $now,
            ]);

            File::ensureDirectoryExists("$root/disciplinary_actions/disciplinary_actions");
            File::put("$root/disciplinary_actions/disciplinary_actions/demo_case.pdf", $pdf);
            DB::table('reinlist_disciplinary_actions')->insert([
                'serial_number' => $users[1]['serial'], 'file_path' => 'disciplinary_actions/demo_case.pdf', 'created_at' => $now, 'updated_at' => $now,
            ]);

            // ---------------- SOI (reinlist) for every user ----------------
            foreach ($users as $u) {
                DB::table('reinlist_basic_info')->insert([
                    'serial_number' => $u['serial'], 'last_name' => $u['last'], 'first_name' => $u['first'], 'middle_name' => 'Sample',
                    'rank' => $u['rank'], 'class' => 'Class 2020', 'birthday' => '1995-05-15', 'birth_place' => 'Sample City',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('reinlist_personal_info')->insert([
                    'personnel_id' => "MEPCOM-{$u['serial']}-2026", 'serial_number' => $u['serial'], 'sex' => 'Male', 'civil_status' => 'Single',
                    'citizenship' => 'Filipino', 'blood_type' => 'O+', 'height' => '170', 'weight' => '68', 'contact_number' => '09000000000',
                    'email' => strtolower($u['first']) . '@demo.test', 'home_address' => 'Sample Street, Sample City',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('reinlist_academic')->insert([
                    'serial_number' => $u['serial'], 'level' => 'tertiary', 'course' => ['BS Marine Biology', 'BS Criminology', 'BS Information Technology', 'BS Nautical Science'][$u['id'] % 4],
                    'school' => 'Sample University', 'year_start' => '2012', 'year_end' => '2016', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('reinlist_dependents')->insert([
                'personnel_id' => "MEPCOM-{$member['serial']}-2026", 'serial_number' => $member['serial'], 'name' => 'Sample Dependent',
                'relationship' => 'Daughter', 'birthdate' => '2019-03-03', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('reinlist_trainings')->insert([
                'serial_number' => $member['serial'], 'category' => 'coastguard', 'course' => 'Sample Coast Guard Course',
                'authority' => 'Sample Authority', 'school' => 'Sample School', 'period' => '2021', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('reinlist_duties')->insert([
                'serial_number' => $member['serial'], 'applicant_id' => $member['serial'], 'type' => 'shorebased', 'unit' => 'MEPLEF',
                'position' => 'Sample Position', 'period' => '2022-2024', 'authority' => 'Sample Order', 'created_at' => $now, 'updated_at' => $now,
            ]);

            // ---------------- training tracks ----------------
            $courses = ['Sample Leadership Course', 'Sample Supervisory Course', 'Sample Basic Course'];
            foreach (['administrative_tracks', 'operation_tracks'] as $t => $table) {
                foreach ($courses as $c => $course) {
                    DB::table($table)->insert([
                        // Tracks use equivalent ranks (ApplicantTrainingController::getEquivalentRank).
                        'no' => $c + 1, 'division_unit' => $units[$c], 'rank' => ['E-3', 'E-6', 'O-1'][$c], 'year_in_grade' => '1',
                        'course_training' => ($t ? 'Operational: ' : 'Administrative: ') . $course, 'provider' => 'MEPTDI',
                        'status' => 'Active', 'remarks' => 'Sample', 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            foreach (['SN2' => 'E-2', 'SN1' => 'E-3', 'PO3' => 'E-4', 'PO2' => 'E-5', 'PO1' => 'E-6', 'ENS' => 'O-1', 'LTJG' => 'O-2'] as $rank => $trackRank) {
                DB::table('rank_equivalencies')->insert(['user_rank' => $rank, 'track_rank' => $trackRank, 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('training_applications')->insert([
                ['serial_number' => $member['serial'], 'training_type' => 'ACT', 'track_id' => 1, 'course_training' => 'Administrative: ' . $courses[0],
                    'rank' => $member['rank'], 'division_unit' => $member['unit'], 'year_in_grade' => '2', 'provider' => 'MEPTDI', 'remarks' => null,
                    'status' => 'Pending', 'applied_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['serial_number' => $users[1]['serial'], 'training_type' => 'OCT', 'track_id' => 1, 'course_training' => 'Operational: ' . $courses[0],
                    'rank' => $users[1]['rank'], 'division_unit' => $users[1]['unit'], 'year_in_grade' => '3', 'provider' => 'MEPTDI', 'remarks' => null,
                    'status' => 'Approved', 'applied_at' => $now->copy()->subMonth(), 'created_at' => $now, 'updated_at' => $now],
            ]);

            // ---------------- LMS ----------------
            foreach ($courses as $c => $course) {
                $courseId = DB::table('training_courses')->insertGetId([
                    'course_name' => $course, 'description' => 'Sample course for development.', 'status' => $c === 2 ? 'inactive' : 'active',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach (array_slice($users, $c * 3, 3) as $u) {
                    DB::table('course_students')->insert([
                        'course_id' => $courseId, 'user_id' => $u['id'], 'serial_number' => $u['serial'], 'full_name' => $u['name'],
                        'date_attended' => $now->copy()->subMonths(2)->format('Y-m-d'), 'date_ended' => $now->copy()->subMonth()->format('Y-m-d'),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            DB::table('courses')->insert([['course_description' => 'BS Marine Biology'], ['course_description' => 'BS Criminology']]);

            // OSAERT dates are free text in the real data (both formats occur).
            foreach ([['Sample Trainee A', 'Sample Shipping Co.', '03/02/2025', '07/02/2025'], ['Sample Trainee B', 'Sample Port Services', '2025-06-10', '2025-06-14'], ['Sample Trainee C', 'Sample Marine Corp.', '15/01/2026', '']] as [$name, $company, $start, $end]) {
                DB::table('osaert_trainees')->insert([
                    'name' => $name, 'company' => $company, 'date_attended' => $start, 'date_ended' => $end, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });

        $this->info('Sample data inserted. Logins (password ' . self::PASSWORD . '):');
        $this->line('  personnel   member@demo.test   (/login)');
        $this->line('  unit        unit@demo.test     (/unit/login)');
        $this->line('  M1          m1@demo.test       (/unit/loginM1)');
        $this->line('  admins      admin@ / hr@ / lms@demo.test, password ' . self::ADMIN_PASSWORD . ', authenticator key ' . self::ADMIN_TOTP_SECRET);

        return 0;
    }
}
