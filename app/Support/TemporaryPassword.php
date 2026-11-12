<?php

namespace App\Support;

use App\Mail\AccountPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Temporary passwords for new admin and unit checker accounts (and resets).
 *
 * Nobody types or sees the password: it is generated here, emailed to the
 * account holder and stored only as a hash, and the account must change it at
 * the next sign-in (must_change_password). In production the log/array mailers
 * are refused, because they would never deliver it ('log' would write it to a file).
 */
class TemporaryPassword
{
    // no 0/O, 1/l/I: easy to read off an email
    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';
    private const DIGITS = '23456789';

    /** Whether the must_change_password column exists yet (before the migration it does not). */
    public static function flagReady(string $table): bool
    {
        // cached for this request only (a static would outlive it in tests and long-running workers)
        $key = 'pcg.must_change_password.' . $table;
        if (!app()->bound($key)) {
            try {
                $ready = \Illuminate\Support\Facades\Schema::hasColumn($table, 'must_change_password');
            } catch (\Throwable $e) {
                $ready = false;
            }
            app()->instance($key, $ready);
        }

        return (bool) app($key);
    }

    public static function generate(int $length = 16): string
    {
        $all = self::UPPER . self::LOWER . self::DIGITS;
        $chars = [self::pick(self::UPPER), self::pick(self::LOWER), self::pick(self::DIGITS)];
        while (count($chars) < $length) {
            $chars[] = self::pick($all);
        }
        // shuffle with a CSPRNG (shuffle() is not one)
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private static function pick(string $set): string
    {
        return $set[random_int(0, strlen($set) - 1)];
    }

    /** Null when the mail settings can deliver a password, else the reason. */
    public static function mailProblem(): ?string
    {
        if (app()->environment('production') && in_array(config('mail.default'), ['log', 'array'], true)) {
            return 'Email is not set up on this server (MAIL_MAILER is ' . config('mail.default') . '), so the password cannot be sent. Set the MAIL_* settings in .env first.';
        }

        return null;
    }

    /**
     * Emails the password. Throws when it cannot be sent, so the caller's
     * database transaction rolls back and no account is left with a password
     * nobody received.
     */
    public static function send(string $email, string $name, string $password, string $signInUrl, string $system, bool $reset = false): void
    {
        if ($problem = self::mailProblem()) {
            throw new \RuntimeException($problem);
        }
        try {
            Mail::to($email)->send(new AccountPassword($name, $email, $password, $signInUrl, $system, $reset));
        } catch (\Throwable $e) {
            // the mail error (SMTP reply) never contains the message body
            Log::error('Account password could not be emailed', ['error' => get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 200)]);
            throw new \RuntimeException('The email with the password could not be sent. Check the mail settings (MAIL_*), then try again.', 0, $e);
        }
    }
}
