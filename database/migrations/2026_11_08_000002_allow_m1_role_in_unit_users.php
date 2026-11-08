<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * "Grant M1 access" failed on Hermes (2026-11-08): "Data truncated for column
 * 'role'". The real unit_users.role is an ENUM that does not list 'm1_checker'
 * (the value the M1 sign-in checks for).
 *
 * up():   adds 'm1_checker' to the ENUM values, keeping every existing value,
 *         NULL/NOT NULL, default and collation. No rows change. Does nothing
 *         when role is not an ENUM or already allows the value. The previous
 *         definition is saved for down().
 * down(): restores the saved definition, and refuses while any account still
 *         has the role 'm1_checker' (it would be cut to an empty value).
 *
 * MySQL/MariaDB only; other drivers (the SQLite tests) are left alone.
 */
return new class extends Migration
{
    private const VALUE = 'm1_checker';

    private function stateFile(): string
    {
        return storage_path('app/migration-state/2026_11_08_000002_allow_m1_role_in_unit_users.json');
    }

    private function column(): ?object
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) || !Schema::hasTable('unit_users')) {
            return null;
        }

        return DB::selectOne('SHOW FULL COLUMNS FROM `unit_users` WHERE Field = ?', ['role']);
    }

    private function modify(object $col, string $type): void
    {
        $sql = 'ALTER TABLE `unit_users` MODIFY `role` ' . $type;
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

    /** ['a', 'b'] from "enum('a','b')". */
    private static function values(string $type): array
    {
        preg_match_all("/'((?:[^']|'')*)'/", $type, $m);

        return array_map(fn ($v) => str_replace("''", "'", $v), $m[1]);
    }

    public function up(): void
    {
        $col = $this->column();
        if (!$col || stripos($col->Type, 'enum(') !== 0) {
            return; // not an ENUM: any text value already fits
        }
        $values = self::values($col->Type);
        if (in_array(self::VALUE, $values, true)) {
            return;
        }

        File::ensureDirectoryExists(dirname($this->stateFile()));
        File::put($this->stateFile(), json_encode(['type' => $col->Type], JSON_PRETTY_PRINT)); // saved before the change

        $values[] = self::VALUE;
        $quoted = implode(',', array_map(fn ($v) => DB::getPdo()->quote($v), $values));
        $this->modify($col, 'ENUM(' . $quoted . ')');
    }

    public function down(): void
    {
        $col = $this->column();
        if (!$col || !File::exists($this->stateFile())) {
            return;
        }
        $using = DB::table('unit_users')->where('role', self::VALUE)->count();
        if ($using > 0) {
            throw new RuntimeException("$using checker account(s) still have the role m1_checker. Remove M1 access first. Not rolled back.");
        }
        $saved = json_decode(File::get($this->stateFile()), true);
        $quoted = implode(',', array_map(fn ($v) => DB::getPdo()->quote($v), self::values($saved['type'])));
        $this->modify($col, 'ENUM(' . $quoted . ')');
        File::delete($this->stateFile());
    }
};
