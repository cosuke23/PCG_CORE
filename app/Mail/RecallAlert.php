<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Emergency recall to a member (App\Support\Recalls). */
class RecallAlert extends Mailable
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $title,
        public string $body,
        public ?string $reportTo,
        public string $sender,
        public string $url,
        public string $system,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "RECALL: {$this->title}");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.recall-text');
    }
}
