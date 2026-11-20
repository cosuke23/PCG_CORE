<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emergency recall (owner request 2026-11-20, App\Support\Recalls): a unit checker
 * (own unit) or M1 checker (a unit or all) sends one message to every approved
 * member of the unit by email, portal notification and phone alert; members
 * answer "on my way" or "cannot come"; the checker sees who answered.
 *
 * up():   creates recalls and recall_responses when they do not exist. No existing table changes.
 * down(): drops both (only this migration's tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recalls')) {
            Schema::create('recalls', function (Blueprint $t) {
                $t->id();
                $t->string('unit_name', 150)->nullable();          // users.mother_unit; NULL = every unit (M1)
                $t->string('title', 150);
                $t->text('message');
                $t->string('report_to', 200)->nullable();          // where to report
                $t->unsignedInteger('recipients')->default(0);
                $t->string('sent_by_type', 10);                    // unit | admin
                $t->unsignedBigInteger('sent_by_id')->nullable();
                $t->string('sent_by_name', 150)->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('recall_responses')) {
            Schema::create('recall_responses', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('recall_id');
                $t->unsignedBigInteger('user_id');
                $t->string('answer', 20)->nullable();              // coming | cannot ; NULL = not answered
                $t->string('note', 300)->nullable();
                $t->timestamp('answered_at')->nullable();
                $t->timestamps();
                $t->unique(['recall_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recall_responses');
        Schema::dropIfExists('recalls');
    }
};
