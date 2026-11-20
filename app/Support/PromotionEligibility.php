<?php

namespace App\Support;

use App\Models\Personnel;
use App\Models\Rank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promotion eligibility (owner request 2026-11-20): who meets time in grade and
 * the required schooling for the next rank by a date (default 31 December of this
 * year). Rules live in config('pcg.promotion'): years in grade per classification
 * with per-rank overrides, and required course keywords per rank, matched against
 * the member's training records (personnel_trainings, and the Adjutant's Coast
 * Guard schooling in reinlist_trainings). The defaults stand until the PCG
 * directive is supplied. Read-only: recording a promotion stays on the person's page.
 */
class PromotionEligibility
{
    public const STATES = [
        'eligible' => 'Eligible',
        'schooling' => 'Time in grade met, schooling missing',
        'soon' => 'Eligible within 6 months after the date',
        'not_yet' => 'Not yet',
        'no_rank_date' => 'No date of rank recorded',
        'top' => 'Highest rank',
    ];

    public static function asOf(?string $date): Carbon
    {
        try {
            return $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? Carbon::parse($date)->startOfDay() : Carbon::now('Asia/Manila')->endOfYear()->startOfDay();
        } catch (\Throwable $e) {
            return Carbon::now('Asia/Manila')->endOfYear()->startOfDay();
        }
    }

    /** [years in grade, required course keywords] for leaving $rank. */
    public static function rule(Rank $rank): array
    {
        $cfg = config('pcg.promotion');
        $over = $cfg['ranks'][strtoupper((string) $rank->code)] ?? [];
        $years = (float) ($over['years'] ?? $cfg['years'][$rank->classification] ?? 3);
        $courses = array_values(array_filter((array) ($over['courses'] ?? [])));
        return [$years, $courses];
    }

    /** The next rank up within the classification, or null at the top. */
    public static function nextRank(Rank $rank, Collection $ranks): ?Rank
    {
        return $ranks->where('classification', $rank->classification)->where('seniority', '>', $rank->seniority)->sortBy('seniority')->first();
    }

    /**
     * @return Collection<int, object> one row per active member of the scope
     */
    public static function rows(Builder $personnel, Carbon $asOf): Collection
    {
        $ranks = Rank::query()->get();
        $people = $personnel->where('service_status', 'active')->whereNotNull('rank_id')
            ->with(['rank', 'currentAssignment.orgUnit'])->get();
        $courses = self::courses($people);
        $out = collect();
        foreach ($people as $p) {
            $rank = $p->rank;
            if (!$rank) {
                continue;
            }
            $next = self::nextRank($rank, $ranks);
            [$years, $required] = self::rule($rank);
            $since = $p->rank_date ? Carbon::parse($p->rank_date)->startOfDay() : null;
            $due = $since ? $since->copy()->addMonths((int) round($years * 12)) : null;
            $taken = $courses[$p->id] ?? [];
            $missing = array_values(array_filter($required, fn ($c) => !collect($taken)->contains(fn ($t) => stripos($t, $c) !== false)));
            $state = match (true) {
                $next === null => 'top',
                $since === null => 'no_rank_date',
                $due->lte($asOf) && !$missing => 'eligible',
                $due->lte($asOf) => 'schooling',
                $due->lte($asOf->copy()->addMonths(6)) => 'soon',
                default => 'not_yet',
            };
            $out->push((object) [
                'id' => $p->id, 'serial_number' => $p->serial_number, 'name' => $p->full_name ?: trim($p->first_name . ' ' . $p->last_name),
                'rank' => $rank->code, 'next' => $next?->code, 'rank_date' => $since?->toDateString(),
                'years_in_grade' => $since ? round($since->diffInDays($asOf) / 365.25, 1) : null,
                'required_years' => $years, 'due' => $due?->toDateString(), 'missing' => $missing,
                'unit' => $p->currentAssignment?->orgUnit?->code, 'state' => $state,
                'classification' => $rank->classification, 'seniority' => $rank->seniority,
            ]);
        }
        return $out->sortBy([['classification', 'asc'], ['seniority', 'desc'], ['due', 'asc'], ['name', 'asc']])->values();
    }

    /** personnel id => course titles from both training sources. */
    private static function courses(Collection $people): array
    {
        $out = [];
        $ids = $people->pluck('id')->all();
        if ($ids && Schema::hasTable('personnel_trainings')) {
            foreach (DB::table('personnel_trainings')->whereIn('personnel_id', $ids)->get(['personnel_id', 'title']) as $t) {
                $out[$t->personnel_id][] = (string) $t->title;
            }
        }
        if ($ids && Schema::hasTable('reinlist_trainings') && Schema::hasColumn('reinlist_trainings', 'serial_number')) {
            $bySerial = $people->mapWithKeys(fn ($p) => [Personnel::normalizeSerial($p->serial_number) => $p->id]);
            $col = collect(['title', 'training_title', 'course', 'course_title', 'name'])->first(fn ($c) => Schema::hasColumn('reinlist_trainings', $c));
            if ($col) {
                foreach (DB::table('reinlist_trainings')->whereIn(DB::raw('UPPER(TRIM(serial_number))'), $bySerial->keys()->all())->get(['serial_number', $col]) as $t) {
                    $pid = $bySerial[Personnel::normalizeSerial($t->serial_number)] ?? null;
                    if ($pid) {
                        $out[$pid][] = (string) $t->$col;
                    }
                }
            }
        }
        return $out;
    }
}
