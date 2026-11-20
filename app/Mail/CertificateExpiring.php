<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To a member: a training certificate or 201 document is about to expire (pcg:certificate-reminders). */
class CertificateExpiring extends Mailable
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $label,
        public string $validUntil,
        public int $daysLeft,
        public string $url,
        public string $system,
    ) {
    }

    public function envelope(): Envelope
    {
        $when = $this->daysLeft <= 0 ? 'expires today' : "expires in {$this->daysLeft} " . ($this->daysLeft === 1 ? 'day' : 'days');
        return new Envelope(subject: "{$this->system}: your {$this->label} {$when}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.certificate-expiring', text: 'emails.certificate-expiring-text');
    }
}
