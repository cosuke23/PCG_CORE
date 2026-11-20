<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leave credits and balances (owner request 2026-11-20). Credits live in pools;
 * some leave types are charged to a pool (LeaveTypes::TYPES[..]['pool']), the
 * rest are granted without credits. Pools (defaults until the PCG directive):
 *
 * - annual: ordinary leave credits. The unit sets an opening balance "as of" a
 *   date; from then 2.5 days (30 a year) are added on the 1st of every month.
 *   Unused credits carry over. Charged by ordinary and terminal leave.
 * - rnr: rest and recreation, earned by duty: the unit grants days after a
 *   deployment or sea duty. Charged by RNR.
 * - solo_parent (7) and vawc (10): days per calendar year by law, reset on Jan 1.
 *
 * The ledger (leave_credit_entries) holds openings, grants, adjustments, days
 * used when leave is approved and days restored when approved leave is
 * cancelled. The monthly accrual is worked out on reading, so no scheduled job
 * is needed. Pending requests hold their days until decided.
 */
class LeaveCredits
{
    public const POOLS = [
        'annual' => ['label' => 'Ordinary leave credits', 'short' => 'Ordinary', 'monthly' => 2.5, 'needs_opening' => true,
            'rule' => '2.5 days are added on the 1st of every month (30 a year); unused days carry over.'],
        'rnr' => ['label' => 'RNR credits', 'short' => 'RNR', 'granted' => true,
            'rule' => 'Earned by duty: your unit adds days after a deployment, sea duty or a hardship assignment.'],
        'solo_parent' => ['label' => 'Solo parent leave', 'short' => 'Solo parent', 'per_year' => 7,
            'rule' => '7 days each calendar year (RA 8972, RA 11861).'],
        'vawc' => ['label' => 'VAWC leave', 'short' => 'VAWC', 'per_year' => 10,
            'rule' => '10 days each calendar year (RA 9262).'],
    ];

    public const KINDS = [
        'opening' => 'Opening balance', 'grant' => 'Credit added', 'adjustment' => 'Adjustment',
        'used' => 'Leave taken', 'restored' => 'Leave cancelled, days returned', 'accrual' => 'Monthly credit',
    ];

    public static function poolFor(?string $leaveType): ?string
    {
        return LeaveTypes::TYPES[$leaveType]['pool'] ?? null;
    }

    public static function label(string $pool): string
    {
        return self::POOLS[$pool]['label'] ?? $pool;
    }

    /** Whole months whose 1st has passed since the opening date (each adds the monthly credit). */
    public static function monthsAccrued(string $asOf, ?Carbon $today = null): int
    {
        $today = ($today ?? LeaveCalendar::today())->copy()->startOfMonth();
        $start = Carbon::parse($asOf, LeaveCalendar::TZ)->startOfMonth();
        if ($today->lte($start)) {
            return 0;
        }
        return (int) (($today->year - $start->year) * 12 + ($today->month - $start->month));
    }

    /**
     * Balances of one member: per pool the credit balance (null = not set up),
     * the days held by pending requests and what is available to file now.
     */
    public static function balances(int $userId, ?Carbon $today = null): array
    {
        $today = $today ?? LeaveCalendar::today();
        $entries = DB::table('leave_credit_entries')->where('user_id', $userId)
            ->orderBy('effective_date')->orderBy('id')->get()->groupBy('pool');
        $held = DB::table('leave_requests')->where('user_id', $userId)->whereRaw('LOWER(status) = ?', ['pending'])
            ->get(['leave_type', 'days'])
            ->groupBy(fn ($r) => self::poolFor($r->leave_type) ?? '-')
            ->map(fn ($g) => (float) $g->sum('days'));

        $out = [];
        foreach (self::POOLS as $pool => $def) {
            $rows = $entries->get($pool, collect());
            $balance = null;
            $accrued = 0.0;
            if (!empty($def['needs_opening'])) {
                $opening = $rows->where('kind', 'opening')->sortBy([['effective_date', 'asc'], ['id', 'asc']])->last();
                if ($opening) {
                    $months = self::monthsAccrued((string) $opening->effective_date, $today);
                    $accrued = $months * (float) $def['monthly'];
                    $after = $rows->filter(fn ($e) => $e->kind !== 'opening'
                        && ((string) $e->effective_date > (string) $opening->effective_date
                            || ((string) $e->effective_date === (string) $opening->effective_date && $e->id > $opening->id)));
                    $balance = (float) $opening->days + $accrued + (float) $after->sum('days');
                }
            } elseif (!empty($def['per_year'])) {
                $year = $rows->filter(fn ($e) => substr((string) $e->effective_date, 0, 4) === (string) $today->year);
                $balance = (float) $def['per_year'] + (float) $year->sum('days');
            } else {
                $balance = (float) $rows->sum('days');
            }
            $hold = (float) ($held[$pool] ?? 0);
            $out[$pool] = [
                'pool' => $pool,
                'label' => $def['label'],
                'short' => $def['short'],
                'rule' => $def['rule'],
                'set' => $balance !== null,
                'balance' => $balance === null ? null : round($balance, 2),
                'held' => $hold,
                'available' => $balance === null ? null : round($balance - $hold, 2),
                'accrued' => $accrued,
            ];
        }
        return $out;
    }

    /**
     * Can $days of $leaveType be taken? Null = yes (or the type uses no credits),
     * else the reason. $ignorePending skips the hold of that request (approval).
     */
    public static function shortfall(int $userId, string $leaveType, float $days, ?int $ignorePending = null): ?string
    {
        $pool = self::poolFor($leaveType);
        if ($pool === null) {
            return null;
        }
        $b = self::balances($userId)[$pool];
        if (!$b['set']) {
            return 'Your ' . strtolower($b['label']) . ' are not set yet. Ask your unit checker to set your opening balance.';
        }
        $available = $b['available'];
        if ($ignorePending !== null) {
            $own = DB::table('leave_requests')->where('id', $ignorePending)->whereRaw('LOWER(status) = ?', ['pending'])->value('days');
            $available += (float) $own;
        }
        if ($days > $available + 1e-9) {
            return 'Only ' . self::fmt($available) . ' ' . (abs($available - 1) < 1e-9 ? 'day is' : 'days are') . ' available in '
                . strtolower($b['label']) . ' (this request is ' . self::fmt($days) . ').';
        }
        return null;
    }

    /** Post a ledger entry. */
    public static function post(int $userId, string $serial, string $pool, string $kind, float $days, string $date,
                                ?string $note = null, ?int $leaveId = null, array $by = []): int
    {
        return DB::table('leave_credit_entries')->insertGetId([
            'user_id' => $userId, 'serial_number' => $serial, 'pool' => $pool, 'kind' => $kind,
            'days' => round($days, 2), 'effective_date' => $date, 'leave_request_id' => $leaveId, 'note' => $note,
            'created_by_type' => $by['type'] ?? null, 'created_by_id' => $by['id'] ?? null, 'created_by_name' => $by['name'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * History of one pool, newest first, with the monthly credits written in as
     * lines (they are not stored).
     */
    public static function history(int $userId, string $pool, ?Carbon $today = null): Collection
    {
        $today = $today ?? LeaveCalendar::today();
        $rows = DB::table('leave_credit_entries')->where('user_id', $userId)->where('pool', $pool)
            ->orderBy('effective_date')->orderBy('id')->get()
            ->map(fn ($e) => (object) ['date' => (string) $e->effective_date, 'kind' => $e->kind, 'days' => (float) $e->days,
                'note' => $e->note, 'by' => $e->created_by_name, 'id' => (int) $e->id]);
        $def = self::POOLS[$pool] ?? [];
        if (!empty($def['needs_opening'])) {
            $opening = $rows->where('kind', 'opening')->last();
            if ($opening) {
                $m = self::monthsAccrued($opening->date, $today);
                $first = Carbon::parse($opening->date, LeaveCalendar::TZ)->startOfMonth();
                for ($i = 1; $i <= $m; $i++) {
                    $rows->push((object) ['date' => $first->copy()->addMonths($i)->toDateString(), 'kind' => 'accrual',
                        'days' => (float) $def['monthly'], 'note' => null, 'by' => null, 'id' => 0]);
                }
            }
        }
        return $rows->sortBy([['date', 'desc'], ['id', 'desc']])->values();
    }

    public static function fmt(?float $days): string
    {
        if ($days === null) {
            return '—';
        }
        $s = rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');
        return $s === '-0' ? '0' : $s;
    }
}
