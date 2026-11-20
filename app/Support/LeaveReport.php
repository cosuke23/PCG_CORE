<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leave reports (owner request 2026-11-20): the CSV export and the monthly summary
 * for unit checkers (own unit; M1 every unit) and admins with leave.view (OrgScope).
 * $limit narrows the users table (alias u) to the caller's scope. Reasons are
 * never exported (they can be medical); the type, dates, status and decision are.
 */
class LeaveReport
{
    public const STATUSES = ['all' => 'All', 'approved' => 'Approved', 'pending' => 'Waiting', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];

    /** Leave that overlaps [$from, $to], oldest start first. */
    public static function rows(callable $limit, ?string $unit, string $from, string $to, string $status = 'all'): Collection
    {
        $q = DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.start_date', '<=', $to)->where('l.end_date', '>=', $from)
            ->when($unit !== null && $unit !== '', fn ($w) => $w->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower(trim($unit))]))
            ->when($status !== 'all', fn ($w) => $w->whereRaw('LOWER(l.status) = ?', [$status]));
        $limit($q);
        return $q->orderBy('l.start_date')->orderBy('l.id')
            ->get(['l.id', 'l.serial_number', 'l.leave_type', 'l.start_date', 'l.end_date', 'l.days', 'l.status', 'l.created_at',
                'l.reviewed_at', 'l.reviewer_name', 'l.review_note', 'u.name', 'u.rank', 'u.mother_unit']);
    }

    /** Days of a request that fall inside [$from, $to], counted the way leave is counted (pcg.leave.count). */
    public static function daysWithin(object $r, string $from, string $to): int
    {
        $a = max((string) $r->start_date, $from);
        $b = min((string) $r->end_date, $to);
        return $a <= $b ? LeaveCalendar::countDays($a, $b) : 0;
    }

    /**
     * Month summary: by unit and by leave type. Approved leave counts members and
     * the days inside the month; waiting and declined are request counts.
     */
    public static function monthly(Collection $rows, string $from, string $to): array
    {
        $units = [];
        $types = [];
        $total = ['members' => [], 'approved' => 0, 'days' => 0, 'pending' => 0, 'declined' => 0];
        foreach ($rows as $r) {
            $s = strtolower((string) $r->status);
            $u = trim((string) $r->mother_unit) !== '' ? trim((string) $r->mother_unit) : '(no unit)';
            $units[$u] ??= ['unit' => $u, 'members' => [], 'approved' => 0, 'days' => 0, 'pending' => 0, 'declined' => 0];
            if ($s === 'approved') {
                $d = self::daysWithin($r, $from, $to);
                $types[$r->leave_type] ??= ['type' => LeaveTypes::label($r->leave_type), 'members' => [], 'approved' => 0, 'days' => 0];
                foreach ([&$units[$u], &$types[$r->leave_type], &$total] as &$bucket) {
                    $bucket['members'][$r->serial_number] = true;
                    $bucket['approved']++;
                    $bucket['days'] += $d;
                }
                unset($bucket);
            } elseif ($s === 'pending' || $s === 'declined') {
                $units[$u][$s]++;
                $total[$s]++;
            }
        }
        $fix = function (array $b) {
            $b['members'] = count($b['members']);
            return (object) $b;
        };
        $byUnit = collect($units)->map($fix)->sortBy('unit', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $byType = collect($types)->map($fix)->sortByDesc('days')->values();
        return ['byUnit' => $byUnit, 'byType' => $byType, 'total' => $fix($total)];
    }

    /** CSV download; cells that a spreadsheet would run as a formula are prefixed with '. */
    public static function csv(Collection $rows, string $filename, string $from, string $to): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Reference', 'Serial number', 'Rank', 'Name', 'Unit', 'Leave type', 'Start', 'End', 'Days', 'Days in period',
                'Status', 'Filed', 'Decided', 'Decided by', 'Decision note'], ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($out, array_map([self::class, 'cell'], [
                    'L-' . str_pad((string) $r->id, 5, '0', STR_PAD_LEFT), $r->serial_number, $r->rank, $r->name, $r->mother_unit,
                    LeaveTypes::label($r->leave_type), $r->start_date, $r->end_date, $r->days, self::daysWithin($r, $from, $to),
                    ucfirst(strtolower((string) $r->status)),
                    $r->created_at ? Carbon::parse($r->created_at)->timezone(LeaveCalendar::TZ)->format('Y-m-d H:i') : '',
                    $r->reviewed_at ? Carbon::parse($r->reviewed_at)->timezone(LeaveCalendar::TZ)->format('Y-m-d H:i') : '',
                    $r->reviewer_name, $r->review_note,
                ]), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public static function cell($v): string
    {
        $v = (string) ($v ?? '');
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && !preg_match('/^-?\d+(\.\d+)?$/', $v) ? "'" . $v : $v;
    }

    /**
     * The period from the request: ?month=YYYY-MM (default this month), or ?from=&to= (max 1 year).
     * @return array{0: string, 1: string, 2: Carbon, 3: bool} from, to, month start, custom range
     */
    public static function period(array $q): array
    {
        $month = LeaveCalendar::month($q['month'] ?? null);
        $valid = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        if ($valid($q['from'] ?? null) && $valid($q['to'] ?? null) && $q['from'] <= $q['to']
            && Carbon::parse($q['from'])->diffInDays(Carbon::parse($q['to'])) <= 366) {
            return [$q['from'], $q['to'], $month, true];
        }
        return [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString(), $month, false];
    }
}
