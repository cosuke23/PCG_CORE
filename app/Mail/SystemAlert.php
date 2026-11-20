<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Monitoring alert to the system administrators (App\Support\Monitor): plain text only. */
class SystemAlert extends Mailable
{
    use Queueable;

    /** @param string[] $lines */
    public function __construct(
        public string $title,
        public array $lines,
        public string $url,
        public string $system,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->system} alert: {$this->title}");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.system-alert-text');
    }
}
