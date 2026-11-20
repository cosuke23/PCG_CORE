<?php

namespace App\Support;

use App\Models\Personnel;
use App\Models\UnitUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Training certificates and 201 documents that expire (owner request 2026-11-20):
 * personnel_trainings.valid_until and the current personnel_documents.expires_on.
 * Members are emailed and notified 30, 7 and 0 days before (pcg:certificate-reminders,
 * the same days as the clearances); unit checkers see a card and admins the list
 * /admin/expiring. Members are found by personnel.user_id, else by serial number
 * (approved accounts only). Dates are Asia/Manila.
 */
class CertificateExpiry
{
    public static function today(): Carbon
    {
        return Carbon::now('Asia/Manila')->startOfDay();
    }

    /**
     * Items ending on or before today + $withinDays (expired ones from the last
     * 60 days included), soonest first. $unit = users.mother_unit; $serials = admin scope.
     */
    public static function upcoming(?string $unit, ?array $serials, int $withinDays = 30): Collection
    {
        if (!Schema::hasTable('personnel')) {
            return collect();
        }
        $from = self::today()->subDays(60)->toDateString();
        $until = self::today()->addDays($withinDays)->toDateString();
        $items = collect();
        if (Schema::hasTable('personnel_trainings')) {
            $items = $items->merge(DB::table('personnel_trainings')->whereNotNull('valid_until')->whereBetween('valid_until', [$from, $until])
                ->get(['personnel_id', 'title', 'valid_until as until', DB::raw("'training' as source")]));
        }
        if (Schema::hasTable('personnel_documents') && Schema::hasTable('document_categories')) {
            $items = $items->merge(DB::table('personnel_documents as d')->join('document_categories as c', 'c.id', '=', 'd.category_id')
                ->where('d.is_current', true)->whereNotNull('d.expires_on')->whereBetween('d.expires_on', [$from, $until])
                ->get(['d.personnel_id', DB::raw('COALESCE(NULLIF(d.title, \'\'), c.name) as title'), 'd.expires_on as until', DB::raw("'201' as source")]));
        }
        if ($items->isEmpty()) {
            return collect();
        }
        $people = DB::table('personnel')->whereIn('id', $items->pluck('personnel_id')->unique()->all())
            ->where('service_status', 'active')->get(['id', 'serial_number', 'full_name', 'user_id'])->keyBy('id');
        $users = DB::table('users')->where(fn ($q) => $q->whereIn('id', $people->pluck('user_id')->filter()->all())
            ->orWhereIn(DB::raw('UPPER(TRIM(serial_number))'), $people->map(fn ($p) => Personnel::normalizeSerial($p->serial_number))->all()))
            ->get(['id', 'name', 'rank', 'email', 'serial_number', 'mother_unit', 'status']);

        $today = self::today();
        $out = collect();
        foreach ($items as $i) {
            $p = $people[$i->personnel_id] ?? null;
            if (!$p) {
                continue;
            }
            $serial = Personnel::normalizeSerial($p->serial_number);
            if ($serials !== null && !in_array($serial, $serials, true)) {
                continue;
            }
            $u = $users->first(fn ($x) => $p->user_id && (int) $x->id === (int) $p->user_id)
                ?? $users->first(fn ($x) => Personnel::normalizeSerial($x->serial_number) === $serial && strtolower(trim((string) $x->status)) === 'approved');
            if ($unit !== null && (!$u || strcasecmp(trim((string) $u->mother_unit), trim($unit)) !== 0)) {
                continue;
            }
            $d = Carbon::parse($i->until, 'Asia/Manila')->startOfDay();
            $out->push((object) [
                'personnel_id' => (int) $p->id, 'user_id' => $u?->id, 'email' => $u?->email, 'name' => $u->name ?? $p->full_name,
                'rank' => $u?->rank, 'serial_number' => $p->serial_number, 'mother_unit' => $u?->mother_unit,
                'title' => (string) $i->title, 'source' => $i->source, 'valid_until' => $d->toDateString(),
                'days_left' => (int) $today->diffInDays($d, false),
            ]);
        }
        return $out->sortBy('valid_until')->values();
    }

    /** Dashboard card for the signed-in unit checker (own unit; M1 every unit), once per request. */
    public static function forUnitChecker(): ?array
    {
        $attrs = request()->attributes;
        if ($attrs->has('unit_certificate_summary')) {
            return $attrs->get('unit_certificate_summary') ?: null;
        }
        $summary = [];
        try {
            $m1 = session('unit_role') === UnitUser::ROLE_M1;
            $list = self::upcoming($m1 ? null : (string) session('unit_name', ''), null, 30);
            if ($list->isNotEmpty()) {
                $summary = ['expired' => $list->where('days_left', '<', 0)->count(), 'soon' => $list->where('days_left', '>=', 0)->count(), 'list' => $list,
                    'scope' => $m1 ? 'every unit' : (string) session('unit_name')];
            }
        } catch (\Throwable $e) {
            $summary = [];
        }
        $attrs->set('unit_certificate_summary', $summary);
        return $summary ?: null;
    }
}
