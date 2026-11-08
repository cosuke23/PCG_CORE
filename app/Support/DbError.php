<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * A loggable reason for a database error without the data in it.
 * The exception message holds the whole SQL with its values, and even the
 * database's own reason can quote one (MySQL: "Duplicate entry 'juan@x.ph' for
 * key 'users_email_unique'", "Incorrect integer value: 'abc' for column 'x'").
 * Quoted names after column/key/table/constraint/index/database stay; every
 * other quoted text becomes '…'.
 */
class DbError
{
    public static function reason(QueryException $e): string
    {
        $state = $e->errorInfo[0] ?? '?';
        $code = $e->errorInfo[1] ?? null;
        $text = (string) ($e->errorInfo[2] ?? 'database error');

        $text = preg_replace_callback("/(\\b(?:column|key|table|constraint|index|database|field)\\s+)?'(?:[^'\\\\]|\\\\.|'')*'/i",
            fn ($m) => !empty($m[1]) ? $m[0] : "'…'", $text);
        $text = mb_substr($text, 0, 300);

        return 'SQLSTATE ' . $state . ($code !== null ? ' (' . $code . ')' : '') . ': ' . $text;
    }
}
