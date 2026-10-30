<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Usage: ->middleware('admin.role:super_admin,hr_admin'). Runs after
 * admin.session; denies admins whose role is not listed.
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (!in_array($request->session()->get('admin_role'), $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your admin role does not allow this.'], 403);
            }

            abort(403, 'Your admin role does not allow this page.');
        }

        return $next($request);
    }
}
