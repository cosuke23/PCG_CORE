<?php

namespace App\Http\Middleware;

use App\Support\AdminAudit;
use Closure;
use Illuminate\Http\Request;

/**
 * Records every state-changing admin request (POST/PUT/PATCH/DELETE) in
 * admin_audit_logs: route, route parameters, a few non-sensitive fields and
 * the response status. Never records passwords or file contents.
 */
class AuditAdminRequests
{
    private const SAFE_FIELDS = ['status', 'role', 'is_active', 'decision', 'serial_number', 'course_name', 'unit_name', 'email', 'name', 'full_name'];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$request->isMethodSafe()) {
            $route = $request->route();
            $params = $route ? collect($route->parameters())->map(fn ($v) => is_object($v) ? ($v->getKey() ?? (string) $v) : $v)->all() : [];
            $details = array_filter([
                'params' => $params ?: null,
                'fields' => array_intersect_key($request->all(), array_flip(self::SAFE_FIELDS)) ?: null,
                'files' => $request->allFiles() ? array_keys($request->allFiles()) : null,
            ]);

            AdminAudit::record(
                $route?->getName() ?? $request->path(),
                $request->path(),
                $details,
                $response->getStatusCode()
            );
        }

        return $response;
    }
}
