<?php

namespace App\Support;

use App\Models\OrgUnit;
use App\Models\Personnel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One source of truth for a member's unit (owner request 2026-11-20, scope agreed:
 * a mismatch report plus one-click fixes). Two places hold a member's unit today:
 * users.mother_unit (chosen at registration; unit checkers, leave and the member
 * portal use it) and the current personnel_assignments row (the HRMIS master;
 * OrgScope and the admin console use it). A member matches when the assignment's
 * unit, or one of its parents, has the code or name in users.mother_unit.
 */
class UnitMismatch
{
    public const STATES = [
        'mismatch' => 'Different units',
        'no_assignment' => 'No current assignment',
        'no_personnel' => 'Not in the personnel master',
        'unknown_unit' => 'Sign-up unit not in the org structure',
        'no_mother_unit' => 'No sign-up unit',
        'ok' => 'Same unit',
    ];

    /** @return array<int, OrgUnit> every org unit by id, loaded once per request */
    private static function units(): array
    {
        $attrs = request()->attributes;
        if (!$attrs->has('unit_mismatch_units')) {
            $attrs->set('unit_mismatch_units', OrgUnit::query()->get()->keyBy('id')->all());
        }
        return $attrs->get('unit_mismatch_units');
    }

    /** The org unit whose code (or name) is $label, or null. */
    public static function unitByLabel(?string $label): ?OrgUnit
    {
        $l = mb_strtolower(trim((string) $label));
        if ($l === '') {
            return null;
        }
        foreach (self::units() as $u) {
            if (mb_strtolower((string) $u->code) === $l) {
                return $u;
            }
        }
        foreach (self::units() as $u) {
            if (mb_strtolower((string) $u->name) === $l) {
                return $u;
            }
        }
        return null;
    }

    /** @return OrgUnit[] the unit and its parents, nearest first */
    public static function chain(?int $unitId): array
    {
        $out = [];
        $units = self::units();
        $guard = 0;
        while ($unitId && isset($units[$unitId]) && $guard++ < 20) {
            $out[] = $units[$unitId];
            $unitId = $units[$unitId]->parent_id;
        }
        return $out;
    }

    /**
     * The users.mother_unit value an assignment unit should give: the nearest unit
     * in its chain that has unit checkers (unit_users.unit_name), else its own code.
     */
    public static function motherUnitFor(int $unitId): ?string
    {
        $checkerUnits = Schema::hasTable('unit_users')
            ? DB::table('unit_users')->whereNotNull('unit_name')->pluck('unit_name')->map(fn ($n) => mb_strtolower(trim($n)))->unique()->flip()
            : collect();
        $chain = self::chain($unitId);
        foreach ($chain as $u) {
            if ($checkerUnits->has(mb_strtolower((string) $u->code))) {
                return (string) $u->code;
            }
        }
        return $chain ? (string) $chain[0]->code : null;
    }

    /**
     * Every approved member with a serial number, with both units and the state.
     * $serials limits to the admin's scope (null = everyone). Members without a
     * personnel record are listed only for org-wide admins (they have no unit to scope by).
     */
    public static function rows(?array $serials): Collection
    {
        $users = DB::table('users')->whereNotNull('serial_number')->where('serial_number', '<>', '')
            ->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
            ->orderBy('mother_unit')->orderBy('name')
            ->get(['id', 'name', 'rank', 'serial_number', 'mother_unit']);
        $personnel = Personnel::query()->get(['id', 'serial_number'])
            ->keyBy(fn ($p) => Personnel::normalizeSerial($p->serial_number));
        $current = DB::table('personnel_assignments')->whereNull('end_date')->get(['personnel_id', 'org_unit_id', 'start_date'])
            ->keyBy('personnel_id');
        $units = self::units();

        $out = collect();
        foreach ($users as $u) {
            $serial = Personnel::normalizeSerial($u->serial_number);
            if ($serials !== null && !in_array($serial, $serials, true)) {
                continue;
            }
            $p = $personnel[$serial] ?? null;
            $a = $p ? ($current[$p->id] ?? null) : null;
            $assigned = $a ? ($units[$a->org_unit_id] ?? null) : null;
            $mother = trim((string) $u->mother_unit);
            $motherUnit = self::unitByLabel($mother);

            $state = match (true) {
                $p === null => 'no_personnel',
                $assigned === null => 'no_assignment',
                $mother === '' => 'no_mother_unit',
                default => collect(self::chain($assigned->id))->contains(fn ($x) => strcasecmp((string) $x->code, $mother) === 0 || strcasecmp((string) $x->name, $mother) === 0)
                    ? 'ok' : ($motherUnit ? 'mismatch' : 'unknown_unit'),
            };
            $suggest = $assigned ? self::motherUnitFor($assigned->id) : null;
            $out->push((object) [
                'user_id' => $u->id, 'name' => $u->name, 'rank' => $u->rank, 'serial_number' => $u->serial_number,
                'mother_unit' => $mother, 'mother_unit_id' => $motherUnit?->id,
                'personnel_id' => $p?->id, 'assigned' => $assigned, 'assigned_since' => $a?->start_date,
                'state' => $state,
                // fix 1: set the sign-up unit from the assignment
                'can_use_assignment' => $assigned !== null && $suggest !== null && strcasecmp($suggest, $mother) !== 0,
                'suggested_mother_unit' => $suggest,
                // fix 2: record an assignment to the sign-up unit
                'can_use_mother_unit' => $p !== null && $motherUnit !== null && $state !== 'ok',
            ]);
        }
        return $out;
    }
}
