<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications for members (owner request 2026-11-20): written by the
 * modules (leave decisions, document and 201 reviews, clearance reminders, SOI
 * reopened), listed at /notifications with a bell count in layouts/personnel,
 * and polled by the Android app without a session (/api/app/notifications,
 * appToken()). Never throws into the caller.
 */
class MemberNotifications
{
    public static function ready(): bool
    {
        static $ready = null;
        if (app()->runningUnitTests()) {
            return Schema::hasTable('member_notifications');
        }
        return $ready ??= Schema::hasTable('member_notifications');
    }

    /** Add a notification. $url is a portal path such as /leave. */
    public static function send(int $userId, string $kind, string $title, ?string $body = null, ?string $url = null): void
    {
        try {
            if ($userId <= 0 || !self::ready()) {
                return;
            }
            DB::table('member_notifications')->insert([
                'user_id' => $userId, 'kind' => mb_substr($kind, 0, 40), 'title' => mb_substr($title, 0, 150),
                'body' => $body !== null ? mb_substr($body, 0, 500) : null,
                'url' => $url !== null && str_starts_with($url, '/') ? mb_substr($url, 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('member_notification.failed', ['user_id' => $userId, 'kind' => $kind, 'error' => class_basename($e)]);
        }
    }

    public static function unread(int $userId): int
    {
        try {
            return self::ready() ? DB::table('member_notifications')->where('user_id', $userId)->whereNull('read_at')->count() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function latest(int $userId, int $take = 50): Collection
    {
        return self::ready()
            ? DB::table('member_notifications')->where('user_id', $userId)->orderByDesc('id')->limit($take)->get()
            : collect();
    }

    public static function markAllRead(int $userId): void
    {
        if (self::ready()) {
            DB::table('member_notifications')->where('user_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);
        }
    }

    /**
     * Token the Android app uses to poll without a session. Tied to the account's
     * password hash, so changing the password (or a reset) revokes it.
     */
    public static function appToken(object $user): string
    {
        return hash_hmac('sha256', 'app-notify|' . $user->id . '|' . ($user->password ?? ''), (string) config('app.key'));
    }

    public static function checkToken(object $user, string $token): bool
    {
        return $token !== '' && hash_equals(self::appToken($user), $token);
    }
}
