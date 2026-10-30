<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTestCommandTest extends TestCase
{
    public function test_sends_and_reports_failures()
    {
        $this->artisan('pcg:mail-test', ['to' => 'not-an-email'])->assertFailed();

        config(['mail.default' => 'array']);
        $this->artisan('pcg:mail-test', ['to' => 'me@example.test'])->expectsOutputToContain('nothing is sent')->assertSuccessful();

        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('Connection refused'));
        $this->artisan('pcg:mail-test', ['to' => 'me@example.test'])->expectsOutputToContain('Connection refused')->assertFailed();
    }

    public function test_password_is_never_printed()
    {
        config(['mail.default' => 'array', 'mail.mailers.smtp.password' => 'super-secret-pw']);
        $this->artisan('pcg:mail-test', ['to' => 'me@example.test'])->doesntExpectOutputToContain('super-secret-pw')->assertSuccessful();
    }
}
