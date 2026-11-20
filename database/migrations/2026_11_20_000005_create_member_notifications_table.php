<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications for members (owner request 2026-11-20,
 * App\Support\MemberNotifications): leave decisions, document and 201 reviews,
 * expiring clearances, SOI reopened. Shown under the bell in the member portal
 * and polled by the Android app (1.0.3) for phone alerts.
 *
 * up():   creates member_notifications when it does not exist. No existing table changes.
 * down(): drops member_notifications (only this migration's table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_notifications')) {
            return;
        }
        Schema::create('member_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('kind', 40);                    // leave.approved, documents.returned, ...
            $t->string('title', 150);
            $t->string('body', 500)->nullable();
            $t->string('url', 255)->nullable();        // a path inside the member portal
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['user_id', 'id']);
            $t->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_notifications');
    }
};
