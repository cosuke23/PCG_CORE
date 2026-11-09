<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * 201 file review (owner approval 2026-11-09): unit and M1 checkers review a
 * member's 201 file like the required documents.
 *
 * up():   adds a nullable `status` (Pending / approved / rejected) to
 *         personnel_201_files when the table exists and the column does not.
 *         No existing row changes; NULL on older rows counts as "waiting for
 *         review", as on the required documents.
 * down(): removes the column only if this migration added it (remembered in a
 *         small state file), so a column that existed before is never dropped.
 */
return new class extends Migration
{
    private function stateFile(): string
    {
        return storage_path('app/migration-state/2026_11_09_000001_add_review_status_to_personnel_201_files.json');
    }

    public function up(): void
    {
        if (!Schema::hasTable('personnel_201_files') || Schema::hasColumn('personnel_201_files', 'status')) {
            return;
        }
        Schema::table('personnel_201_files', function (Blueprint $t) {
            $t->string('status', 50)->nullable();
        });
        File::ensureDirectoryExists(dirname($this->stateFile()));
        File::put($this->stateFile(), json_encode(['added' => true]));
    }

    public function down(): void
    {
        if (!File::exists($this->stateFile())) {
            return;
        }
        if (Schema::hasTable('personnel_201_files') && Schema::hasColumn('personnel_201_files', 'status')) {
            Schema::table('personnel_201_files', function (Blueprint $t) {
                $t->dropColumn('status');
            });
        }
        File::delete($this->stateFile());
    }
};
