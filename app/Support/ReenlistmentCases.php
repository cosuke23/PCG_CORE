<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-enlistment workflow (owner request 2026-11-20): a case per non-officer with a
 * status and a checklist of requirements (config('pcg.reenlistment.requirements');
 * defaults until the PCG directive is supplied). Kept on /admin/reenlistment/{id}
 * by capability reenlistment.manage; members are notified when the status changes.
 */
class ReenlistmentCases
{
    public const STATUSES = [
        'not_started' => 'Not started',
        'in_progress' => 'Gathering requirements',
        'submitted' => 'Submitted for approval',
        'approved' => 'Re-enlistment approved',
        'not_reenlisting' => 'Not re-enlisting',
    ];

    public static function ready(): bool
    {
        return Schema::hasTable('reenlistment_cases');
    }

    /** @return array<string, string> key => label */
    public static function requirements(): array
    {
        $out = [];
        foreach ((array) config('pcg.reenlistment.requirements', []) as $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $out[substr(sha1(mb_strtolower($label)), 0, 10)] = $label;
            }
        }
        return $out;
    }

    public static function for(int $userId): ?object
    {
        if (!self::ready()) {
            return null;
        }
        $c = DB::table('reenlistment_cases')->where('user_id', $userId)->first();
        if ($c) {
            $c->done = json_decode((string) $c->checklist, true) ?: [];
        }
        return $c;
    }

    /** user_id => case, for a list page. */
    public static function forMany(array $userIds): Collection
    {
        if (!self::ready() || !$userIds) {
            return collect();
        }
        return DB::table('reenlistment_cases')->whereIn('user_id', $userIds)->get()->each(function ($c) {
            $c->done = json_decode((string) $c->checklist, true) ?: [];
        })->keyBy('user_id');
    }

    /**
     * Save status, checklist (keys ticked now) and note. Keeps the date each item was
     * first ticked. Returns [old status, new status].
     */
    public static function save(object $person, string $status, array $ticked, ?string $note, array $by): array
    {
        $old = self::for((int) $person->id);
        $done = [];
        foreach (array_keys(self::requirements()) as $k) {
            if (in_array($k, $ticked, true)) {
                $done[$k] = $old->done[$k] ?? now('Asia/Manila')->toDateString();
            }
        }
        $values = ['serial_number' => (string) $person->serial_number, 'ete' => $person->ete ?: null, 'status' => $status,
            'checklist' => json_encode($done), 'note' => $note, 'updated_by_name' => $by['name'] ?? null, 'updated_by_id' => $by['id'] ?? null,
            'updated_at' => now()];
        if ($old) {
            DB::table('reenlistment_cases')->where('id', $old->id)->update($values);
        } else {
            DB::table('reenlistment_cases')->insert($values + ['user_id' => $person->id, 'created_at' => now()]);
        }
        return [$old->status ?? 'not_started', $status];
    }
}
