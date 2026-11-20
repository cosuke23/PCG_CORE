<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email with the one-time unit checker sign-in code. Sent immediately (not queued). */
class UnitLoginCode extends Mailable
{
    use Queueable;

    public function __construct(public string $name, public string $code, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        // the code is only in the body: subjects show on lock screens and in mail logs
        return new Envelope(subject: 'MEPCOM HRMIS unit checker sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.unit-login-code', text: 'emails.unit-login-code-text');
    }
}
