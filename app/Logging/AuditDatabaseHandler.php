<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies every entry of the `audit` log channel (Log::channel('audit')) into
 * admin_audit_logs, so the admin console's Audit log shows what unit checkers,
 * members and scheduled jobs did, not only admins (owner request 2026-11-20).
 * The actor goes in admin_email: "unit: <email>", "member: <email>", the admin's
 * email, or "system". The file log (audit_file) stays as it was. Never throws.
 */
class AuditDatabaseHandler extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Info, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            if (!Schema::hasTable('admin_audit_logs')) {
                return;
            }
            [$actor, $adminId] = self::actor();
            $context = $record->context;
            $ip = $context['ip'] ?? (app()->runningInConsole() ? null : request()->ip());
            unset($context['ip']);
            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $adminId,
                'admin_email' => mb_substr($actor, 0, 255),
                'action' => mb_substr($record->message, 0, 100),
                'method' => app()->runningInConsole() ? 'CLI' : request()->method(),
                'subject' => self::subject($context),
                'status' => null,
                'ip' => $ip,
                'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 255),
                'details' => $context ? mb_substr(json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', 0, 2000) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // auditing must never break the action; the file log still has the entry
        }
    }

    /** @return array{0: string, 1: ?int} */
    private static function actor(): array
    {
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return ['system', null];
        }
        $session = request()->hasSession() ? request()->session() : null;
        if ($session && $session->get('admin_logged_in')) {
            $id = $session->get('admin_user_id');
            return [(string) $session->get('admin_email', 'admin'), $id !== null ? (int) $id : null];
        }
        if ($session && $session->get('unit_user_id')) {
            return ['unit: ' . (string) $session->get('unit_email', '#' . $session->get('unit_user_id')), null];
        }
        $user = auth()->user();
        if ($user) {
            return ['member: ' . (string) ($user->email ?? '#' . $user->id), null];
        }
        return [app()->runningInConsole() ? 'system' : 'guest', null];
    }

    /** A short subject from the usual context keys ("leave #12", "member #4"). */
    private static function subject(array $c): ?string
    {
        foreach (['leave_id' => 'leave', 'entry_id' => 'credit entry', 'member_user_id' => 'member', 'user_id' => 'member', 'unit_user_id' => 'unit checker'] as $k => $label) {
            if (isset($c[$k]) && is_scalar($c[$k])) {
                return $label . ' #' . $c[$k] . (isset($c['serial_number']) && is_scalar($c['serial_number']) ? ' (' . $c['serial_number'] . ')' : '');
            }
        }
        return isset($c['serial_number']) && is_scalar($c['serial_number']) ? 'serial ' . $c['serial_number'] : null;
    }
}
