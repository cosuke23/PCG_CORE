<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Registration failed on Hermes (2026-11-08): "Data too long for column
 * 'sub_unit'". The real users table keeps unit names in short VARCHAR
 * columns, but the registration dropdown has names up to ~110 characters
 * (e.g. "Marine Environmental Protection Training and Doctrine Institute (MEPTDI)").
 *
 * up():   widens users.mother_unit, sub_unit and unit_assignment to
 *         VARCHAR(255) where they are shorter. Only the length changes:
 *         NULL/NOT NULL, default and collation are kept. No data changes.
 *         The previous definitions are saved for down().
 * down(): restores the saved lengths, and refuses while any value is longer
 *         than the old length (so a rollback never cuts data).
 *
 * MySQL/MariaDB only; other drivers (the SQLite tests) are left alone.
 */
return new class extends Migration
{
    private const COLUMNS = ['mother_unit', 'sub_unit', 'unit_assignment'];
    private const LENGTH = 255;

    private function stateFile(): string
    {
        return storage_path('app/migration-state/2026_11_08_000001_widen_users_unit_columns.json');
    }

    private function supported(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) && Schema::hasTable('users');
    }

    /** SHOW FULL COLUMNS row for one column, or null. */
    private function column(string $name): ?object
    {
        return DB::selectOne('SHOW FULL COLUMNS FROM `users` WHERE Field = ?', [$name]);
    }

    private function modify(object $col, string $type): void
    {
        $sql = 'ALTER TABLE `users` MODIFY `' . $col->Field . '` ' . $type;
        if ($col->Collation) {
            $sql .= ' CHARACTER SET ' . explode('_', $col->Collation)[0] . ' COLLATE ' . $col->Collation;
        }
        $sql .= $col->Null === 'YES' ? ' NULL' : ' NOT NULL';
        if ($col->Default !== null) {
            $sql .= ' DEFAULT ' . DB::getPdo()->quote($col->Default);
        } elseif ($col->Null === 'YES') {
            $sql .= ' DEFAULT NULL';
        }
        DB::statement($sql);
    }

    public function up(): void
    {
        if (!$this->supported()) {
            return;
        }
        $saved = File::exists($this->stateFile()) ? json_decode(File::get($this->stateFile()), true) : [];

        foreach (self::COLUMNS as $name) {
            $col = $this->column($name);
            if (!$col || !preg_match('/^(var)?char\((\d+)\)$/i', $col->Type, $m) || (int) $m[2] >= self::LENGTH) {
                continue; // missing, not a short text column, or already wide enough
            }
            $saved[$name] = $saved[$name] ?? $col->Type;
            File::ensureDirectoryExists(dirname($this->stateFile()));
            File::put($this->stateFile(), json_encode($saved, JSON_PRETTY_PRINT)); // saved before the change
            $this->modify($col, 'VARCHAR(' . self::LENGTH . ')');
        }
    }

    public function down(): void
    {
        if (!$this->supported() || !File::exists($this->stateFile())) {
            return;
        }
        $saved = json_decode(File::get($this->stateFile()), true) ?: [];

        foreach ($saved as $name => $oldType) {
            $col = $this->column($name);
            if (!$col || !preg_match('/\((\d+)\)$/', $oldType, $m)) {
                continue;
            }
            $longest = (int) DB::table('users')->max(DB::raw('CHAR_LENGTH(`' . $name . '`)'));
            if ($longest > (int) $m[1]) {
                throw new RuntimeException("users.$name has values of $longest characters; the old column held {$m[1]}. Not rolled back.");
            }
        }
        foreach ($saved as $name => $oldType) {
            if ($col = $this->column($name)) {
                $this->modify($col, strtoupper($oldType));
            }
        }
        File::delete($this->stateFile());
    }
};
