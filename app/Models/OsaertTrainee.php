<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsaertTrainee extends Model
{
    protected $fillable = [
        'name',
        'company',
        'date_attended',
        'date_ended',
    ];

    /*
     * date_attended / date_ended are free-text columns holding either
     * "dd/mm/yyyy" (older records) or "yyyy-mm-dd" (bulk CSV upload).
     * The year is the first 4 characters in the second format and the last
     * 4 in the first; everything below handles both.
     */

    public static function yearOf(?string $date): string
    {
        $date = trim((string) $date);

        return substr($date, 4, 1) === '-' ? substr($date, 0, 4) : substr($date, -4);
    }

    // SQL equivalent of yearOf() (works in MySQL/MariaDB and SQLite).
    public static function yearSql(string $column): string
    {
        return "CASE WHEN SUBSTR($column, 5, 1) = '-' THEN SUBSTR($column, 1, 4) ELSE SUBSTR($column, -4) END";
    }

    public function scopeWhereYearOf($query, string $column, $year)
    {
        abort_unless(in_array($column, ['date_attended', 'date_ended'], true), 500);

        return $query->whereRaw(self::yearSql($column) . ' = ?', [$year]);
    }

    public function getCertificateNumberAttribute(): string
    {
        return 'OSAERT-' . self::yearOf($this->date_ended) . '-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    // Number printed before the date-format fix (last 4 characters of date_ended).
    public function getLegacyCertificateNumberAttribute(): string
    {
        return 'OSAERT-' . substr(trim((string) $this->date_ended), -4) . '-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    public function isCompleted(): bool
    {
        return trim((string) $this->date_ended) !== '';
    }
}
