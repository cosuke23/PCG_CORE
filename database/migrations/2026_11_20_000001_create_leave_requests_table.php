<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave module (owner request 2026-11-20): members file leave on a calendar,
 * the unit checker approves or declines it, approved leave shows on the unit
 * calendar.
 *
 * up():   creates leave_requests when it does not exist. No existing table changes.
 * down(): drops it only while it holds no requests (refuses otherwise, so a
 *         rollback can never lose filed leave).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests')) {
            return;
        }
        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('serial_number', 100)->index();
            $t->string('unit_name', 150)->nullable();          // users.mother_unit when filed (history)
            $t->string('leave_type', 30);                      // App\Support\LeaveTypes
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedSmallInteger('days');                  // calendar days, inclusive
            $t->text('reason')->nullable();
            $t->string('status', 20)->default('pending');      // pending | approved | declined | cancelled
            $t->unsignedBigInteger('reviewed_by')->nullable(); // unit_users.id
            $t->string('reviewer_name', 150)->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_note')->nullable();
            $t->timestamps();
            $t->index(['status', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leave_requests')) {
            return;
        }
        if (DB::table('leave_requests')->exists()) {
            throw new RuntimeException('leave_requests holds filed leave; not dropping it.');
        }
        Schema::drop('leave_requests');
    }
};
