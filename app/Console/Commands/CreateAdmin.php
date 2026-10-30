<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'pcg:create-admin {--role=super_admin : super_admin, hr_admin or lms_admin}';

    protected $description = 'Create an administrator account (two-factor setup happens at first sign-in)';

    public function handle()
    {
        if (!Schema::hasTable('admin_users')) {
            $this->error('The admin_users table does not exist yet. Back up the database, then run: php artisan migrate');
            return 1;
        }

        $data = [
            'name' => $this->ask('Full name'),
            'email' => strtolower((string) $this->ask('Email')),
            'password' => $this->secret('Password (min. 12 characters)'),
            'role' => $this->option('role'),
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email',
            'password' => ['required', Password::min(12)],
            'role' => 'required|in:' . implode(',', array_keys(AdminUser::ROLES)),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        AdminUser::create(array_merge($data, ['password' => Hash::make($data['password']), 'is_active' => true]));

        $this->info("Admin {$data['email']} created ({$data['role']}). Sign in at /admin/login and set up two-factor sign-in.");
        $this->line('The .env setup admin (ADMIN_EMAIL) no longer works now that an account exists.');

        return 0;
    }
}
