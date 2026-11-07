<?php

namespace App\Mail;

use App\Models\AdminUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email with the one-time admin sign-in code. Sent immediately (not queued). */
class AdminLoginCode extends Mailable
{
    use Queueable;

    public function __construct(public AdminUser $admin, public string $code, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        // The code is only in the body: subjects show on lock screens and in mail logs.
        return new Envelope(subject: 'MEPCOM HRMIS sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-login-code', text: 'emails.admin-login-code-text');
    }
}
