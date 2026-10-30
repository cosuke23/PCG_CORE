<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;

/** Usage: ->middleware('admin.can:personnel.edit'). Runs after admin.session. */
class EnsureAdminCan
{
    public function handle(Request $request, Closure $next, string $capability)
    {
        if (!AdminPermissions::allows($request->session()->get('admin_role'), $capability)) {
            abort(403, 'Your admin role does not allow this.');
        }

        return $next($request);
    }
}
