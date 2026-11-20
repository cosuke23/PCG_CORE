<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devices signed in to a member account (owner request 2026-11-20). Each sign-in
 * gets a member_devices row whose id is kept in the session (member_device_id);
 * middleware member.device updates last_seen_at and signs the session out when
 * the row was signed out from another device (/security). Never throws into a
 * request: without the table everything simply works as before.
 */
class MemberDevices
{
    public const SESSION_KEY = 'member_device_id';

    public static function ready(): bool
    {
        static $ready = null;
        return app()->runningUnitTests() ? Schema::hasTable('member_devices') : ($ready ??= Schema::hasTable('member_devices'));
    }

    /** A short readable name from the user agent. */
    public static function label(?string $ua): string
    {
        $ua = (string) $ua;
        if (str_contains($ua, 'PCGHRMIS-Android/')) {
            return 'PCG HRMIS app on Android';
        }
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iPhone/iPad',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'unknown system',
        };
        return "$browser on $os";
    }

    /** Called right after a member signs in. */
    public static function start(Request $request, int $userId): void
    {
        if (!self::ready()) {
            return;
        }
        try {
            $ua = mb_substr((string) $request->userAgent(), 0, 255);
            $id = DB::table('member_devices')->insertGetId([
                'user_id' => $userId, 'label' => self::label($ua), 'user_agent' => $ua, 'ip' => $request->ip(),
                'created_at' => now(), 'last_seen_at' => now(),
            ]);
            $request->session()->put(self::SESSION_KEY, $id);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Per request: false when this session must be signed out (its device was
     * signed out elsewhere). Creates the row for sessions older than the feature.
     */
    public static function touch(Request $request, int $userId): bool
    {
        if (!self::ready()) {
            return true;
        }
        try {
            $id = $request->session()->get(self::SESSION_KEY);
            if (!$id) {
                self::start($request, $userId);
                return true;
            }
            $row = DB::table('member_devices')->where('id', $id)->first(['user_id', 'last_seen_at', 'signed_out_at']);
            if (!$row || (int) $row->user_id !== $userId || $row->signed_out_at !== null) {
                return false;
            }
            if (!$row->last_seen_at || now()->diffInMinutes($row->last_seen_at, true) >= 5) {
                DB::table('member_devices')->where('id', $id)->update(['last_seen_at' => now(), 'ip' => $request->ip()]);
            }
            return true;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function end(Request $request, string $by = 'self'): void
    {
        if (!self::ready() || !($id = $request->session()->get(self::SESSION_KEY))) {
            return;
        }
        try {
            DB::table('member_devices')->where('id', $id)->whereNull('signed_out_at')->update(['signed_out_at' => now(), 'signed_out_by' => $by]);
        } catch (\Throwable $e) {
        }
    }

    /** Devices still signed in (seen in the last 30 days), newest first. */
    public static function active(int $userId): Collection
    {
        if (!self::ready()) {
            return collect();
        }
        return DB::table('member_devices')->where('user_id', $userId)->whereNull('signed_out_at')
            ->where('last_seen_at', '>=', now()->subDays(30))->orderByDesc('last_seen_at')->get();
    }

    /** Sign out every other device of the member; returns how many. */
    public static function signOutOthers(int $userId, ?int $keepId, string $by = 'other_device'): int
    {
        if (!self::ready()) {
            return 0;
        }
        return DB::table('member_devices')->where('user_id', $userId)->whereNull('signed_out_at')
            ->when($keepId, fn ($q) => $q->where('id', '<>', $keepId))
            ->update(['signed_out_at' => now(), 'signed_out_by' => $by]);
    }

    public static function signOutOne(int $userId, int $deviceId): bool
    {
        return self::ready() && DB::table('member_devices')->where('user_id', $userId)->where('id', $deviceId)->whereNull('signed_out_at')
            ->update(['signed_out_at' => now(), 'signed_out_by' => 'other_device']) > 0;
    }
}
