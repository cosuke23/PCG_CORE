<?php

namespace App\Http\Middleware;

use App\Support\MemberDevices;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Member routes: keep the device list current and end sessions signed out from another device. */
class TrackMemberDevice
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && !MemberDevices::touch($request, (int) $user->id)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This device was signed out.'], 401);
            }
            return redirect('/login')->withErrors(['email' => 'This device was signed out from your account\'s Security page. Sign in again.']);
        }
        return $next($request);
    }
}
