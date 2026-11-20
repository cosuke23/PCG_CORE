<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** To a unit checker: leave requests of their scope that have waited too long (pcg:leave-reminders). */
class LeaveWaiting extends Mailable
{
    use Queueable;

    /** @param array<int, array{name: string, type: string, dates: string, waiting: int}> $requests */
    public function __construct(
        public string $name,
        public string $scope,
        public array $requests,
        public string $url,
        public string $system,
    ) {
    }

    public function envelope(): Envelope
    {
        $n = count($this->requests);
        return new Envelope(subject: "{$this->system}: {$n} leave " . ($n === 1 ? 'request is' : 'requests are') . ' waiting for your approval');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.leave-waiting', text: 'emails.leave-waiting-text');
    }
}
