<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable PCG organisational hierarchy (HQ -> command -> office ->
 * unit -> section). New table only. Seeds just the records confirmed by the
 * owner's organisational chart: NHQ-PCG and the three Functional Commands.
 * Subordinate units are added through Admin -> Organization.
 *
 * Rollback refuses to drop the table once units other than the seeded ones
 * exist, so organisational data entered later is never lost by accident.
 */
return new class extends Migration
{
    private const SEEDED = ['NHQ-PCG', 'MSSC', 'MEPCOM', 'MARSLEC'];

    public function up(): void
    {
        if (Schema::hasTable('org_units')) {
            return; // already created (e.g. migration record missing): nothing to do
        }

        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('type', 30)->index();              // headquarters | command | office | unit | section
            $table->string('category', 40)->nullable();       // command group from the PCG chart
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();   // admin_users.id
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        $hq = DB::table('org_units')->insertGetId([
            'code' => 'NHQ-PCG', 'name' => 'National Headquarters, Philippine Coast Guard',
            'type' => 'headquarters', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $commands = [
            ['MSSC', 'Maritime Safety Services Command'],
            ['MEPCOM', 'Marine Environmental Protection Command'],
            ['MARSLEC', 'Maritime Security and Law Enforcement Command'],
        ];
        foreach ($commands as $i => [$code, $name]) {
            DB::table('org_units')->insert([
                'parent_id' => $hq, 'code' => $code, 'name' => $name, 'type' => 'command',
                'category' => 'functional', 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('org_units')) {
            return;
        }
        if (DB::table('org_units')->whereNotIn('code', self::SEEDED)->exists()) {
            throw new RuntimeException('org_units contains units added after installation; not dropping it. Export or remove them deliberately first.');
        }
        Schema::drop('org_units');
    }
};
