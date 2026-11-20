<?php

namespace App\Support;

use App\Mail\UnitLoginCode as UnitLoginCodeMail;
use App\Models\UnitUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One-time sign-in code by email: the second step of unit checker and M1
 * checker sign-in (config pcg.unit.two_factor = 'email'). Same rules as the
 * admins' code (EmailLoginCode): only an HMAC of the code is kept in the session,
 * it expires after config('pcg.unit.email_code_minutes'), works once and is
 * dropped after 5 wrong tries. The checker is signed in only after the code.
 */
class UnitLoginCode
{
    public const MAX_ATTEMPTS = 5;
    public const RESEND_AFTER_SECONDS = 60;
    private const KEY = 'unit_email_code';

    public static function enabled(): bool
    {
        return config('pcg.unit.two_factor', 'email') === 'email';
    }

    public static function minutes(): int
    {
        return max(1, (int) config('pcg.unit.email_code_minutes', 10));
    }

    /** Creates a code and emails it; false if it could not be sent. */
    public static function issue(UnitUser $user): bool
    {
        if (app()->environment('production') && in_array(config('mail.default'), ['log', 'array'], true)) {
            Log::error('Unit checker sign-in codes need a real mailer; MAIL_MAILER is ' . config('mail.default'));
            session()->forget(self::KEY);
            return false;
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            Mail::to($user->email)->send(new UnitLoginCodeMail((string) ($user->full_name ?: 'unit checker'), $code, self::minutes()));
        } catch (\Throwable $e) {
            Log::error('Unit checker sign-in code could not be emailed', ['unit_user_id' => $user->id, 'error' => class_basename($e)]);
            session()->forget(self::KEY);
            return false;
        }
        session([self::KEY => [
            'unit_user_id' => $user->id,
            'hash' => self::hash($code),
            'expires_at' => now()->addMinutes(self::minutes())->getTimestamp(),
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

    /** Null when the code is right, otherwise why it was refused. */
    public static function check(UnitUser $user, string $input): ?string
    {
        $state = session(self::KEY);
        if (!$state || (int) $state['unit_user_id'] !== (int) $user->id) {
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

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', 'unit|' . $code, (string) config('app.key'));
    }
}
