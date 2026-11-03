<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Named limiters: each keeps its own counter. The plain "throttle:5,1"
        // form keys only by IP, so all throttled routes shared one counter and
        // a few personnel logins from one office IP locked out the admin login.
        // Per account there is also an hourly cap counted across ALL addresses,
        // so a password cannot be guessed from many IPs (no permanent lockout:
        // the owner can sign in again after the hour).
        $login = fn (string $name, int $perEmail, int $perIp) => function (Request $request) use ($name, $perEmail, $perIp) {
            // ASCII-folded, so "ádmin@x" and "admin@x" (equal for the database's
            // accent-insensitive collation) share one counter
            $email = strtolower(trim(\Illuminate\Support\Str::ascii((string) $request->input('email'))));

            return [
                Limit::perMinute($perEmail)->by($name . '|' . $request->ip() . '|' . $email),
                Limit::perHour(30)->by($name . '-acct|' . $email),
                Limit::perMinute($perIp)->by($name . '-ip|' . $request->ip()),
            ];
        };

        RateLimiter::for('login', $login('login', 5, 30));
        RateLimiter::for('unit-login', $login('unit-login', 5, 30));
        RateLimiter::for('admin-login', $login('admin-login', 5, 20));
        // Sign-in codes: 5 a minute and 30 a day per admin, so a 6-digit code
        // cannot be ground down even when the password is known.
        RateLimiter::for('two-factor', fn (Request $request) => [
            Limit::perMinute(5)->by('2fa|' . $request->session()->get('admin_2fa_user_id', $request->ip())),
            Limit::perDay(30)->by('2fa-day|' . $request->session()->get('admin_2fa_user_id', $request->ip())),
        ]);
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(5)->by('register|' . $request->ip()),
            Limit::perHour(20)->by('register-h|' . $request->ip()),
        ]);
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by('contact|' . $request->ip()));
        RateLimiter::for('public-verify', fn (Request $request) => Limit::perMinute(60)->by('verify|' . $request->ip()));
    }
}
