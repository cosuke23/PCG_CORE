<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To a non-officer: their ETE is 6 or 3 months away (pcg:reenlistment-reminders). */
class ReenlistmentDue extends Mailable
{
    use Queueable;

    /** @param string[] $requirements */
    public function __construct(
        public string $name,
        public string $ete,
        public int $months,
        public array $requirements,
        public string $url,
        public string $system,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->system}: your term of enlistment ends in about {$this->months} months");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.reenlistment-due-text');
    }
}
