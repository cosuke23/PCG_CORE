<?php

namespace App\Http\Middleware;

use App\Support\SoiLock;
use Closure;
use Illuminate\Http\Request;

/** Refuses SOI saves once the member has submitted the SOI (App\Support\SoiLock). */
class EnsureSoiOpen
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && SoiLock::locked(\Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->first())) {
            if ($request->expectsJson() || $request->isJson()) {
                return response()->json(['status' => 'locked', 'message' => SoiLock::MESSAGE], 423);
            }

            return redirect()->route('profile')->with('error', SoiLock::MESSAGE);
        }

        return $next($request);
    }
}
