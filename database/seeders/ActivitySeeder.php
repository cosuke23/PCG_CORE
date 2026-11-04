<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/** contact_messages and a few admin_audit_logs entries, so the Messages and Audit log pages have content. */
class ActivitySeeder extends DevOnlySeeder
{
    public function run(): void
    {
        $this->ensureSafe();
        $now = now();

        if (!$this->alreadySeeded('contact_messages', 'email', '%@demo.test')) {
            foreach ([
                ['Liza Mendoza', 'liza.m@demo.test', 'Registration status', 'Good day, may I ask about the status of my registration? (sample)', 'new'],
                ['Ramon Torres', 'ramon.t@demo.test', 'Document upload', 'My NBI clearance upload keeps failing. (sample)', 'read'],
                ['Grace Navarro', 'grace.n@demo.test', 'Training schedule', 'When is the next oil spill response course? (sample)', 'archived'],
            ] as $i => [$name, $email, $subject, $message, $status]) {
                DB::table('contact_messages')->insert([
                    'name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message, 'status' => $status,
                    'ip' => '127.0.0.1', 'read_at' => $status === 'new' ? null : $now->copy()->subDays($i),
                    'created_at' => $now->copy()->subDays(3 - $i), 'updated_at' => $now,
                ]);
            }
        }

        if (!DB::table('admin_audit_logs')->where('subject', 'like', 'sample:%')->exists()) {
            $admins = DB::table('admin_users')->pluck('id', 'email');
            foreach ([
                ['admin@demo.test', 'login.success', 'sample:sign-in', 'POST', '/admin/two-factor/challenge'],
                ['hr@demo.test', 'POST admin/registrations', 'sample:registration', 'POST', '/admin/registrations'],
                ['approver@demo.test', 'POST admin/registrations/{registration}/decide', 'sample:decision', 'POST', '/admin/registrations/1/decide'],
                ['reenlistment@demo.test', 'reenlistment.csv', 'sample:export', 'GET', '/admin/reenlistment/export'],
                ['unknown@demo.test', 'login.failed', 'sample:failed sign-in', 'POST', '/admin/login'],
            ] as $i => [$email, $action, $subject, $method, $path]) {
                DB::table('admin_audit_logs')->insert([
                    'admin_user_id' => $admins[$email] ?? null, 'admin_email' => $email, 'action' => $action,
                    'method' => $method, 'subject' => $subject, 'status' => $action === 'login.failed' ? 401 : 200,
                    'ip' => '127.0.0.1', 'user_agent' => 'Sample browser', 'details' => json_encode(['path' => $path, 'sample' => true]),
                    'created_at' => $now->copy()->subHours(10 - $i),
                ]);
            }
        }

        $this->command?->line('  contact messages and audit entries ready');
    }
}
