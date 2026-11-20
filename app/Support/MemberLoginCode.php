<?php

namespace App\Support;

use App\Mail\MemberLoginCode as MemberLoginCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Optional emailed sign-in code for members (owner request 2026-11-20): the member
 * turns it on at /security (users.login_code_enabled). Same rules as the unit
 * checkers' code: only an HMAC in the session, 10 minutes, works once, 5 tries.
 */
class MemberLoginCode
{
    public const MAX_ATTEMPTS = 5;
    public const RESEND_AFTER_SECONDS = 60;
    private const KEY = 'member_email_code';

    public static function available(): bool
    {
        return Schema::hasTable('users') && Schema::hasColumn('users', 'login_code_enabled');
    }

    public static function wanted(User $user): bool
    {
        return self::available() && (bool) $user->getAttribute('login_code_enabled');
    }

    public static function minutes(): int
    {
        return max(1, (int) config('pcg.unit.email_code_minutes', 10));
    }

    public static function issue(User $user): bool
    {
        if (app()->environment('production') && in_array(config('mail.default'), ['log', 'array'], true)) {
            Log::error('Member sign-in codes need a real mailer; MAIL_MAILER is ' . config('mail.default'));
            return false;
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            Mail::to($user->email)->send(new MemberLoginCodeMail((string) $user->name, $code, self::minutes()));
        } catch (\Throwable $e) {
            Log::error('Member sign-in code could not be emailed', ['user_id' => $user->id, 'error' => class_basename($e)]);
            return false;
        }
        session([self::KEY => ['user_id' => $user->id, 'hash' => self::hash($code),
            'expires_at' => now()->getTimestamp() + self::minutes() * 60, 'sent_at' => now()->getTimestamp(), 'attempts' => 0]]);
        return true;
    }

    public static function secondsUntilResend(): int
    {
        $sent = session(self::KEY . '.sent_at');
        return $sent ? max(0, self::RESEND_AFTER_SECONDS - (now()->getTimestamp() - $sent)) : 0;
    }

    public static function check(User $user, string $input): ?string
    {
        $state = session(self::KEY);
        if (!$state || (int) $state['user_id'] !== (int) $user->id) {
            return 'No code is pending. Request a new code.';
        }
        if (now()->getTimestamp() > $state['expires_at']) {
            session()->forget(self::KEY);
            return 'That code has expired. Request a new code.';
        }
        if ($state['attempts'] >= self::MAX_ATTEMPTS) {
            session()->forget(self::KEY);
            return 'Too many wrong codes. Request a new code.';
        }
        $digits = preg_replace('/\D+/', '', $input);
        if (strlen($digits) === 6 && hash_equals($state['hash'], self::hash($digits))) {
            session()->forget(self::KEY);
            return null;
        }
        session([self::KEY . '.attempts' => $state['attempts'] + 1]);
        $left = self::MAX_ATTEMPTS - $state['attempts'] - 1;
        return $left > 0 ? "That code is not correct. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.' : 'Too many wrong codes. Request a new code.';
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', 'member|' . $code, (string) config('app.key'));
    }
}
