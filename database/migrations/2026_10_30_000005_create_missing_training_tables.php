<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Training / MEPTDI LMS tables the code uses but some pcg_core copies lack
 * (tracks, rank equivalencies, training applications, training courses,
 * course students, OSAERT trainees). Shapes follow
 * database/dev/schema_inferred.sql.
 *
 * Additive only: a table that already exists is never touched. Tables this
 * migration creates carry the comment below; rollback drops only those, and
 * only while they are still empty (data entered since is kept).
 */
return new class extends Migration
{
    private const MARKER = 'pcg:000005';

    public function up(): void
    {
        $this->create('administrative_tracks', fn (Blueprint $t) => $this->track($t));
        $this->create('operation_tracks', fn (Blueprint $t) => $this->track($t));

        $this->create('rank_equivalencies', function (Blueprint $t) {
            $t->id();
            $t->string('user_rank', 50)->nullable()->index();
            $t->string('track_rank', 100)->nullable();
            $t->timestamps();
        });

        $this->create('training_applications', function (Blueprint $t) {
            $t->id();
            $t->string('serial_number', 100)->nullable()->index();
            $t->string('training_type', 10)->nullable(); // ACT | OCT
            $t->unsignedBigInteger('track_id')->nullable();
            $t->string('course_training')->nullable();
            $t->string('rank', 100)->nullable();
            $t->string('division_unit')->nullable();
            $t->integer('year_in_grade')->nullable();
            $t->string('provider')->nullable();
            $t->text('remarks')->nullable();
            $t->string('status', 50)->nullable()->default('Pending');
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
            $t->index(['training_type', 'track_id']);
            $t->index('status');
        });

        $this->create('training_courses', function (Blueprint $t) {
            $t->id();
            $t->string('course_name')->nullable()->index();
            $t->text('description')->nullable();
            $t->string('status', 50)->nullable()->default('Active');
            $t->timestamps();
        });

        $this->create('course_students', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('course_id')->nullable()->index();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('serial_number', 100)->nullable()->index();
            $t->string('full_name')->nullable();
            $t->date('date_attended')->nullable();
            $t->date('date_ended')->nullable();
            $t->timestamps();
        });

        $this->create('osaert_trainees', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable()->index();
            $t->string('company')->nullable();
            // strings on purpose: legacy rows use dd/mm/yyyy, new ones yyyy-mm-dd
            $t->string('date_attended', 20)->nullable();
            $t->string('date_ended', 20)->nullable();
            $t->string('start_year', 20)->nullable();
            $t->string('end_year', 20)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['osaert_trainees', 'course_students', 'training_courses', 'training_applications',
                  'rank_equivalencies', 'operation_tracks', 'administrative_tracks'] as $table) {
            if (!Schema::hasTable($table) || !$this->createdHere($table)) {
                continue;
            }
            if (DB::table($table)->exists()) {
                echo "  Kept {$table}: it contains data.\n";
                continue;
            }
            Schema::drop($table);
        }
    }

    private function create(string $table, callable $columns): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $t) use ($columns) {
            $columns($t);
            $t->comment(self::MARKER);
        });
    }

    private function track(Blueprint $t): void
    {
        $t->id();
        $t->integer('no')->nullable();
        $t->string('division_unit')->nullable();
        $t->string('rank', 100)->nullable()->index();
        $t->integer('year_in_grade')->nullable();
        $t->string('course_training')->nullable();
        $t->string('provider')->nullable();
        $t->string('status', 100)->nullable();
        $t->text('remarks')->nullable();
        $t->timestamps();
    }

    private function createdHere(string $table): bool
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return true; // no table comments (SQLite test databases)
        }

        $row = DB::selectOne(
            'SELECT table_comment AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [DB::connection()->getDatabaseName(), $table]
        );

        return $row && $row->c === self::MARKER;
    }
};
