<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Opening balances from a CSV (the members' leave cards), for unit checkers and
 * admins. Columns: serial_number, days, as_of (YYYY-MM-DD or MM/DD/YYYY); a
 * name column and a header row are allowed and ignored. Every row is checked
 * first (member found and in the importer's scope, numbers and dates valid, no
 * serial twice) and shown as a preview; only confirmed, valid rows are posted,
 * each as an opening balance through LeaveActions::credit. The file itself is
 * read in memory and never stored.
 */
class LeaveCreditImport
{
    public const MAX_ROWS = 2000;

    /**
     * @param  callable(object $member): bool  $inScope
     * @return array{rows: array<int, array>, ok: int, errors: int, skipped: int}
     */
    public static function check(string $csv, callable $inScope): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);           // Excel's UTF-8 mark
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $rows = [];
        $seen = [];
        $cols = ['serial' => 0, 'days' => 1, 'as_of' => 2];
        $skipped = 0;
        $today = LeaveCalendar::today();
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map(fn ($c) => trim((string) $c), str_getcsv($line));
            if ($i === 0 && preg_grep('/serial/i', $cells)) {        // header row: find the columns by name
                foreach ($cells as $k => $h) {
                    $h = strtolower($h);
                    if (str_contains($h, 'current')) continue;          // the template's information column
                    if (str_contains($h, 'serial')) $cols['serial'] = $k;
                    elseif (str_contains($h, 'day') || str_contains($h, 'balance') || str_contains($h, 'credit')) $cols['days'] = $k;
                    elseif (str_contains($h, 'as of') || str_contains($h, 'as_of') || str_contains($h, 'date')) $cols['as_of'] = $k;
                }
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                $rows[] = ['line' => $i + 1, 'serial' => '', 'error' => 'Only ' . self::MAX_ROWS . ' rows are read; split the file.'];
                break;
            }
            $serial = strtoupper($cells[$cols['serial']] ?? '');
            $daysRaw = str_replace(',', '.', $cells[$cols['days']] ?? '');
            $dateRaw = $cells[$cols['as_of']] ?? '';
            if ($serial !== '' && $daysRaw === '' && $dateRaw === '') {
                $skipped++;                                          // left blank: not changed
                continue;
            }
            $row = ['line' => $i + 1, 'serial' => $serial, 'days' => null, 'as_of' => null, 'name' => null, 'user_id' => null,
                'current' => null, 'error' => null];

            if ($serial === '') {
                $row['error'] = 'No serial number.';
            } elseif (isset($seen[$serial])) {
                $row['error'] = 'This serial number is already on line ' . $seen[$serial] . '.';
            } elseif (!is_numeric($daysRaw) || (float) $daysRaw < 0 || (float) $daysRaw > 365) {
                $row['error'] = 'Days must be a number from 0 to 365.';
            } elseif (($date = self::date($dateRaw)) === null) {
                $row['error'] = 'The "as of" date must be YYYY-MM-DD or MM/DD/YYYY.';
            } elseif ($date->gt($today->copy()->addDays(31))) {
                $row['error'] = 'The "as of" date is more than a month ahead.';
            } else {
                $member = self::member($serial);
                if (!$member) {
                    $row['error'] = 'No member with this serial number.';
                } elseif (!$inScope($member)) {
                    $row['error'] = 'This member is not in your unit or command.';
                } else {
                    $b = LeaveCredits::balances((int) $member->id)['annual'];
                    $row = array_merge($row, ['days' => round((float) $daysRaw, 2), 'as_of' => $date->toDateString(),
                        'name' => trim(($member->rank ?? '') . ' ' . $member->name), 'user_id' => (int) $member->id,
                        'current' => $b['set'] ? $b['balance'] : null]);
                }
            }
            if ($serial !== '' && !isset($seen[$serial])) {
                $seen[$serial] = $i + 1;
            }
            $rows[] = $row;
        }
        $ok = count(array_filter($rows, fn ($r) => $r['error'] === null));
        return ['rows' => $rows, 'ok' => $ok, 'errors' => count($rows) - $ok, 'skipped' => $skipped];
    }

    /** Post the valid rows as opening balances. Returns how many were posted. */
    public static function apply(array $rows, array $by, ?string $ip = null): int
    {
        $n = 0;
        DB::transaction(function () use ($rows, $by, $ip, &$n) {
            foreach ($rows as $r) {
                if ($r['error'] !== null || empty($r['user_id'])) {
                    continue;
                }
                $member = DB::table('users')->where('id', $r['user_id'])->first(['id', 'name', 'serial_number']);
                if (!$member) {
                    continue;
                }
                [$ok] = LeaveActions::credit($member, ['action' => 'opening', 'days' => $r['days'], 'effective_date' => $r['as_of'],
                    'note' => 'Opening balance (imported)'], $by, $ip);
                $n += $ok ? 1 : 0;
            }
        });
        Log::channel('audit')->info('leave_credit.import', ['rows' => $n, 'by_type' => $by['type'] ?? null, 'by_id' => $by['id'] ?? null, 'ip' => $ip]);
        return $n;
    }

    /** CSV of the given members to fill in: serial, name, unit, days (current balance if set), as of. */
    public static function template(Collection $members): string
    {
        $out = fopen('php://temp', 'r+');
        // days and as_of start empty: only the rows filled in are imported
        fputcsv($out, ['serial_number', 'name', 'unit', 'current_balance', 'days', 'as_of']);
        foreach ($members as $m) {
            $b = LeaveCredits::balances((int) $m->id)['annual'];
            fputcsv($out, [self::safe($m->serial_number), self::safe(trim(($m->rank ?? '') . ' ' . $m->name)), self::safe($m->mother_unit),
                $b['set'] ? LeaveCredits::fmt($b['balance']) : 'not set', '', '']);
        }
        rewind($out);
        return "\xEF\xBB\xBF" . stream_get_contents($out);
    }

    /** Spreadsheet formula injection guard for cells taken from the database. */
    private static function safe(?string $v): string
    {
        $v = (string) $v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    }

    private static function date(string $v): ?Carbon
    {
        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y'] as $f) {
            try {
                $d = Carbon::createFromFormat($f, $v, LeaveCalendar::TZ);
                if ($d && $d->format($f) === $v) {
                    return $d->startOfDay();
                }
            } catch (\Throwable $e) {
            }
        }
        return null;
    }

    /** The member with this serial: the approved account first when there are several. */
    private static function member(string $serial): ?object
    {
        return DB::table('users')->whereRaw('UPPER(TRIM(serial_number)) = ?', [$serial])
            ->orderByRaw("CASE WHEN LOWER(TRIM(status)) = 'approved' THEN 0 ELSE 1 END")->orderBy('id')
            ->first(['id', 'name', 'rank', 'serial_number', 'mother_unit', 'status']);
    }
}
