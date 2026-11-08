<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Adjutant module (owner approval 2026-11-08): the Adjutant records Coast Guard
 * awards and decorations (SOI 1.a) and Coast Guard schooling (SOI 3.a).
 *
 * up():   adds a nullable added_by (admin_users.id of the Adjutant) to
 *         reinlist_cg_awards_decorations and reinlist_trainings, where the
 *         table exists and the column does not. No existing row changes
 *         (added_by stays NULL = entered by the member before this module).
 * down(): removes only the columns this migration added (remembered in a
 *         small state file), so a column that existed before is never dropped.
 */
return new class extends Migration
{
    private const TABLES = ['reinlist_cg_awards_decorations', 'reinlist_trainings'];

    private function stateFile(): string
    {
        return storage_path('app/migration-state/2026_11_08_000003_add_adjutant_added_by_to_soi_tables.json');
    }

    public function up(): void
    {
        $added = File::exists($this->stateFile()) ? (json_decode(File::get($this->stateFile()), true) ?: []) : [];

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'added_by')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('added_by')->nullable()->index();
            });
            $added[] = $table;
            File::ensureDirectoryExists(dirname($this->stateFile()));
            File::put($this->stateFile(), json_encode(array_values(array_unique($added))));
        }
    }

    public function down(): void
    {
        if (!File::exists($this->stateFile())) {
            return;
        }
        foreach (json_decode(File::get($this->stateFile()), true) ?: [] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'added_by')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex($table . '_added_by_index');
                    $t->dropColumn('added_by');
                });
            }
        }
        File::delete($this->stateFile());
    }
};
