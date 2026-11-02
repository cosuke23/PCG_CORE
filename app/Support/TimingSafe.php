<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * A bcrypt hash of a random value, made once per process, for checking a
 * password when there is no account: the failed sign-in then costs the same
 * time as one with a real account.
 */
class TimingSafe
{
    private static ?string $hash = null;

    public static function dummyHash(): string
    {
        return self::$hash ??= Hash::make(bin2hex(random_bytes(16)));
    }
}
