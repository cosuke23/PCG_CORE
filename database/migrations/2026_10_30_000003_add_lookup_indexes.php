<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Adds indexes for the lookups the application runs on every page
 * (serial_number joins, personnel_id, course_id, ...). Approved by the
 * owner on 2026-10-30.
 *
 * Safe on an existing database:
 *  - additive only: no column, data or existing index is changed;
 *  - an index is added only if the table and columns exist and no index
 *    already starts with the same column(s);
 *  - TEXT/BLOB columns are skipped (MySQL cannot index them without a
 *    prefix length);
 *  - each index is attempted on its own, so one failure does not stop
 *    the rest; results are printed and logged.
 * Rollback (php artisan migrate:rollback --step=1) drops only the indexes
 * this migration created (names starting with "pcg_idx_").
 */
return new class extends Migration
{
    private const INDEXES = [
        'users' => [['serial_number'], ['mother_unit'], ['status']],
        'unit_users' => [['email']],
        'personnel_data' => [['serial_number'], ['personnel_id'], ['status']],
        'personnel_201_files' => [['serial_number'], ['personnel_id']],
        'rejected_documents' => [['serial_number', 'status']],
        'reinlist_basic_info' => [['serial_number']],
        'reinlist_personal_info' => [['serial_number']],
        'reinlist_dependents' => [['serial_number']],
        'reinlist_academic' => [['serial_number']],
        'reinlist_vocational' => [['serial_number']],
        'reinlist_trainings' => [['serial_number']],
        'reinlist_duties' => [['serial_number']],
        'reinlist_appointments' => [['serial_number']],
        'reinlist_careers' => [['serial_number'], ['applicant_id']],
        'reinlist_awards' => [['serial_number'], ['applicant_id']],
        'reinlist_cg_awards_decorations' => [['serial_number'], ['applicant_id']],
        'reinlist_cg_letters' => [['serial_number'], ['applicant_id']],
        'reinlist_cg_certificates' => [['serial_number']],
        'reinlist_cg_appreciations' => [['serial_number'], ['applicant_id']],
        'reinlist_cg_plaques' => [['serial_number'], ['applicant_id']],
        'reinlist_military_letters' => [['serial_number']],
        'reinlist_civilian_awards' => [['serial_number'], ['applicant_id']],
        'reinlist_other_letters' => [['serial_number'], ['applicant_id']],
        'reinlist_other_appreciations' => [['serial_number'], ['applicant_id']],
        'reinlist_other_plaques' => [['serial_number'], ['applicant_id']],
        'reinlist_profile' => [['serial_number']],
        'reinlist_disciplinary_actions' => [['serial_number']],
        'course_students' => [['course_id'], ['serial_number']],
        'training_applications' => [['serial_number', 'training_type', 'track_id']],
        'administrative_tracks' => [['rank']],
        'operation_tracks' => [['rank']],
        'rank_equivalencies' => [['user_rank']],
    ];

    private const UNINDEXABLE = ['text', 'mediumtext', 'longtext', 'tinytext', 'blob', 'mediumblob', 'longblob', 'json'];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                $this->report("skip $table: table not found");
                continue;
            }

            $existing = collect(Schema::getIndexes($table))->pluck('columns')->all();
            $types = collect(Schema::getColumns($table))->mapWithKeys(fn ($c) => [$c['name'] => strtolower($c['type_name'])]);

            foreach ($indexes as $columns) {
                $name = $this->indexName($table, $columns);

                if (array_diff($columns, $types->keys()->all())) {
                    $this->report("skip $name: column missing");
                    continue;
                }
                if (array_intersect($columns, array_keys($types->filter(fn ($t) => in_array($t, self::UNINDEXABLE, true))->all()))) {
                    $this->report("skip $name: TEXT/BLOB column");
                    continue;
                }
                $covered = collect($existing)->contains(fn ($cols) => array_slice($cols, 0, count($columns)) === $columns);
                if ($covered) {
                    $this->report("skip $name: already indexed");
                    continue;
                }

                try {
                    Schema::table($table, fn ($t) => $t->index($columns, $name));
                    $this->report("added $name");
                } catch (\Throwable $e) {
                    $this->report("FAILED $name: " . $e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $names = collect(Schema::getIndexes($table))->pluck('name')->all();
            foreach ($indexes as $columns) {
                $name = $this->indexName($table, $columns);
                if (in_array($name, $names, true)) {
                    Schema::table($table, fn ($t) => $t->dropIndex($name));
                }
            }
        }
    }

    private function indexName(string $table, array $columns): string
    {
        $name = 'pcg_idx_' . $table . '_' . implode('_', $columns);

        return strlen($name) > 64 ? 'pcg_idx_' . substr(md5($name), 0, 16) : $name;
    }

    private function report(string $line): void
    {
        Log::info('[index migration] ' . $line);
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            fwrite(STDOUT, "  $line\n");
        }
    }
};
