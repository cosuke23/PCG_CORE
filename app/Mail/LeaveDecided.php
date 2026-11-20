<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To the member: their leave request was approved or declined (LeaveActions::decide). */
class LeaveDecided extends Mailable
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $type,
        public string $dates,
        public int $days,
        public bool $approved,
        public ?string $note,
        public string $url,
        public string $system,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->system}: your leave is " . ($this->approved ? 'approved' : 'declined'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.leave-decided', text: 'emails.leave-decided-text');
    }
}
