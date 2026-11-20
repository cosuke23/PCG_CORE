<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * The 3D BRP Gabriela Silang of the PCG HRMIS landing page (public/js/pcg-ship-3d.js),
 * sent gzip-compressed (about a quarter of the size) without needing gzip in the web
 * server's configuration. The compressed copy is made once per model version and
 * kept in storage/framework/cache; browsers cache the response for a week and
 * revalidate with the ETag. Clients that do not accept gzip get the plain file.
 */
class ShipModelController extends Controller
{
    public function show(Request $request)
    {
        $src = public_path('models/brp-gabriela-silang.glb');
        if (!is_file($src)) {
            abort(404);
        }
        $version = filemtime($src) . '-' . filesize($src);
        $etag = '"ship-' . sha1($version) . '"';
        $headers = [
            'Content-Type' => 'model/gltf-binary',
            'Cache-Control' => 'public, max-age=604800',
            'ETag' => $etag,
            'Vary' => 'Accept-Encoding',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        if (!str_contains(strtolower((string) $request->header('Accept-Encoding')), 'gzip')) {
            return response()->file($src, $headers);
        }

        $dir = storage_path('framework/cache/ship-model');
        $gz = $dir . '/brp-gabriela-silang-' . sha1($version) . '.glb.gz';
        if (!is_file($gz)) {
            File::ensureDirectoryExists($dir);
            $tmp = $gz . '.' . bin2hex(random_bytes(4));
            File::put($tmp, gzencode(File::get($src), 9));
            File::move($tmp, $gz);
            foreach (File::glob($dir . '/brp-gabriela-silang-*.glb.gz') as $old) {
                if ($old !== $gz) {
                    File::delete($old);
                }
            }
        }

        return response(File::get($gz), 200, $headers + ['Content-Encoding' => 'gzip', 'Content-Length' => (string) filesize($gz)]);
    }
}
