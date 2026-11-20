<?php

namespace App\Support;

/**
 * Leave types for the leave module (owner request 2026-11-20). Built from the
 * usual rules for Philippine uniformed services and the national leave laws,
 * plus rest and recreation (RNR); limits are defaults until the PCG directive
 * is supplied. `max_days` caps one request; `sensitive` types (health and
 * personal matters) show only as "On leave" to the rest of the unit; `pool`
 * types are charged to that credit pool (App\Support\LeaveCredits).
 */
class LeaveTypes
{
    public const TYPES = [
        'rnr' => ['label' => 'Rest and recreation (RNR)', 'hint' => 'After sea duty, a deployment or a hardship assignment. Uses your RNR credits.', 'max_days' => 30, 'sensitive' => false, 'pool' => 'rnr'],
        'ordinary' => ['label' => 'Ordinary (annual) leave', 'hint' => 'Uses your ordinary leave credits.', 'max_days' => 60, 'sensitive' => false, 'pool' => 'annual'],
        'sick' => ['label' => 'Sick leave', 'hint' => 'A medical certificate may be required.', 'max_days' => 60, 'sensitive' => true],
        'convalescent' => ['label' => 'Convalescent leave', 'hint' => 'Recovery after hospitalisation or surgery, on a medical officer\'s advice.', 'max_days' => 90, 'sensitive' => true],
        'emergency' => ['label' => 'Emergency or compassionate leave', 'hint' => 'Death or serious illness in the family, disasters.', 'max_days' => 15, 'sensitive' => false],
        'maternity' => ['label' => 'Maternity leave', 'hint' => '105 days; 120 for solo parents (RA 11210).', 'max_days' => 120, 'sensitive' => true],
        'paternity' => ['label' => 'Paternity leave', 'hint' => '7 days (RA 8187).', 'max_days' => 7, 'sensitive' => false],
        'solo_parent' => ['label' => 'Solo parent leave', 'hint' => '7 days a year (RA 8972, RA 11861).', 'max_days' => 7, 'sensitive' => false, 'pool' => 'solo_parent'],
        'vawc' => ['label' => 'Leave for victims of violence (VAWC)', 'hint' => '10 days a year (RA 9262).', 'max_days' => 10, 'sensitive' => true, 'pool' => 'vawc'],
        'special_women' => ['label' => 'Special leave for women', 'hint' => 'Up to 2 months after gynaecological surgery (RA 9710).', 'max_days' => 60, 'sensitive' => true],
        'study' => ['label' => 'Study or schooling leave', 'hint' => 'For an approved course.', 'max_days' => 120, 'sensitive' => false],
        'terminal' => ['label' => 'Terminal leave', 'hint' => 'Accumulated leave before retirement or separation. Uses your ordinary leave credits.', 'max_days' => 120, 'sensitive' => false, 'pool' => 'annual'],
        'pass' => ['label' => 'Pass', 'hint' => 'A short absence of up to 3 days.', 'max_days' => 3, 'sensitive' => false],
        'without_pay' => ['label' => 'Leave without pay', 'hint' => 'Not charged to credits; pay is withheld.', 'max_days' => 90, 'sensitive' => false],
    ];

    /** Longest request any type allows (days, inclusive). */
    public const MAX_DAYS = 120;

    /** Default of config('pcg.leave.backdate_days'): how far back a request may start. */
    public const BACKDATE_DAYS = 30;

    public static function backdateDays(): int
    {
        return max(0, (int) config('pcg.leave.backdate_days', self::BACKDATE_DAYS));
    }

    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function label(?string $key): string
    {
        return self::TYPES[$key]['label'] ?? 'Leave';
    }

    public static function maxDays(string $key): int
    {
        $override = config('pcg.leave.max_days.' . $key);
        return $override !== null ? (int) $override : (self::TYPES[$key]['max_days'] ?? self::MAX_DAYS);
    }

    public static function sensitive(?string $key): bool
    {
        return (bool) (self::TYPES[$key]['sensitive'] ?? true);
    }

    /** What other members of the unit see for this leave. */
    public static function publicLabel(?string $key): string
    {
        return self::sensitive($key) ? 'On leave' : self::label($key);
    }
}
