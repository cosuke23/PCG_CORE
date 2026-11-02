<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        // Only answer for our own address (APP_URL) and the local health checks
        // (deploy scripts call 127.0.0.1:<port>); any other Host header gets
        // a 400. Extra hosts: PCG_TRUSTED_HOSTS=host1,host2 (exact names).
        $extra = array_map(
            fn ($h) => '^' . preg_quote(trim($h)) . '$',
            array_filter(explode(',', (string) config('pcg.trusted_hosts', '')))
        );

        return array_merge([
            $this->allSubdomainsOfApplicationUrl(),
            '^127\.0\.0\.1$',
            '^localhost$',
        ], $extra);
    }
}
