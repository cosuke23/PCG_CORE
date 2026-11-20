<?php

namespace App\Support;

use App\Models\UnitUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clearances (NBI, Ombudsman, Sandiganbayan) near or past their "valid until"
 * date (owner request 2026-11-20): reminder emails to the member 30, 7 and 0 days
 * before (pcg:clearance-reminders, config('pcg.clearance.reminder_days')) and a
 * card on the unit checker dashboards. Each member's record is the newest
 * personnel_data row they own (older rows: by serial, approved accounts only),
 * the same rule as DocumentReview::rowFor. Dates are Asia/Manila.
 */
class ClearanceExpiry
{
    public const TZ = 'Asia/Manila';

    public static function today(): Carbon
    {
        return Carbon::now(self::TZ)->startOfDay();
    }

    /**
     * Clearances that end on or before today + $withinDays (expired ones included),
     * soonest first. $unit null = every unit.
     *
     * @return Collection<int, object{user_id: int, name: string, rank: ?string, email: ?string, serial_number: string, mother_unit: ?string, field: string, label: string, valid_until: string, days_left: int}>
     */
    public static function upcoming(?string $unit, int $withinDays = 30): Collection
    {
        if (!Schema::hasTable('personnel_data') || !Schema::hasTable('users')) {
            return collect();
        }
        $until = self::today()->addDays($withinDays)->toDateString();
        $cols = array_map(fn ($f) => "pd.{$f}_expiration", DocumentReview::EXPIRY_FIELDS);
        $rows = DB::table('personnel_data as pd')
            ->join('users as u', function ($j) {
                $j->on('u.id', '=', 'pd.personnel_id')
                    ->orWhere(fn ($w) => $w->whereNull('pd.personnel_id')->whereColumn('u.serial_number', 'pd.serial_number')
                        ->whereRaw('LOWER(u.status) = ?', ['approved']));
            })
            ->when($unit !== null, fn ($q) => $q->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower(trim($unit))]))
            ->where(function ($q) use ($cols, $until) {
                foreach ($cols as $c) {
                    $q->orWhere(fn ($w) => $w->whereNotNull($c)->where($c, '<=', $until));
                }
            })
            ->orderByDesc('pd.id')
            ->get(array_merge(['pd.id', 'pd.personnel_id', 'u.id as user_id', 'u.name', 'u.rank', 'u.email', 'u.serial_number', 'u.mother_unit'],
                $cols, array_map(fn ($f) => "pd.$f", DocumentReview::EXPIRY_FIELDS)));

        $today = self::today();
        $seen = [];
        $out = collect();
        foreach ($rows as $r) {
            if (isset($seen[$r->user_id])) {
                continue;                       // only the member's newest record counts
            }
            $seen[$r->user_id] = true;
            foreach (DocumentReview::EXPIRY_FIELDS as $f) {
                $date = $r->{$f . '_expiration'} ?? null;
                if (!$date || empty($r->$f) || $date > $until) {
                    continue;                   // no file, no date, or not due yet
                }
                $d = Carbon::parse($date, self::TZ)->startOfDay();
                $out->push((object) [
                    'user_id' => (int) $r->user_id, 'name' => (string) $r->name, 'rank' => $r->rank, 'email' => $r->email,
                    'serial_number' => (string) $r->serial_number, 'mother_unit' => $r->mother_unit,
                    'field' => $f, 'label' => DocumentReview::DOC_TYPES[$f] . ($f === 'nbi_clearance' ? '' : ' Clearance'),
                    'valid_until' => $d->toDateString(), 'days_left' => (int) $today->diffInDays($d, false),
                ]);
            }
        }
        return $out->sortBy('valid_until')->values();
    }

    /** Dashboard card data for the signed-in unit checker, once per request. */
    public static function forUnitChecker(): ?array
    {
        $attrs = request()->attributes;
        if ($attrs->has('unit_clearance_summary')) {
            return $attrs->get('unit_clearance_summary') ?: null;
        }
        $summary = [];
        try {
            $m1 = session('unit_role') === UnitUser::ROLE_M1;
            $list = self::upcoming($m1 ? null : (string) session('unit_name', ''), 30);
            if ($list->isNotEmpty()) {
                $summary = [
                    'expired' => $list->where('days_left', '<', 0)->count(),
                    'soon' => $list->where('days_left', '>=', 0)->count(),
                    'list' => $list,
                    'scope' => $m1 ? 'every unit' : (string) session('unit_name'),
                ];
            }
        } catch (\Throwable $e) {
            $summary = [];
        }
        $attrs->set('unit_clearance_summary', $summary);
        return $summary ?: null;
    }

    public static function when(int $daysLeft): string
    {
        return match (true) {
            $daysLeft < -1 => 'expired ' . abs($daysLeft) . ' days ago',
            $daysLeft === -1 => 'expired yesterday',
            $daysLeft === 0 => 'expires today',
            $daysLeft === 1 => 'expires tomorrow',
            default => "expires in $daysLeft days",
        };
    }
}
