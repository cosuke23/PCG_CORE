<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The re-enlistment (SOI) form sections that pcg:dev-seed does not fill:
 * vocational, appointments, career, awards, all CG / military / civilian /
 * other award and letter lists, the reinlist profile, plus profiles and
 * applicants. One or two entries per sample member, marked "Sample".
 */
class ReenlistmentFormSeeder extends DevOnlySeeder
{
    /** Award-style tables: type + authority. */
    private const LISTS = [
        'reinlist_cg_awards_decorations' => [['Coast Guard Bronze Cross Medal', 'CPCG'], ['Coast Guard Merit Medal', 'CPCG']],
        'reinlist_cg_letters' => [['Letter of Commendation', 'Commander, MEPCOM']],
        'reinlist_cg_certificates' => [['Certificate of Recognition', 'Commander, MEPCOM']],
        'reinlist_cg_appreciations' => [['Letter of Appreciation', 'Commander, MEPLEF']],
        'reinlist_cg_plaques' => [['Plaque of Appreciation', 'MEPCOM']],
        'reinlist_military_letters' => [['Letter of Commendation (AFP)', 'Philippine Navy']],
        'reinlist_civilian_awards' => [['Outstanding Volunteer Award', 'Local Government Unit']],
        'reinlist_other_letters' => [['Letter of Appreciation', 'Partner agency']],
        'reinlist_other_appreciations' => [['Certificate of Appreciation', 'Partner school']],
        'reinlist_other_plaques' => [['Plaque of Recognition', 'Partner agency']],
    ];

    public function run(): void
    {
        $this->ensureSafe();
        if ($this->alreadySeeded('reinlist_vocational', 'course', 'Sample %')) {
            $this->command?->line('  re-enlistment sections already present');
            return;
        }

        $now = now();
        $members = $this->members();
        $count = 0;

        DB::transaction(function () use ($members, $now, &$count) {
            foreach ($members as $i => $m) {
                $serial = $m->serial_number;
                [$first, $last] = array_pad(explode(' ', $m->name, 2), 2, 'Sample');

                $rows = [
                    'reinlist_vocational' => [[
                        'personnel_id' => "MEPCOM-{$serial}-" . $now->year, 'serial_number' => $serial,
                        'course' => 'Sample Marine Pollution Response Course', 'authority' => 'MEPTDI',
                        'school' => 'MEPTDI, Manila', 'period' => '2023',
                    ]],
                    'reinlist_appointments' => [[
                        'serial_number' => $serial, 'applicant_id' => (string) $m->id,
                        'description' => 'Sample appointment: temporary enlistment', 'date' => $now->copy()->subYears(3)->toDateString(),
                        'authority' => 'Commandant, PCG',
                    ]],
                    'reinlist_careers' => [[
                        'serial_number' => $serial, 'applicant_id' => $m->id, 'student' => 'Sample basic course',
                        'sea' => 'BRP Sample (2 years)', 'staff' => 'MEPCOM staff', 'instructor' => $i % 3 === 0 ? 'MEPTDI instructor' : null,
                        'command' => 'MEPCOM', 'organization_affiliation' => 'Sample association',
                        'civil_service_eligibility' => 'Professional (sample)', 'compulsory_retirement' => $now->copy()->addYears(30)->toDateString(),
                    ]],
                    'reinlist_awards' => [[
                        'serial_number' => $serial, 'applicant_id' => $m->id, 'category' => 'Service',
                        'type' => 'Sample Service Ribbon', 'authority' => 'CPCG',
                    ]],
                    'reinlist_profile' => [[
                        'serial_number' => $serial, 'organization' => 'Sample organization',
                        'eligibility' => 'Professional (sample)', 'retirement' => $now->copy()->addYears(30)->toDateString(),
                    ]],
                ];
                foreach (self::LISTS as $table => $entries) {
                    foreach (array_slice($entries, 0, 1 + $i % count($entries)) as [$type, $authority]) {
                        $rows[$table][] = ['serial_number' => $serial, 'applicant_id' => $m->id, 'type' => "Sample {$type}", 'authority' => $authority];
                    }
                }

                foreach ($rows as $table => $list) {
                    if (!Schema::hasTable($table)) {
                        continue;
                    }
                    foreach ($list as $row) {
                        DB::table($table)->insert($row + ['created_at' => $now, 'updated_at' => $now]);
                        $count++;
                    }
                }

                if (Schema::hasTable('profiles') && !DB::table('profiles')->where('user_id', $m->id)->exists()) {
                    DB::table('profiles')->insert([
                        'user_id' => $m->id, 'district' => 'NCR', 'first_name' => $first, 'last_name' => $last,
                        'birthday' => $now->copy()->subYears(25 + $i)->toDateString(), 'sex' => $i % 2 ? 'Female' : 'Male',
                        'perm_street' => 'Sample Street', 'perm_city' => 'Manila', 'perm_province' => 'Metro Manila', 'perm_zipcode' => '1000',
                        'perm_years_residency' => '5', 'pres_street' => 'Sample Street', 'pres_city' => 'Manila', 'pres_province' => 'Metro Manila',
                        'pres_zipcode' => '1000', 'pres_years_residency' => '5', 'education_level' => 'College',
                        'tertiary_course' => 'BS Marine Biology', 'tertiary_school' => 'Sample University', 'tertiary_year_graduated' => '2018',
                        'secondary_school' => 'Sample High School', 'secondary_year_graduated' => '2014',
                        'elementary_school' => 'Sample Elementary School', 'elementary_year_graduated' => '2010',
                        'profile_code' => 'SAMPLE-' . $serial, 'face_verified' => 0,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $count++;
                }
            }

            if (Schema::hasTable('applicants') && !DB::table('applicants')->exists()) {
                DB::table('applicants')->insert([['created_at' => $now, 'updated_at' => $now], ['created_at' => $now, 'updated_at' => $now]]);
                $count += 2;
            }
        });

        $this->command?->line("  re-enlistment sections: {$count} rows");
    }
}
