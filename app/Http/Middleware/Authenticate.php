<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Sign-in checks the account status only once (LoginController). Check it
     * on every request too: a member who is rejected, or whose serial number
     * is given to another approved account, loses access at once instead of
     * keeping a session that writes to that serial's records.
     */
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);

        $guard = $this->auth->guard($guards[0] ?? null);
        $user = $guard->user();
        if ($user instanceof \App\Models\User
            && array_key_exists('status', $user->getAttributes())
            && strtolower(trim((string) $user->status)) !== 'approved') {
            if (method_exists($guard, 'logout')) {
                $guard->logout();
            }
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            throw new AuthenticationException('Your account is no longer active.', $guards, $this->redirectTo($request));
        }
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
