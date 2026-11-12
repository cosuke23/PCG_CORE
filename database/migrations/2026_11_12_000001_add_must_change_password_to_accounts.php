<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Emailed temporary passwords (owner request 2026-11-12): new admin and unit
 * checker accounts get a random password by email and must change it at their
 * first sign-in.
 *
 * up():   adds must_change_password (boolean, default false) to admin_users and
 *         unit_users where the column does not exist. Existing accounts keep
 *         false, so nobody already signed in is affected.
 * down(): removes the column only from the tables this migration changed (state file).
 */
return new class extends Migration
{
    private const TABLES = ['admin_users', 'unit_users'];

    private function stateFile(): string
    {
        return storage_path('app/migration-state/2026_11_12_000001_add_must_change_password_to_accounts.json');
    }

    public function up(): void
    {
        $added = [];
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'must_change_password')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('must_change_password')->default(false);
            });
            $added[] = $table;
        }
        if ($added) {
            File::ensureDirectoryExists(dirname($this->stateFile()));
            File::put($this->stateFile(), json_encode(['added' => $added]));
        }
    }

    public function down(): void
    {
        if (!File::exists($this->stateFile())) {
            return;
        }
        $state = json_decode(File::get($this->stateFile()), true) ?: [];
        foreach ($state['added'] ?? [] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'must_change_password')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('must_change_password');
                });
            }
        }
        File::delete($this->stateFile());
    }
};
