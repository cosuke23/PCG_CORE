<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Member sign-in protection (owner request 2026-11-20, App\Support\MemberDevices):
 * an optional emailed sign-in code chosen by the member, and the list of devices
 * signed in to the account with "sign out" per device or everywhere else.
 *
 * up():   adds nullable users.login_code_enabled (NULL/0 = off) when missing, and
 *         creates member_devices when missing. No existing row changes.
 * down(): drops member_devices and removes the column only if this migration added it.
 */
return new class extends Migration
{
    private function stateFile(): string
    {
        return storage_path('app/migration-state/2026_11_21_000001_add_member_sign_in_security.json');
    }

    public function up(): void
    {
        $added = false;
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'login_code_enabled')) {
            Schema::table('users', function (Blueprint $t) {
                $t->boolean('login_code_enabled')->nullable();
            });
            $added = true;
        }
        if (!Schema::hasTable('member_devices')) {
            Schema::create('member_devices', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('label', 120)->nullable();          // "Chrome on Windows", "PCG HRMIS app on Android"
                $t->string('user_agent', 255)->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('last_seen_at')->nullable();
                $t->timestamp('signed_out_at')->nullable();    // signed out here, or from another device
                $t->string('signed_out_by', 20)->nullable();   // self | other_device | password
            });
        }
        File::ensureDirectoryExists(dirname($this->stateFile()));
        File::put($this->stateFile(), json_encode(['column_added' => $added]));
    }

    public function down(): void
    {
        Schema::dropIfExists('member_devices');
        if (!File::exists($this->stateFile())) {
            return;
        }
        $state = json_decode((string) File::get($this->stateFile()), true) ?: [];
        if (!empty($state['column_added']) && Schema::hasTable('users') && Schema::hasColumn('users', 'login_code_enabled')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('login_code_enabled');
            });
        }
        File::delete($this->stateFile());
    }
};
