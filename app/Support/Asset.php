<?php

namespace App\Support;

/**
 * URL of our own CSS/JS with a version taken from the file's modification
 * time. Hermes caches these files for 7 days (deploy/nginx), so without it
 * browsers keep yesterday's stylesheet after a deploy and pages render with
 * mismatched code (e.g. an overlay that should be hidden showing on load).
 */
class Asset
{
    public static function v(string $path): string
    {
        $file = public_path($path);
        $version = is_file($file) ? substr(md5((string) filemtime($file) . filesize($file)), 0, 10) : null;

        return asset($path) . ($version ? '?v=' . $version : '');
    }
}
