<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Guards the unit checker area. Unit login (UnitUserController) stores
 * "unit_user_id" in the session; it is not a Laravel auth guard.
 */
class EnsureUnitSession
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('unit_user_id')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect('/unit/login');
        }

        return $next($request);
    }
}
