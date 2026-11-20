<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Deployment and sea-duty records (owner request 2026-11-20). Recorded by unit
 * checkers (own unit; M1 every unit) and admins with leave.credits (OrgScope).
 * Ending one can grant RNR days into the member's rnr pool (LeaveCredits); the
 * suggested number is config('pcg.deployment.rnr_days_per_30') per 30 days
 * deployed (0 = no suggestion, the checker types the days). A deployment that
 * covers a day makes the member "Deployed" in Readiness.
 */
class Deployments
{
    public const KINDS = ['sea_duty' => 'Sea duty', 'deployment' => 'Deployment', 'detail' => 'Detail / temporary duty'];

    public static function ready(): bool
    {
        return Schema::hasTable('personnel_deployments');
    }

    public static function kindLabel(?string $k): string
    {
        return self::KINDS[$k] ?? 'Deployment';
    }

    public static function rules(): array
    {
        $max = LeaveCalendar::today()->copy()->addYear()->toDateString();
        return [
            'user_id' => ['required', 'integer'],
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'assignment' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:' . $max],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:' . $max],
            'note' => ['nullable', 'string', 'max:500'],
            'rnr_days' => ['nullable', 'numeric', 'between:0,60'],
        ];
    }

    public static function endRules(object $dep): array
    {
        return [
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $dep->start_date, 'before_or_equal:' . LeaveCalendar::today()->copy()->addYear()->toDateString()],
            'rnr_days' => ['nullable', 'numeric', 'between:0,60'],
        ];
    }

    /** Calendar days from start to end, inclusive. */
    public static function length(string $from, string $to): int
    {
        return (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
    }

    public static function suggestedRnr(int $days): float
    {
        $per = (float) config('pcg.deployment.rnr_days_per_30', 0);
        return $per > 0 ? floor($days / 30) * $per : 0.0;
    }

    /**
     * Deployments of members in scope: still active (or starting later) first, then
     * the most recent. $limit narrows users (alias u).
     */
    public static function list(callable $limit, ?string $unit, int $take = 100): Collection
    {
        if (!self::ready()) {
            return collect();
        }
        $q = DB::table('personnel_deployments as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->when($unit !== null && $unit !== '', fn ($w) => $w->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower(trim($unit))]));
        $limit($q);
        $today = LeaveCalendar::today()->toDateString();
        return $q->orderByRaw('CASE WHEN d.end_date IS NULL OR d.end_date >= ? THEN 0 ELSE 1 END', [$today])
            ->orderByDesc('d.start_date')->orderByDesc('d.id')->limit($take)
            ->get(['d.*', 'u.name', 'u.rank', 'u.mother_unit'])
            ->each(function ($d) use ($today) {
                $d->active = $d->start_date <= $today && ($d->end_date === null || $d->end_date >= $today);
                $d->upcoming = $d->start_date > $today;
                $d->length = self::length($d->start_date, $d->end_date ?? $today);
            });
    }

    /** user_id => deployment covering $date, for the given members. */
    public static function on(string $date, array $userIds): Collection
    {
        if (!self::ready() || !$userIds) {
            return collect();
        }
        return DB::table('personnel_deployments')->whereIn('user_id', $userIds)
            ->where('start_date', '<=', $date)->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
            ->orderBy('start_date')->get(['user_id', 'kind', 'assignment', 'end_date'])->keyBy('user_id');
    }

    /** Record a deployment; with an end date and RNR days the credit is posted too. */
    public static function record(object $member, array $data, array $by, ?string $ip = null): array
    {
        $id = DB::table('personnel_deployments')->insertGetId([
            'user_id' => $member->id, 'serial_number' => $member->serial_number, 'unit_name' => $member->mother_unit ?? null,
            'kind' => $data['kind'], 'assignment' => trim($data['assignment']), 'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null, 'note' => $data['note'] ?? null,
            'recorded_by_type' => $by['type'] ?? null, 'recorded_by_id' => $by['id'] ?? null, 'recorded_by_name' => $by['name'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Log::channel('audit')->info('deployment.recorded', ['deployment_id' => $id, 'member_user_id' => $member->id, 'serial_number' => $member->serial_number,
            'kind' => $data['kind'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'] ?? null, 'ip' => $ip]);
        $msg = self::kindLabel($data['kind']) . ' of ' . $member->name . ' recorded';
        if (!empty($data['end_date']) && (float) ($data['rnr_days'] ?? 0) > 0) {
            $msg .= '; ' . self::grant(DB::table('personnel_deployments')->find($id), $member, (float) $data['rnr_days'], $by, $ip);
        }
        return [true, $msg . '.'];
    }

    /** End an active deployment, optionally granting RNR. */
    public static function end(object $dep, object $member, string $endDate, float $rnr, array $by, ?string $ip = null): string
    {
        DB::table('personnel_deployments')->where('id', $dep->id)->update(['end_date' => $endDate, 'updated_at' => now()]);
        Log::channel('audit')->info('deployment.ended', ['deployment_id' => $dep->id, 'member_user_id' => $member->id, 'serial_number' => $member->serial_number,
            'end_date' => $endDate, 'ip' => $ip]);
        $dep->end_date = $endDate;
        $msg = self::kindLabel($dep->kind) . ' of ' . $member->name . ' ended on ' . Carbon::parse($endDate)->format('M j, Y');
        if ($rnr > 0 && $dep->rnr_entry_id === null) {
            $msg .= '; ' . self::grant($dep, $member, $rnr, $by, $ip);
        }
        return $msg . '.';
    }

    private static function grant(object $dep, object $member, float $days, array $by, ?string $ip): string
    {
        $days = round($days, 2);
        $note = self::kindLabel($dep->kind) . ': ' . $dep->assignment . ' (' . LeaveCalendar::dateLabel($dep->start_date, $dep->end_date) . ')';
        $entry = LeaveCredits::post((int) $member->id, (string) $member->serial_number, 'rnr', 'grant', $days, $dep->end_date, mb_substr($note, 0, 500), null, $by);
        DB::table('personnel_deployments')->where('id', $dep->id)->update(['rnr_days' => $days, 'rnr_entry_id' => $entry, 'updated_at' => now()]);
        Log::channel('audit')->info('leave_credit.grant', ['entry_id' => $entry, 'member_user_id' => $member->id, 'serial_number' => $member->serial_number,
            'pool' => 'rnr', 'days' => $days, 'deployment_id' => $dep->id, 'by_type' => $by['type'] ?? null, 'by_id' => $by['id'] ?? null, 'ip' => $ip]);
        return LeaveCredits::fmt($days) . ' RNR days added';
    }
}
