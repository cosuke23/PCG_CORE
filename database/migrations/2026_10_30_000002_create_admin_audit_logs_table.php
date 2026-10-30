<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who did what in the admin area (sign-ins, approvals, account changes...).
 * New table only. Rollback drops only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_audit_logs')) Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->string('admin_email')->nullable();
            $table->string('action', 100)->index();
            $table->string('method', 10)->nullable();
            $table->string('subject')->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
