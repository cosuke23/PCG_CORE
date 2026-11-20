<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email with the one-time member sign-in code (optional, chosen on /security). Sent immediately (not queued). */
class MemberLoginCode extends Mailable
{
    use Queueable;

    public function __construct(public string $name, public string $code, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        // the code is only in the body: subjects show on lock screens and in mail logs
        return new Envelope(subject: 'MEPCOM HRMIS sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.member-login-code', text: 'emails.member-login-code-text');
    }
}
