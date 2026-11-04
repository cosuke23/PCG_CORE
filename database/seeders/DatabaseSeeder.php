<?php

namespace Database\Seeders;

/**
 * Sample data for EVERY application table, for a development database only:
 *
 *     mariadb -uroot -e "CREATE DATABASE pcg_core_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
 *     mariadb -uroot pcg_core_dev < database/dev/schema_inferred.sql
 *     php artisan migrate
 *     php artisan db:seed
 *
 * Refuses unless APP_ENV=local and the database name ends in _dev (see
 * DevOnlySeeder). Safe to run twice: each seeder skips what is already there.
 * Framework tables (password_resets, failed_jobs, personal_access_tokens,
 * migrations) are left empty on purpose.
 */
class DatabaseSeeder extends DevOnlySeeder
{
    public function run(): void
    {
        $this->ensureSafe();

        $this->call([
            SampleCoreSeeder::class,        // users, unit/admin accounts, documents, trainings, OSAERT ...
            OrgUnitSeeder::class,           // the MEPCOM unit list
            PersonnelMasterSeeder::class,   // personnel + current assignments from approved registrations
            ReenlistmentFormSeeder::class,  // every re-enlistment (SOI) section, profiles, applicants
            HrmisRecordsSeeder::class,      // registrations, history, 201 documents, promotions, trainings
            ActivitySeeder::class,          // contact messages and audit log entries
        ]);
    }
}
