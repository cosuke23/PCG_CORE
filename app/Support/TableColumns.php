<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Some legacy tables on Hermes differ from the schema this code was written
 * for (unit_users: user_id NOT NULL although unused, timestamps missing...).
 * fit() drops columns the real table does not have and, for inserts, gives a
 * required column without a default an empty value (0 or '') instead of NULL.
 */
class TableColumns
{
    private const NUMERIC = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real'];

    public static function fit(string $table, array $row, bool $forInsert = false): array
    {
        $columns = collect(Schema::getColumns($table))->keyBy('name')->all();

        $fitted = [];
        foreach ($row as $name => $value) {
            $col = $columns[$name] ?? null;
            if (!$col) {
                continue;
            }
            if ($forInsert && $value === null && !$col['nullable'] && $col['default'] === null && !$col['auto_increment']) {
                $value = in_array(strtolower($col['type_name']), self::NUMERIC, true) ? 0 : '';
            }
            $fitted[$name] = $value;
        }

        return $fitted;
    }
}
