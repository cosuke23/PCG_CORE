<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Artisan;

/** org_units: the MEPCOM unit list (insert-only import; headquarters and commands come from the migration). */
class OrgUnitSeeder extends DevOnlySeeder
{
    public function run(): void
    {
        $this->ensureSafe();
        Artisan::call('pcg:import-org-units', ['file' => base_path('database/data/mepcom_units.json'), '--apply' => true]);
        $this->command?->line('  ' . trim(collect(explode("\n", trim(Artisan::output())))->last() ?: 'units imported'));
    }
}
