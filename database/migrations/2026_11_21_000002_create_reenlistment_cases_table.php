<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-enlistment workflow (owner request 2026-11-20, App\Support\ReenlistmentCases):
 * one case per non-officer with a status and the checklist of requirements
 * (config('pcg.reenlistment.requirements')), kept by HR / the re-enlistment admin.
 *
 * up():   creates reenlistment_cases when it does not exist. No existing table changes.
 * down(): drops reenlistment_cases (only this migration's table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reenlistment_cases')) {
            return;
        }
        Schema::create('reenlistment_cases', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->string('serial_number', 100)->index();
            $t->date('ete')->nullable();                     // the ETE the case is for (users.ete when opened)
            $t->string('status', 30)->default('not_started'); // App\Support\ReenlistmentCases::STATUSES
            $t->text('checklist')->nullable();               // JSON: requirement key => date done
            $t->text('note')->nullable();
            $t->string('updated_by_name', 150)->nullable();
            $t->unsignedBigInteger('updated_by_id')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reenlistment_cases');
    }
};
