<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Encrypted storage and controlled delivery of uploaded files.
 *
 * Every upload goes through store()/storeBytes(): the file is encrypted with
 * AES-256-GCM (FileCrypto) before it touches the upload folder, so the
 * folder never holds a readable copy. Files are delivered only by
 * controllers that have already checked the viewer's rights, through
 * respond(), which decrypts on the fly and audits the view or download.
 *
 * $logicalPath is the file's stable name inside the system (for example
 * "documents_toupload/documents/123_x.pdf" or "personnel-documents/7/2026/ab.pdf").
 * It is bound into the encryption, so a file moved to another record's
 * path no longer decrypts.
 *
 * Files written before encryption was introduced are still served (after
 * the same checks) until `php artisan pcg:files encrypt --apply` converts them.
 */
class SecureFiles
{
    /** Content types that may be shown in the browser; anything else is downloaded. */
    private const INLINE = ['application/pdf', 'image/jpeg', 'image/png'];

    /**
     * Every folder that holds uploads: logical prefix => folder. A file's
     * logical path is "<prefix>/<path inside the folder>".
     *
     * @return array<string, string>
     */
    public static function areas(): array
    {
        $upload = rtrim((string) config('pcg.upload_root'), '/\\');

        return [
            'documents_toupload' => $upload . '/documents_toupload',
            '201_files' => $upload . '/201_files',
            'disciplinary_actions' => $upload . '/disciplinary_actions',
            'profile_photos' => $upload . '/profile_photos',
            'selfies' => rtrim((string) config('pcg.selfie_dir'), '/\\'),
            'personnel-documents' => rtrim((string) config('pcg.document_root'), '/\\'),
        ];
    }

    /**
     * Every stored file: [full path, logical path, area].
     *
     * @return \Generator<array{0: string, 1: string, 2: string}>
     */
    public static function walk(): \Generator
    {
        foreach (self::areas() as $area => $root) {
            if (!is_dir($root)) {
                continue;
            }
            $base = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isLink() || !$file->isFile() || str_starts_with($file->getFilename(), '.')) {
                    continue; // temp files of an interrupted write start with a dot
                }
                $full = str_replace('\\', '/', $file->getPathname());
                yield [$full, $area . '/' . substr($full, strlen($base) + 1), $area];
            }
        }
    }

    /** Encrypt an uploaded file into $directory/$fileName. Returns the full path. */
    public static function store(UploadedFile $file, string $directory, string $fileName, string $logicalPath): string
    {
        $dest = rtrim($directory, '/\\') . '/' . $fileName;
        FileCrypto::encryptFile($file->getRealPath(), $dest, $logicalPath);
        @unlink($file->getRealPath()); // the plaintext temp upload is not kept

        return $dest;
    }

    public static function storeBytes(string $bytes, string $fullPath, string $logicalPath): void
    {
        FileCrypto::encryptBytes($bytes, $fullPath, $logicalPath);
    }

    /** Plaintext of a stored file (encrypted or not yet converted). */
    public static function contents(string $fullPath, string $logicalPath): string
    {
        return FileCrypto::isEncrypted($fullPath)
            ? FileCrypto::decryptToString($fullPath, $logicalPath)
            : (string) file_get_contents($fullPath);
    }

    /**
     * Stream a file to a viewer who has ALREADY been authorised.
     * Audits the access as file.view or file.download.
     */
    public static function respond(string $fullPath, string $logicalPath, string $downloadName, bool $download = false, ?string $mime = null, array $audit = [])
    {
        $encrypted = FileCrypto::isEncrypted($fullPath);

        try {
            $head = $encrypted ? FileCrypto::peek($fullPath, $logicalPath) : (string) file_get_contents($fullPath, false, null, 0, 4096);
            $size = $encrypted ? FileCrypto::info($fullPath)['size'] : (int) filesize($fullPath);
        } catch (\Throwable $e) {
            Log::error('file.decrypt_failed', ['path' => $logicalPath, 'error' => $e->getMessage()]);
            abort(500, 'This file could not be opened. The administrator has been notified.');
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($head) ?: 'application/octet-stream';
        $type = $mime && $mime === $detected ? $mime : $detected;
        if (!in_array($type, self::INLINE, true)) {
            $download = true;
            $type = 'application/octet-stream';
        }

        $name = self::safeName($downloadName);
        $headers = [
            'Content-Type' => $type,
            'Content-Length' => (string) $size,
            'Content-Disposition' => HeaderUtils::makeDisposition($download ? 'attachment' : 'inline', $name, self::asciiName($name)),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ];

        self::audit($download ? 'file.download' : 'file.view', $logicalPath, $audit + ['encrypted' => $encrypted]);

        return new StreamedResponse(function () use ($fullPath, $logicalPath, $encrypted) {
            try {
                if ($encrypted) {
                    foreach (FileCrypto::decryptChunks($fullPath, $logicalPath) as $chunk) {
                        echo $chunk;
                        flush();
                    }
                } else {
                    readfile($fullPath);
                }
            } catch (\Throwable $e) {
                Log::error('file.stream_failed', ['path' => $logicalPath, 'error' => $e->getMessage()]);
            }
        }, 200, $headers);
    }

    /**
     * Who opened which file. Admins also go to admin_audit_logs (the Audit
     * log page); everyone goes to the audit log channel. No file content,
     * names of people or personal data are logged: only ids and the path.
     */
    public static function audit(string $action, string $logicalPath, array $context = []): void
    {
        $session = request()->hasSession() ? request()->session() : null;
        $who = [];
        if ($session?->get('admin_logged_in')) {
            $who = ['viewer' => 'admin', 'admin_user_id' => $session->get('admin_user_id'), 'role' => $session->get('admin_role')];
        } elseif ($session?->has('unit_user_id')) {
            $who = ['viewer' => 'unit', 'unit_user_id' => $session->get('unit_user_id'), 'unit_role' => $session->get('unit_role')];
        } elseif (auth()->check()) {
            $who = ['viewer' => 'personnel', 'user_id' => auth()->id()];
        }

        $entry = ['path' => $logicalPath, 'ip' => request()->ip()] + $who + $context;
        Log::channel('audit')->info($action, $entry);

        if (($who['viewer'] ?? null) === 'admin') {
            AdminAudit::record($action, $logicalPath, $context, 200);
        }
    }

    private static function safeName(string $name): string
    {
        $name = preg_replace('/[^\pL\pN ._()-]+/u', '_', basename(str_replace('\\', '/', $name)));

        return mb_substr(trim((string) $name, ' .') ?: 'file', 0, 150);
    }

    private static function asciiName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._()-]+/', '_', \Illuminate\Support\Str::ascii($name)) ?: 'file';
    }
}
