<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Artisan;

/**
 * The original sample set (pcg:dev-seed): users, unit_users, admin_users,
 * personnel_data, rejected_documents, personnel_201_files, the first
 * reinlist_* sections, training tracks, rank equivalencies, training
 * applications, courses, course students and OSAERT trainees.
 */
class SampleCoreSeeder extends DevOnlySeeder
{
    public function run(): void
    {
        $this->ensureSafe();
        Artisan::call('pcg:dev-seed');
        $this->command?->line('  ' . trim(strtok(Artisan::output(), "\n") ?: 'pcg:dev-seed done'));
    }
}
