<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Base for every sample-data seeder: refuses to run anywhere but a
 * development or test database, so `php artisan db:seed` can never put
 * sample data into the real pcg_core (or the Hermes mepcom_hrmis).
 *
 * Allowed: APP_ENV=local with a database name ending in "_dev", or the
 * in-memory SQLite database of the test suite.
 */
abstract class DevOnlySeeder extends Seeder
{
    protected function ensureSafe(): void
    {
        $connection = DB::connection();
        $database = (string) $connection->getDatabaseName();
        $isTestSqlite = app()->environment('testing') && $connection->getDriverName() === 'sqlite';
        $isDev = app()->environment('local') && str_ends_with($database, '_dev');

        if (!$isTestSqlite && !$isDev) {
            throw new RuntimeException("Refusing to seed '{$database}' (APP_ENV=" . app()->environment()
                . '): sample data only goes into a local *_dev database.');
        }
    }

    /** Has this seeder already run? (sample rows are marked, re-runs add nothing) */
    protected function alreadySeeded(string $table, string $column, string $value): bool
    {
        return DB::table($table)->where($column, 'like', $value)->exists();
    }

    /** Approved and pending sample members created by pcg:dev-seed. */
    protected function members()
    {
        return DB::table('users')->where('email', 'like', '%@demo.test')->where('serial_number', 'like', 'DEMO-%')
            ->orderBy('id')->get(['id', 'name', 'serial_number', 'status', 'registration_type']);
    }
}
