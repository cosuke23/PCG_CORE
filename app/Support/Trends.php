<?php

namespace App\Support;

use App\Models\Personnel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Command dashboard trends (owner request 2026-11-20): strength by rank and by
 * unit, the ETE pipeline for the next 24 months, leave days taken and members
 * deployed per month for the last 12 months. Everything is limited to the
 * admin's OrgScope (personnel query, and its serial numbers for users-based data).
 * Each series: list of [label, value, tooltip].
 */
class Trends
{
    public static function build(Builder $personnel, ?array $serials): array
    {
        $people = (clone $personnel)->where('service_status', 'active')->with(['rank', 'currentAssignment.orgUnit'])->get();
        $limitUsers = function ($q, string $col = 'u.serial_number') use ($serials) {
            if ($serials !== null) {
                $q->whereIn(DB::raw("UPPER(TRIM($col))"), $serials ?: ['__none__']);
            }
            return $q;
        };

        // strength by rank (most senior first within officers, then non-officers)
        $byRank = $people->groupBy(fn ($p) => $p->rank?->code ?? 'No rank')->map(fn ($g) => [
            'n' => $g->count(), 'cls' => $g->first()->rank?->classification === 'officer' ? 0 : 1, 'sen' => -1 * (int) ($g->first()->rank?->seniority ?? 0)])
            ->sortBy([['cls', 'asc'], ['sen', 'asc']]);
        $rank = $byRank->map(fn ($v, $k) => [$k, $v['n'], "$k: {$v['n']} " . ($v['n'] === 1 ? 'member' : 'members')])->values()->all();

        // strength by unit (current assignment)
        $unit = $people->groupBy(fn ($p) => $p->currentAssignment?->orgUnit?->code ?? 'No assignment')->map->count()->sortDesc()
            ->map(fn ($n, $k) => [$k, $n, "$k: $n " . ($n === 1 ? 'member' : 'members')])->values()->all();

        $tz = 'Asia/Manila';
        $thisMonth = Carbon::now($tz)->startOfMonth();

        // ETE pipeline: non-officers whose ETE falls in each of the next 24 months
        $ete = [];
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'ete')) {
            $rows = $limitUsers(DB::table('users as u'))->whereRaw('LOWER(TRIM(u.registration_type)) = ?', ['non_officer'])
                ->whereRaw('LOWER(TRIM(u.status)) = ?', ['approved'])
                ->whereBetween('u.ete', [$thisMonth->toDateString(), $thisMonth->copy()->addMonths(24)->subDay()->toDateString()])
                ->pluck('u.ete');
            $counts = $rows->countBy(fn ($d) => substr((string) $d, 0, 7));
            for ($i = 0; $i < 24; $i++) {
                $m = $thisMonth->copy()->addMonths($i);
                $n = (int) ($counts[$m->format('Y-m')] ?? 0);
                $ete[] = [$m->format('M y'), $n, $m->format('F Y') . ": $n ETE"];
            }
        }

        // leave days taken and members deployed, last 12 months
        $leave = [];
        $deployed = [];
        $first = $thisMonth->copy()->subMonths(11);
        $leaves = Schema::hasTable('leave_requests')
            ? $limitUsers(DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id'), 'l.serial_number')
                ->whereRaw('LOWER(l.status) = ?', ['approved'])->where('l.end_date', '>=', $first->toDateString())
                ->where('l.start_date', '<=', $thisMonth->copy()->endOfMonth()->toDateString())
                ->get(['l.serial_number', 'l.start_date', 'l.end_date'])
            : collect();
        $deps = Deployments::ready()
            ? $limitUsers(DB::table('personnel_deployments as d')->join('users as u', 'u.id', '=', 'd.user_id'), 'd.serial_number')
                ->where('d.start_date', '<=', $thisMonth->copy()->endOfMonth()->toDateString())
                ->where(fn ($q) => $q->whereNull('d.end_date')->orWhere('d.end_date', '>=', $first->toDateString()))
                ->get(['d.user_id', 'd.start_date', 'd.end_date'])
            : collect();
        for ($i = 0; $i < 12; $i++) {
            $m = $first->copy()->addMonths($i);
            $from = $m->toDateString();
            $to = $m->copy()->endOfMonth()->toDateString();
            $days = 0;
            foreach ($leaves as $l) {
                $days += LeaveReport::daysWithin($l, $from, $to);
            }
            $leave[] = [$m->format('M y'), $days, $m->format('F Y') . ": $days leave " . ($days === 1 ? 'day' : 'days')];
            $n = $deps->filter(fn ($d) => $d->start_date <= $to && ($d->end_date === null || $d->end_date >= $from))->pluck('user_id')->unique()->count();
            $deployed[] = [$m->format('M y'), $n, $m->format('F Y') . ": $n " . ($n === 1 ? 'member' : 'members') . ' deployed'];
        }

        return [
            'strength' => $people->count(),
            'rank' => $rank, 'unit' => $unit, 'ete' => $ete, 'leave' => $leave, 'deployed' => $deployed,
            'eteTotal' => array_sum(array_column($ete, 1)),
            'eteYear' => array_sum(array_column(array_slice($ete, 0, 12), 1)),
        ];
    }
}
