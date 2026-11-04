<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Artisan;

/** personnel + personnel_assignments: approved sample registrations copied into the personnel master. */
class PersonnelMasterSeeder extends DevOnlySeeder
{
    public function run(): void
    {
        $this->ensureSafe();
        Artisan::call('pcg:personnel-sync', ['--apply' => true, '--assign-to' => 'MEPCOM']);
        $this->command?->line('  ' . trim(collect(explode("\n", trim(Artisan::output())))->last() ?: 'personnel synced'));
    }
}
