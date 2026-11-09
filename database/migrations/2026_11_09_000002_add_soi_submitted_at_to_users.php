<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * SOI submission lock (owner approval 2026-11-09): once a member submits the
 * SOI, every step is read-only until the Adjutant or the HR admin of their
 * unit reopens it.
 *
 * up():   adds a nullable users.soi_submitted_at (NULL = still editable) when
 *         the column does not exist. No existing row changes.
 * down(): removes the column only if this migration added it (state file).
 */
return new class extends Migration
{
    private function stateFile(): string
    {
        return storage_path('app/migration-state/2026_11_09_000002_add_soi_submitted_at_to_users.json');
    }

    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'soi_submitted_at')) {
            return;
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dateTime('soi_submitted_at')->nullable();
        });
        File::ensureDirectoryExists(dirname($this->stateFile()));
        File::put($this->stateFile(), json_encode(['added' => true]));
    }

    public function down(): void
    {
        if (!File::exists($this->stateFile())) {
            return;
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'soi_submitted_at')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('soi_submitted_at');
            });
        }
        File::delete($this->stateFile());
    }
};
