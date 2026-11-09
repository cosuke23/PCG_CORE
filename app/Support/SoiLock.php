<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * SOI submission lock (owner decisions 2026-11-09): once the member submits
 * the SOI (users.soi_submitted_at set), all six steps are read-only, blank
 * fields included; every save is refused. Only the Adjutant and the HR admin
 * of the member's unit (and the super admin) can reopen it, with a reason.
 */
class SoiLock
{
    public const MESSAGE = 'Your SOI has been submitted and can no longer be edited. Contact your Adjutant or HR Unit Admin to make changes.';

    public static function enabled(): bool
    {
        return Schema::hasColumn('users', 'soi_submitted_at');
    }

    public static function submittedAt(?object $user): ?Carbon
    {
        if (!$user || !self::enabled() || empty($user->soi_submitted_at)) {
            return null;
        }

        return Carbon::parse($user->soi_submitted_at);
    }

    public static function locked(?object $user): bool
    {
        return self::submittedAt($user) !== null;
    }
}
