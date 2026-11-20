<?php

namespace App\Http\Controllers;

use App\Support\Monitor;

/**
 * Public liveness probe for uptime monitors: {"status":"ok"} with 200, or
 * {"status":"fail"} with 503. No details leave the server (see pcg:monitor).
 */
class HealthController extends Controller
{
    public function __invoke()
    {
        $ok = Monitor::healthy(Monitor::checks());
        return response()->json(['status' => $ok ? 'ok' : 'fail'], $ok ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
