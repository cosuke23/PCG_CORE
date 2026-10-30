<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    // RFC 6238 Appendix B (SHA1, secret "12345678901234567890"), last 6 digits.
    public function test_rfc6238_vectors()
    {
        $secret = Totp::base32Encode('12345678901234567890');

        foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037'] as $time => $code) {
            $this->assertSame($code, Totp::code($secret, Totp::currentStep($time)), "t=$time");
        }
    }

    public function test_base32_round_trip()
    {
        $bytes = random_bytes(20);
        $this->assertSame($bytes, Totp::base32Decode(Totp::base32Encode($bytes)));
    }

    public function test_verify_accepts_drift_and_rejects_replay()
    {
        $secret = Totp::generateSecret();
        $t = 1_800_000_000;
        $step = Totp::currentStep($t);

        $this->assertSame($step, Totp::verify($secret, Totp::code($secret, $step), null, $t));
        $this->assertSame($step - 1, Totp::verify($secret, Totp::code($secret, $step - 1), null, $t));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step - 3), null, $t));
        // the same code cannot be used twice
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step), $step, $t));
        $this->assertNull(Totp::verify($secret, 'abc123', null, $t));
    }
}
