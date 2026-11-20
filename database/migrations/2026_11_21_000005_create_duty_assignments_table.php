<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duty roster (owner request 2026-11-20, App\Support\DutyRoster): watch and duty
 * assignments per unit and day. A member on approved leave, deployed or in a MEPTDI
 * class that day cannot be rostered.
 *
 * up():   creates duty_assignments when it does not exist. No existing table changes.
 * down(): drops duty_assignments (only this migration's table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('duty_assignments')) {
            return;
        }
        Schema::create('duty_assignments', function (Blueprint $t) {
            $t->id();
            $t->string('unit_name', 150)->index();           // users.mother_unit of the member when rostered
            $t->date('duty_date')->index();
            $t->string('shift', 10);                         // App\Support\DutyRoster::SHIFTS
            $t->string('post', 100);                         // e.g. Officer of the Day, Duty driver, Watch: bridge
            $t->unsignedBigInteger('user_id');
            $t->string('serial_number', 100);
            $t->string('note', 200)->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'duty_date', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_assignments');
    }
};
