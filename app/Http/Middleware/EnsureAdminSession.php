<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Guards the MEPCOM admin area. Admin login (AdminLoginController) sets
 * the "admin_logged_in" session flag; it is not a Laravel auth guard.
 */
class EnsureAdminSession
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->get('admin_logged_in')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Please login as admin.']);
        }

        // Database accounts: re-read role, scope and status on every request so
        // a change by a super admin (or a deactivation) applies immediately.
        if ($id = $request->session()->get('admin_user_id')) {
            $admin = \App\Models\AdminUser::query()->whereKey($id)->first(['id', 'role', 'org_unit_id', 'is_active']);

            if (!$admin || !$admin->is_active) {
                $request->session()->forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_org_unit_id']);

                return redirect()->route('admin.login')->withErrors(['email' => 'Your admin account is no longer active.']);
            }

            $request->session()->put(['admin_role' => $admin->role, 'admin_org_unit_id' => $admin->org_unit_id]);
        }

        return $next($request);
    }
}
