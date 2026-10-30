<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HashPassword extends Command
{
    protected $signature = 'pcg:hash-password';

    protected $description = 'Generate a bcrypt hash for ADMIN_PASSWORD_HASH in .env';

    public function handle()
    {
        $password = $this->secret('Password');
        $confirm  = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');
            return 1;
        }

        if (strlen((string) $password) < 12) {
            $this->error('Use at least 12 characters.');
            return 1;
        }

        $this->line('Add this line to .env (single quotes are required because the hash contains "$"):');
        $this->line("ADMIN_PASSWORD_HASH='" . Hash::make($password) . "'");

        return 0;
    }
}
