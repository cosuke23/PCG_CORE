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

        return $next($request);
    }
}
