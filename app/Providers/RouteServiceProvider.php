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
        $login = fn (string $name, int $perEmail, int $perIp) => function (Request $request) use ($name, $perEmail, $perIp) {
            return [
                Limit::perMinute($perEmail)->by($name . '|' . $request->ip() . '|' . strtolower((string) $request->input('email'))),
                Limit::perMinute($perIp)->by($name . '-ip|' . $request->ip()),
            ];
        };

        RateLimiter::for('login', $login('login', 5, 30));
        RateLimiter::for('unit-login', $login('unit-login', 5, 30));
        RateLimiter::for('admin-login', $login('admin-login', 5, 20));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by('2fa|' . $request->session()->get('admin_2fa_user_id', $request->ip())));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by('register|' . $request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by('contact|' . $request->ip()));
        RateLimiter::for('public-verify', fn (Request $request) => Limit::perMinute(60)->by('verify|' . $request->ip()));
    }
}
