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

        if ($message = self::revalidate($request->session())) {
            return redirect()->route('admin.login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }

    /**
     * Re-check a signed-in admin session against the database and the time
     * limit. Returns null when it is still valid, or the reason it ended (the
     * admin flags are then removed). Used by this middleware and by routes
     * outside the admin group that grant admin rights, such as /files.
     */
    public static function revalidate(\Illuminate\Contracts\Session\Session $session): ?string
    {
        // Absolute limit: an admin session ends 12 hours after sign-in even if it
        // is kept busy (idle sessions already end after SESSION_LIFETIME).
        $since = (int) $session->get('admin_since', 0);
        if ($since === 0) {
            $session->put('admin_since', time());
        } elseif (time() - $since > (int) config('pcg.admin.max_session_hours', 12) * 3600) {
            $session->forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_org_unit_id', 'admin_bootstrap', 'admin_since']);
            $session->migrate(true);
            return 'Your admin session has ended. Please sign in again.';
        }

        // Database accounts: re-read role, scope and status on every request so
        // a change by a super admin (or a deactivation) applies immediately.
        if ($id = $session->get('admin_user_id')) {
            $admin = \App\Models\AdminUser::query()->whereKey($id)->first(['id', 'role', 'org_unit_id', 'is_active']);

            if (!$admin || !$admin->is_active) {
                $session->forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_org_unit_id']);
                return 'Your admin account is no longer active.';
            }

            // Signed in without the second step, but the role now needs it
            // (e.g. promoted from re-enlistment admin): sign in again.
            if ($session->get('admin_password_only') && !\App\Http\Controllers\AdminLoginController::passwordOnly($admin->role)) {
                $session->forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_org_unit_id', 'admin_since', 'admin_password_only']);
                $session->migrate(true);
                return 'Your role has changed. Please sign in again.';
            }

            $session->put(['admin_role' => $admin->role, 'admin_org_unit_id' => $admin->org_unit_id]);
        } elseif ($session->get('admin_bootstrap') && \App\Http\Controllers\AdminLoginController::accountsEnabled()) {
            // The .env setup admin (no 2FA) only works until the first real
            // account exists; end any such session that is still open.
            $session->forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_org_unit_id', 'admin_bootstrap']);
            return 'Admin accounts now exist. Sign in with your own account.';
        }

        return null;
    }
}
