<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Guards the unit checker area. Unit login (UnitUserController) stores
 * "unit_user_id" in the session; it is not a Laravel auth guard. The
 * account is re-checked on every request (active, role, unit).
 */
class EnsureUnitSession
{
    public function handle(Request $request, Closure $next)
    {
        if (!\App\Models\UnitUser::refreshSession($request->session())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect('/unit/login');
        }

        // Signed in with an emailed temporary password: nothing opens until the
        // checker has chosen their own (ChoosePasswordController).
        if ($request->session()->get('unit_must_change_password') && !$request->routeIs('unit.password.*')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Choose your own password first.'], 403);
            }

            return redirect()->route('unit.password.edit');
        }

        return $next($request);
    }
}
