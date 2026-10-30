<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a test email and shows the mail settings in effect (password hidden),
 * so the email sign-in codes can be checked without signing in.
 */
class MailTest extends Command
{
    protected $signature = 'pcg:mail-test {to : Address to send the test email to}';

    protected $description = 'Send a test email and show the active mail settings';

    public function handle()
    {
        $to = $this->argument('to');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$to}' is not a valid email address.");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $smtp = config('mail.mailers.smtp', []);
        $this->table(['Setting', 'Value in effect'], [
            ['MAIL_MAILER', $mailer],
            ['MAIL_HOST', $smtp['host'] ?? ''],
            ['MAIL_PORT', $smtp['port'] ?? ''],
            ['MAIL_ENCRYPTION / scheme', ($smtp['encryption'] ?? $smtp['scheme'] ?? '') ?: '(none)'],
            ['MAIL_USERNAME', $smtp['username'] ?? ''],
            ['MAIL_PASSWORD', empty($smtp['password']) ? '(empty)' : '(set, hidden)'],
            ['MAIL_FROM_ADDRESS', config('mail.from.address')],
            ['MAIL_FROM_NAME', config('mail.from.name')],
            ['Config cached', app()->configurationIsCached() ? 'YES - run php artisan config:clear after editing .env' : 'no'],
        ]);

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER is '{$mailer}': nothing is sent; the email is only written to storage/logs/laravel.log.");
            $this->line('Set MAIL_MAILER=smtp (and the MAIL_* values) in .env, then run php artisan config:clear.');
        }

        try {
            Mail::raw("This is a test email from MEPCOM HRMIS.\n\nIf you received it, admin sign-in codes can be emailed.", function ($message) use ($to) {
                $message->to($to)->subject('MEPCOM HRMIS mail test');
            });
        } catch (\Throwable $e) {
            $this->error('Sending failed: ' . $e->getMessage());
            $this->line('Common fixes: Gmail needs an App Password (not your normal password, and 2-Step Verification on);');
            $this->line('port 587 goes with MAIL_ENCRYPTION=tls, port 465 with ssl; MAIL_FROM_ADDRESS should be the sending account.');

            return self::FAILURE;
        }

        $this->info($mailer === 'smtp'
            ? "Sent to {$to} through {$smtp['host']}. Check the inbox and the spam folder."
            : "Handed to the '{$mailer}' mailer (not delivered by email).");

        return self::SUCCESS;
    }
}
