<?php

namespace App\Http\Middleware;

use App\Models\UnitUser;
use Closure;
use Illuminate\Http\Request;

/**
 * M1 (all-units) pages: the all-units dashboard, 201 files, serial search
 * and disciplinary records. Requires a unit_users account with the
 * m1_checker role (granted on Admin -> Unit accounts). Previously any
 * unit checker could open them.
 */
class EnsureUnitM1
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->get('unit_role') !== UnitUser::ROLE_M1) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'M1 access required.'], 403);
            }

            return redirect('/unit/dashboard')
                ->with('error', 'That page is for M1 checkers (all units). Your account reviews your own unit only.');
        }

        return $next($request);
    }
}
