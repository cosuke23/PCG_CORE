<?php

namespace App\Support;

/**
 * Public URLs for files under config('pcg.upload_root').
 *
 * Production (cPanel) serves the project root, so uploaded files live at
 * https://pcg-mepcom.com/public/... and sets PCG_UPLOAD_URL accordingly.
 * Locally the web root is public/, so the default is asset('').
 */
class Uploads
{
    public static function baseUrl(): string
    {
        return rtrim(config('pcg.upload_url') ?: asset(''), '/') . '/';
    }

    public static function url(?string $relativePath): string
    {
        return self::baseUrl() . ltrim((string) $relativePath, '/');
    }
}
