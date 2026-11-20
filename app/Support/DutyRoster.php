<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duty roster per unit (owner request 2026-11-20). Unit checkers roster members of
 * their unit (M1: any unit) by day and shift; a member who is deployed, on approved
 * leave or in a MEPTDI class that day is refused (App\Support\Readiness decides who
 * is away). Members see their own duties at /my-duties and are notified when rostered.
 */
class DutyRoster
{
    public const SHIFTS = ['day' => 'Day (0800–1600)', 'night' => 'Night (1600–0800)', '24h' => '24 hours'];

    public static function ready(): bool
    {
        return Schema::hasTable('duty_assignments');
    }

    public static function weekStart(?string $param): Carbon
    {
        try {
            $d = $param && preg_match('/^\d{4}-\d{2}-\d{2}$/', $param) ? Carbon::parse($param, LeaveCalendar::TZ) : LeaveCalendar::today();
        } catch (\Throwable $e) {
            $d = LeaveCalendar::today();
        }
        return $d->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /** date => shift => list of assignments, for one unit and week. */
    public static function week(string $unit, Carbon $monday): array
    {
        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $out[$monday->copy()->addDays($i)->toDateString()] = array_fill_keys(array_keys(self::SHIFTS), []);
        }
        if (!self::ready()) {
            return $out;
        }
        $rows = DB::table('duty_assignments as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->whereRaw('LOWER(TRIM(d.unit_name)) = ?', [mb_strtolower(trim($unit))])
            ->whereBetween('d.duty_date', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
            ->orderBy('d.post')->get(['d.*', 'u.name', 'u.rank']);
        foreach ($rows as $r) {
            $out[substr((string) $r->duty_date, 0, 10)][$r->shift][] = $r;
        }
        return $out;
    }

    /** Why the member cannot take a duty that day, or null when available. */
    public static function awayReason(object $member, string $date): ?string
    {
        $people = Readiness::on($date, fn ($q) => $q->where('u.id', $member->id))['people'];
        $p = $people->first();
        if (!$p) {
            return null;
        }
        return match ($p->state) {
            'deployed' => 'deployed (' . $p->detail . ')',
            'leave' => 'on approved leave',
            'training' => 'in a MEPTDI class (' . $p->detail . ')',
            default => null,
        };
    }

    public static function upcomingFor(int $userId): Collection
    {
        return self::ready()
            ? DB::table('duty_assignments')->where('user_id', $userId)->where('duty_date', '>=', LeaveCalendar::today()->toDateString())
                ->orderBy('duty_date')->orderBy('shift')->limit(60)->get()
            : collect();
    }
}
