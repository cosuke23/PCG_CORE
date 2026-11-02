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

        // Absolute limit: an admin session ends 12 hours after sign-in even if it
        // is kept busy (idle sessions already end after SESSION_LIFETIME).
        $since = (int) $request->session()->get('admin_since', 0);
        if ($since === 0) {
            $request->session()->put('admin_since', time());
        } elseif (time() - $since > (int) config('pcg.admin.max_session_hours', 12) * 3600) {
            $request->session()->forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_org_unit_id', 'admin_bootstrap', 'admin_since']);
            $request->session()->migrate(true);

            return redirect()->route('admin.login')->withErrors(['email' => 'Your admin session has ended. Please sign in again.']);
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
        } elseif ($request->session()->get('admin_bootstrap') && \App\Http\Controllers\AdminLoginController::accountsEnabled()) {
            // The .env setup admin (no 2FA) only works until the first real
            // account exists; end any such session that is still open.
            $request->session()->forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_org_unit_id', 'admin_bootstrap']);

            return redirect()->route('admin.login')->withErrors(['email' => 'Admin accounts now exist. Sign in with your own account.']);
        }

        return $next($request);
    }
}
