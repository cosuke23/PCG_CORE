<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Writes to admin_audit_logs. Never throws: auditing must not break the
 * action being audited (falls back to the application log).
 */
class AdminAudit
{
    public static function record(string $action, ?string $subject = null, array $details = [], ?int $status = null, ?string $email = null): void
    {
        $request = request();

        $row = [
            'admin_user_id' => session('admin_user_id'),
            'admin_email' => $email ?? session('admin_email'),
            'action' => substr($action, 0, 100),
            'method' => $request?->method(),
            'subject' => $subject !== null ? substr($subject, 0, 255) : null,
            'status' => $status,
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            'details' => $details ? json_encode($details) : null,
            'created_at' => now(),
        ];

        try {
            if (Schema::hasTable('admin_audit_logs')) {
                DB::table('admin_audit_logs')->insert($row);
                return;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        Log::info('admin audit', $row);
    }
}
