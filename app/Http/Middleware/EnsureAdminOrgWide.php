<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Pages that list personnel without organisational filtering (the original
 * MEPCOM admin pages) are for organisation-wide admins only. Admins limited
 * to a command or unit (admin_users.org_unit_id) use the scoped modules.
 */
class EnsureAdminOrgWide
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->get('admin_org_unit_id')) {
            abort(403, 'This page covers all personnel. Your account is limited to one command or unit.');
        }

        return $next($request);
    }
}
