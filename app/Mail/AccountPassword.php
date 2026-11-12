<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email with a new account's temporary password (or a reset one). Sent immediately, not queued. */
class AccountPassword extends Mailable
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $signInUrl,
        public string $system,
        public bool $reset = false,
    ) {
    }

    public function envelope(): Envelope
    {
        // The password is only in the body: subjects show on lock screens and in mail logs.
        return new Envelope(subject: $this->reset ? "{$this->system}: your new temporary password" : "{$this->system}: your new account");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-password', text: 'emails.account-password-text');
    }
}
