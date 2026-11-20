<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave credits and balances (owner request 2026-11-20): a ledger of credit
 * entries per member and credit pool (App\Support\LeaveCredits). Balances are
 * the ledger plus the monthly accrual, worked out when read (no scheduled job).
 *
 * up():   creates leave_credit_entries when it does not exist. No existing table changes.
 * down(): drops it only while it holds no entries (refuses otherwise).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_credit_entries')) {
            return;
        }
        Schema::create('leave_credit_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('serial_number', 100)->index();
            $t->string('pool', 30);                                // annual | rnr | solo_parent | vawc
            $t->string('kind', 20);                                // opening | grant | adjustment | used | restored
            $t->decimal('days', 7, 2);                             // + adds credit, - takes it
            $t->date('effective_date');
            $t->unsignedBigInteger('leave_request_id')->nullable()->index();
            $t->string('note', 500)->nullable();
            $t->string('created_by_type', 20)->nullable();         // unit | member | system
            $t->unsignedBigInteger('created_by_id')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'pool', 'effective_date']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leave_credit_entries')) {
            return;
        }
        if (DB::table('leave_credit_entries')->exists()) {
            throw new RuntimeException('leave_credit_entries holds credit history; not dropping it.');
        }
        Schema::drop('leave_credit_entries');
    }
};
