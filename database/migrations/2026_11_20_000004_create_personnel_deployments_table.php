<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deployment / sea-duty records (owner request 2026-11-20, App\Support\Deployments):
 * unit checkers and admins record when a member is deployed (vessel or station,
 * from / to). An ended deployment can grant RNR credits (leave_credit_entries,
 * pool rnr); a deployment that covers a day shows as "Deployed" in readiness.
 *
 * up():   creates personnel_deployments when it does not exist. No existing table changes.
 * down(): drops personnel_deployments (only this migration's table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personnel_deployments')) {
            return;
        }
        Schema::create('personnel_deployments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('serial_number', 100)->index();
            $t->string('unit_name', 150)->nullable();              // users.mother_unit when recorded (history)
            $t->string('kind', 20)->default('sea_duty');           // App\Support\Deployments::KINDS
            $t->string('assignment', 150);                         // vessel, station or mission
            $t->date('start_date');
            $t->date('end_date')->nullable();                      // NULL = still deployed
            $t->text('note')->nullable();
            $t->decimal('rnr_days', 6, 2)->nullable();             // RNR granted when it ended
            $t->unsignedBigInteger('rnr_entry_id')->nullable();    // leave_credit_entries.id
            $t->string('recorded_by_type', 10)->nullable();        // unit | admin
            $t->unsignedBigInteger('recorded_by_id')->nullable();
            $t->string('recorded_by_name', 150)->nullable();
            $t->timestamps();
            $t->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_deployments');
    }
};
