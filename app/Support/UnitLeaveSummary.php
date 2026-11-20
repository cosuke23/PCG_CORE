<?php

namespace App\Support;

use App\Models\UnitUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave at a glance for unit checkers (dashboard card and sidebar badge): requests
 * waiting, who is on leave today, leave starting in the next 7 days and, for an
 * own-unit checker, members without an opening balance. Same scope as leave
 * approval: users.mother_unit = the checker's unit; M1 checkers see every unit.
 * Worked out once per request; null until the leave tables exist.
 */
class UnitLeaveSummary
{
    public static function get(): ?array
    {
        $attrs = request()->attributes;
        if ($attrs->has('unit_leave_summary')) {
            return $attrs->get('unit_leave_summary') ?: null;
        }
        $summary = [];
        try {
            if (Schema::hasTable('leave_requests')) {
                $summary = self::build();
            }
        } catch (\Throwable $e) {
            $summary = [];
        }
        $attrs->set('unit_leave_summary', $summary);
        return $summary ?: null;
    }

    private static function build(): array
    {
        $m1 = session('unit_role') === UnitUser::ROLE_M1;
        $unit = trim((string) session('unit_name', ''));
        $scoped = fn ($q) => $m1 ? $q : $q->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower($unit)]);
        $base = fn () => $scoped(DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id'));
        $today = LeaveCalendar::today()->toDateString();
        $week = LeaveCalendar::today()->addDays(7)->toDateString();

        $away = $base()->whereRaw('LOWER(l.status) = ?', ['approved'])
            ->where('l.start_date', '<=', $today)->where('l.end_date', '>=', $today)
            ->orderBy('l.end_date')->get(['l.serial_number', 'l.leave_type', 'l.end_date', 'u.name', 'u.rank']);
        LeaveCalendar::assignColours($away->pluck('serial_number'));

        $noOpening = null;
        if (!$m1 && Schema::hasTable('leave_credit_entries')) {
            $members = DB::table('users as u')->whereNotNull('u.serial_number')->where('u.serial_number', '<>', '')
                ->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower($unit)])->pluck('u.id');
            $withOpening = DB::table('leave_credit_entries')->whereIn('user_id', $members)
                ->where('pool', 'annual')->where('kind', 'opening')->distinct()->count('user_id');
            $noOpening = max(0, $members->count() - $withOpening);
        }

        return [
            'm1' => $m1,
            'scope' => $m1 ? 'all units' : $unit,
            'pending' => $base()->whereRaw('LOWER(l.status) = ?', ['pending'])->count(),
            'away' => $away,
            'starting' => $base()->whereRaw('LOWER(l.status) = ?', ['approved'])
                ->where('l.start_date', '>', $today)->where('l.start_date', '<=', $week)->count(),
            'noOpening' => $noOpening,
        ];
    }
}
