<?php

namespace App\Support;

/**
 * URLs for uploaded personnel files.
 *
 * Files are served through FileController (/files/{path}), which checks
 * that the viewer may see them. $relativePath is the database value with
 * its area prefix, e.g. "documents_toupload/documents/123_soi.pdf",
 * "201_files/soi/123_x.pdf" or "selfies/abc.png".
 */
class Uploads
{
    public static function baseUrl(): string
    {
        return url('files') . '/';
    }

    public static function url(?string $relativePath): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim((string) $relativePath, '/')));

        return self::baseUrl() . implode('/', $segments);
    }
}
