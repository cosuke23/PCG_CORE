<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manpower readiness on a day: per unit (users.mother_unit) the strength
 * (approved members with a serial number), who is on approved leave, who is in a
 * MEPTDI class (course_students with that day inside date_attended..date_ended),
 * and how many are available (strength minus everyone away, each person once).
 */
class Readiness
{
    /**
     * @param  callable(\Illuminate\Database\Query\Builder): void  $scope  limits users (u)
     * @return array{units: Collection, people: Collection, totals: array}
     */
    public static function on(string $date, callable $scope, ?string $unit = null): array
    {
        $members = DB::table('users as u')->whereNotNull('u.serial_number')->where('u.serial_number', '<>', '')
            ->whereRaw('LOWER(TRIM(u.status)) = ?', ['approved'])
            ->when($unit !== null && $unit !== '', fn ($q) => $q->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower(trim((string) $unit))]));
        $scope($members);
        $members = $members->get(['u.id', 'u.name', 'u.rank', 'u.serial_number', 'u.mother_unit']);
        $ids = $members->pluck('id')->all();
        $serials = $members->mapWithKeys(fn ($m) => [strtoupper(trim((string) $m->serial_number)) => $m->id]);

        $leave = collect();
        if (Schema::hasTable('leave_requests') && $ids) {
            $leave = DB::table('leave_requests')->whereIn('user_id', $ids)->whereRaw('LOWER(status) = ?', ['approved'])
                ->where('start_date', '<=', $date)->where('end_date', '>=', $date)
                ->get(['user_id', 'leave_type', 'end_date'])->keyBy('user_id');
        }
        $training = collect();
        if (Schema::hasTable('course_students') && $serials->isNotEmpty()) {
            $rows = DB::table('course_students as cs')
                ->leftJoin('training_courses as tc', 'tc.id', '=', 'cs.course_id')
                ->whereIn(DB::raw('UPPER(TRIM(cs.serial_number))'), $serials->keys()->all())
                ->whereNotNull('cs.date_attended')->where('cs.date_attended', '<=', $date)
                ->where(fn ($q) => $q->where('cs.date_ended', '>=', $date))
                ->get(['cs.serial_number', 'cs.date_ended', 'tc.course_name']);
            foreach ($rows as $r) {
                $uid = $serials[strtoupper(trim((string) $r->serial_number))] ?? null;
                if ($uid && !$training->has($uid)) {
                    $training->put($uid, $r);
                }
            }
        }

        $people = collect();
        foreach ($members as $m) {
            $state = $leave->has($m->id) ? 'leave' : ($training->has($m->id) ? 'training' : 'available');
            $people->push((object) [
                'id' => $m->id, 'name' => $m->name, 'rank' => $m->rank, 'serial' => $m->serial_number,
                'unit' => trim((string) $m->mother_unit) !== '' ? trim((string) $m->mother_unit) : 'No unit',
                'state' => $state,
                'detail' => $state === 'leave' ? LeaveTypes::label($leave[$m->id]->leave_type) : ($state === 'training' ? (string) ($training[$m->id]->course_name ?? 'MEPTDI class') : null),
                'until' => $state === 'leave' ? $leave[$m->id]->end_date : ($state === 'training' ? $training[$m->id]->date_ended : null),
            ]);
        }

        $units = $people->groupBy(fn ($p) => mb_strtoupper($p->unit))->map(function ($g) {
            $strength = $g->count();
            $onLeave = $g->where('state', 'leave')->count();
            $inTraining = $g->where('state', 'training')->count();
            $available = $strength - $onLeave - $inTraining;
            return (object) ['unit' => $g->first()->unit, 'strength' => $strength, 'leave' => $onLeave, 'training' => $inTraining,
                'available' => $available, 'percent' => $strength ? (int) round($available * 100 / $strength) : 0];
        })->sortBy('unit')->values();

        $t = ['strength' => $units->sum('strength'), 'leave' => $units->sum('leave'), 'training' => $units->sum('training'), 'available' => $units->sum('available')];
        $t['percent'] = $t['strength'] ? (int) round($t['available'] * 100 / $t['strength']) : 0;

        return ['units' => $units, 'people' => $people->where('state', '!=', 'available')->sortBy([['unit', 'asc'], ['state', 'asc'], ['name', 'asc']])->values(), 'totals' => $t];
    }
}
