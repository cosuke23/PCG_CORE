<?php

namespace App\Support;

use App\Mail\AdminLoginCode;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One-time sign-in codes sent by email (second step of admin sign-in).
 *
 * The code itself is never stored: the session keeps an HMAC of it, the
 * expiry, the number of attempts and when it was sent. A code works once,
 * expires after config('pcg.admin.email_code_minutes') and is discarded
 * after 5 wrong attempts.
 */
class EmailLoginCode
{
    public const MAX_ATTEMPTS = 5;
    public const RESEND_AFTER_SECONDS = 60;
    private const KEY = 'admin_email_code';

    public static function enabled(): bool
    {
        return config('pcg.admin.two_factor', 'email') === 'email';
    }

    /** Creates a code and emails it. Returns false if the email could not be sent. */
    public static function issue(AdminUser $admin): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = max(1, (int) config('pcg.admin.email_code_minutes', 10));

        try {
            Mail::to($admin->email)->send(new AdminLoginCode($admin, $code, $minutes));
        } catch (\Throwable $e) {
            Log::error('Admin sign-in code could not be emailed', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
            session()->forget(self::KEY);

            return false;
        }

        session([self::KEY => [
            'admin_id' => $admin->id,
            'hash' => self::hash($code),
            'expires_at' => now()->addMinutes($minutes)->getTimestamp(),
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ]]);

        return true;
    }

    public static function secondsUntilResend(): int
    {
        $sent = session(self::KEY . '.sent_at');

        return $sent ? max(0, self::RESEND_AFTER_SECONDS - (now()->getTimestamp() - $sent)) : 0;
    }

    /** Returns null when the code is correct, otherwise the reason it was refused. */
    public static function check(AdminUser $admin, string $input): ?string
    {
        $state = session(self::KEY);
        if (!$state || (int) $state['admin_id'] !== (int) $admin->id) {
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
            session()->forget(self::KEY); // single use

            return null;
        }

        session([self::KEY . '.attempts' => $state['attempts'] + 1]);
        $left = self::MAX_ATTEMPTS - $state['attempts'] - 1;

        return $left > 0 ? "That code is not correct. {$left} " . ($left === 1 ? 'try' : 'tries') . ' left.' : 'Too many wrong codes. Request a new code.';
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }

    /** a****@example.com */
    public static function masked(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1) . str_repeat('*', max(3, mb_strlen($local) - 1)) . '@' . $domain;
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
