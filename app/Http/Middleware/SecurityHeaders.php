<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Security headers for every web response, including a
 * Content-Security-Policy with a per-request nonce.
 *
 * - Scripts: only from this site, or inline <script nonce="{{ $cspNonce }}">.
 *   Inline event handlers (onclick=...) are not allowed; use the
 *   data-on-click delegation in public/js/pcg-ui.js. 'unsafe-eval' is
 *   needed by Alpine.js (x-data expressions).
 * - Nothing loads from other origins except OpenStreetMap map tiles and
 *   geocoding on the document-upload page.
 * PCG_CSP=enforce (default) | report (Report-Only, for a cautious rollout) | off
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $nonce = base64_encode(random_bytes(18));
        View::share('cspNonce', $nonce);
        // per-request flags set by templates (see below); start each request clean
        app()->forgetInstance('csp.allow_eval');
        app()->forgetInstance('csp.allow_osm');

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Camera: registration selfie. Geolocation: residence map.
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        $mode = config('pcg.csp', 'enforce');
        if ($mode !== 'off') {
            $header = $mode === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            // 'unsafe-eval' and the OpenStreetMap hosts only where a template
            // asks for them (the Alpine.js layouts; the map page), decided by
            // the server-side template, never by the response text, which
            // injected markup could influence.
            $needsEval = app()->bound('csp.allow_eval') && app('csp.allow_eval') === true;
            $needsMap = app()->bound('csp.allow_osm') && app('csp.allow_osm') === true;
            $response->headers->set($header, $this->policy($nonce, $needsEval, $needsMap));
        }
        app()->forgetInstance('csp.allow_eval');
        app()->forgetInstance('csp.allow_osm');

        return $response;
    }

    private function policy(string $nonce, bool $allowEval = false, bool $allowMap = false): string
    {
        $eval = $allowEval ? " 'unsafe-eval'" : '';
        $tiles = $allowMap ? ' https://*.tile.openstreetmap.org' : '';
        $geo = $allowMap ? ' https://nominatim.openstreetmap.org' : '';

        // Every <script> carries the nonce. 'strict-dynamic' makes current
        // browsers ignore 'self', so an injected <script src="/any/our/file.js">
        // without the nonce does not run; 'self' stays only as a fallback for
        // browsers without CSP level 3.
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce' 'strict-dynamic'$eval",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:$tiles",
            "font-src 'self' data:",
            "connect-src 'self'$geo",
            "media-src 'self' blob:",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
