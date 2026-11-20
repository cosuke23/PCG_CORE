<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Data Privacy Act basics (owner request 2026-11-20): registration records that
 * the member read the privacy notice (/privacy) and agreed to it.
 *
 * up():   adds nullable users.privacy_consent_at and users.privacy_notice_version
 *         when they do not exist. No existing row changes (older accounts stay NULL).
 * down(): removes only the columns this migration added (state file).
 */
return new class extends Migration
{
    private const COLUMNS = ['privacy_consent_at', 'privacy_notice_version'];

    private function stateFile(): string
    {
        return storage_path('app/migration-state/2026_11_20_000003_add_privacy_consent_to_users.json');
    }

    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        $add = array_values(array_filter(self::COLUMNS, fn ($c) => !Schema::hasColumn('users', $c)));
        if (!$add) {
            return;
        }
        Schema::table('users', function (Blueprint $t) use ($add) {
            if (in_array('privacy_consent_at', $add, true)) {
                $t->dateTime('privacy_consent_at')->nullable();
            }
            if (in_array('privacy_notice_version', $add, true)) {
                $t->string('privacy_notice_version', 20)->nullable();
            }
        });
        File::ensureDirectoryExists(dirname($this->stateFile()));
        File::put($this->stateFile(), json_encode(['added' => $add]));
    }

    public function down(): void
    {
        if (!File::exists($this->stateFile())) {
            return;
        }
        $added = (array) (json_decode((string) File::get($this->stateFile()), true)['added'] ?? []);
        $drop = array_values(array_filter($added, fn ($c) => Schema::hasTable('users') && Schema::hasColumn('users', $c)));
        if ($drop) {
            Schema::table('users', function (Blueprint $t) use ($drop) {
                $t->dropColumn($drop);
            });
        }
        File::delete($this->stateFile());
    }
};
