<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator accounts with roles and two-factor sign-in (replaces the
 * single admin defined in .env). New table only; existing data untouched.
 * Rollback: php artisan migrate:rollback --step=1 (drops only this table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 32)->default('hr_admin'); // super_admin | hr_admin | lms_admin
            $table->boolean('is_active')->default(true);
            $table->text('two_factor_secret')->nullable();            // encrypted
            $table->text('two_factor_recovery_codes')->nullable();    // encrypted JSON of hashes
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable(); // replay protection
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
