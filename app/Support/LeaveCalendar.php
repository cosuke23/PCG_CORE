<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Month calendar for the leave module: the grid of days, the leave shown on
 * each day, and a stable colour and initials per person so a crowded day still
 * tells people apart. Dates are Asia/Manila calendar days.
 */
class LeaveCalendar
{
    public const TZ = 'Asia/Manila';
    public const COLOURS = 12;          // .lc-c0 .. .lc-c11 in leave-calendar.css

    /** 'calendar' or 'working' (config pcg.leave.count). */
    public static function countMode(): string
    {
        return config('pcg.leave.count') === 'working' ? 'working' : 'calendar';
    }

    /** @return string[] YYYY-MM-DD non-working days (config pcg.leave.holidays) */
    public static function holidays(): array
    {
        return array_values(array_filter((array) config('pcg.leave.holidays', []),
            fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)));
    }

    /** Days a request from $from to $to (inclusive) counts: every day, or working days only. */
    public static function countDays(string $from, string $to): int
    {
        $a = Carbon::parse($from, self::TZ);
        $b = Carbon::parse($to, self::TZ);
        if ($b->lt($a)) {
            return 0;
        }
        if (self::countMode() === 'calendar') {
            return (int) $a->diffInDays($b) + 1;
        }
        $off = array_flip(self::holidays());
        $n = 0;
        for ($d = $a->copy(); $d->lte($b); $d->addDay()) {
            if (!$d->isWeekend() && !isset($off[$d->toDateString()])) {
                $n++;
            }
        }
        return $n;
    }

    public static function today(): Carbon
    {
        return Carbon::now(self::TZ)->startOfDay();
    }

    /** First day of the month asked for (?month=YYYY-MM), else this month. */
    public static function month(?string $param): Carbon
    {
        if (is_string($param) && preg_match('/^(\d{4})-(\d{2})$/', $param, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12
            && (int) $m[1] >= 2000 && (int) $m[1] <= 2100) {
            return Carbon::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, self::TZ);
        }
        return self::today()->startOfMonth();
    }

    /** Weeks (Sunday first) covering the month, each day with its flags. */
    public static function grid(Carbon $month): array
    {
        $today = self::today()->toDateString();
        $day = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $last = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->startOfDay();
        $weeks = [];
        while ($day->lte($last)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = [
                    'date' => $day->toDateString(),
                    'day' => $day->day,
                    'in_month' => $day->month === $month->month,
                    'today' => $day->toDateString() === $today,
                    'weekend' => $day->isWeekend(),
                ];
                $day->addDay();
            }
            $weeks[] = $week;
        }
        return $weeks;
    }

    /** First and last date shown on the grid. */
    public static function range(Carbon $month): array
    {
        return [
            $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY)->toDateString(),
            $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->toDateString(),
        ];
    }

    /** Colours given out for the calendar on this page (serial => colour). */
    private static array $assigned = [];

    /**
     * Give the people on one calendar different colours: in serial order, the first
     * twelve never share one. Anyone else falls back to a colour from the serial.
     */
    public static function assignColours(iterable $serials): void
    {
        $keys = [];
        foreach ($serials as $s) {
            $keys[strtoupper(trim((string) $s))] = true;
        }
        $keys = array_keys($keys);
        sort($keys);
        self::$assigned = [];
        foreach ($keys as $i => $k) {
            self::$assigned[$k] = $i % self::COLOURS;
        }
    }

    public static function colour(?string $serial): int
    {
        $key = strtoupper(trim((string) $serial));
        return self::$assigned[$key] ?? (int) (sprintf('%u', crc32($key)) % self::COLOURS);
    }

    public static function initials(?string $name): string
    {
        $parts = preg_split('/[\s,]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => !preg_match('/^(jr|sr|ii|iii|iv)\.?$/i', $p)));
        if (!$parts) {
            return '?';
        }
        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($first . $last);
    }

    /** Short name for a chip: the last word of the name. */
    public static function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => !preg_match('/^(jr|sr|ii|iii|iv)\.?$/i', $p)));
        return $parts ? $parts[count($parts) - 1] : '';
    }

    /**
     * Approved leave overlapping [from, to] for members of one unit (users.mother_unit,
     * compared case-insensitively), or of every unit when $unit is null; $serials limits it
     * to those members (the admin console's org scope).
     */
    public static function approved(?string $unit, string $from, string $to, ?array $serials = null): Collection
    {
        $q = DB::table('leave_requests as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->whereRaw('LOWER(l.status) = ?', ['approved'])
            ->where('l.start_date', '<=', $to)
            ->where('l.end_date', '>=', $from)
            ->select('l.id', 'l.user_id', 'l.serial_number', 'l.leave_type', 'l.start_date', 'l.end_date', 'l.days', 'l.status',
                'u.name', 'u.rank', 'u.mother_unit')
            ->orderBy('l.start_date')->orderBy('u.name');
        if ($unit !== null) {
            $q->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower(trim($unit))]);
        }
        if ($serials !== null) {            // admin scope (OrgScope): normalised serial numbers
            $q->whereIn(DB::raw('UPPER(TRIM(l.serial_number))'), $serials ?: ['__none__']);
        }
        return $q->get();
    }

    /**
     * Calendar entries keyed by date. $viewerId sees the real type of their own
     * leave; everyone else sees sensitive types as "On leave" unless $fullTypes.
     */
    public static function byDay(Collection $rows, string $from, string $to, ?int $viewerId = null, bool $fullTypes = false): array
    {
        self::assignColours($rows->pluck('serial_number'));
        $days = [];
        foreach ($rows as $r) {
            $own = $viewerId !== null && (int) $r->user_id === $viewerId;
            $entry = [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'rank' => (string) ($r->rank ?? ''),
                'initials' => self::initials($r->name),
                'short' => self::shortName($r->name),
                'colour' => self::colour($r->serial_number),
                'type' => ($own || $fullTypes) ? LeaveTypes::label($r->leave_type) : LeaveTypes::publicLabel($r->leave_type),
                'pending' => strtolower((string) $r->status) === 'pending',
                'own' => $own,
                'start' => (string) $r->start_date,
                'end' => (string) $r->end_date,
            ];
            $d = Carbon::parse(max((string) $r->start_date, $from), self::TZ);
            $end = Carbon::parse(min((string) $r->end_date, $to), self::TZ);
            while ($d->lte($end)) {
                $days[$d->toDateString()][] = $entry;
                $d->addDay();
            }
        }
        return $days;
    }

    /** One line per person and leave for the month's legend. */
    public static function legend(array $byDay): array
    {
        $seen = [];
        foreach ($byDay as $entries) {
            foreach ($entries as $e) {
                $seen[$e['id']] = $e;
            }
        }
        usort($seen, fn ($a, $b) => [$a['start'], $a['name']] <=> [$b['start'], $b['name']]);
        return $seen;
    }

    public static function dateLabel(string $from, string $to): string
    {
        $a = Carbon::parse($from, self::TZ);
        $b = Carbon::parse($to, self::TZ);
        if ($from === $to) {
            return $a->format('M j, Y');
        }
        if ($a->year === $b->year && $a->month === $b->month) {
            return $a->format('M j') . '–' . $b->format('j, Y');
        }
        return $a->format('M j') . ' – ' . $b->format('M j, Y');
    }
}
