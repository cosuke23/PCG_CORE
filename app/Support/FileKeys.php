<?php

namespace App\Support;

use RuntimeException;

/**
 * The key ring for uploaded-file encryption (see docs/FILE_ENCRYPTION.md).
 *
 * Master keys (key-encryption keys, 32 random bytes each) live in one JSON
 * file OUTSIDE every upload folder and outside public/:
 *
 *   { "active": "k20261105a", "keys": { "k20261105a": "base64:..." } }
 *
 * Each uploaded file has its own random data key, encrypted ("wrapped")
 * with the active master key; the file header records which master key
 * wrapped it. Rotating adds a new master key and re-wraps the data keys,
 * so the files themselves never need re-encrypting.
 *
 * Path: PCG_FILE_KEYS_PATH (production: /srv/mepcom-hrmis/shared/keys/
 * file-keys.json, mode 0600, or 0640 when only the app's group may read it). Tests may use PCG_FILE_KEY (one base64 key,
 * id "env") instead of a file. A missing key ring is created automatically
 * only in local and testing environments; elsewhere it is an error, because
 * a new key cannot read files written with the old one.
 */
class FileKeys
{
    private static ?array $ring = null;

    public static function path(): string
    {
        return (string) (config('pcg.file_keys_path') ?: storage_path('app/keys/file-keys.json'));
    }

    /** Forget the loaded key ring (tests, and commands after they change it). */
    public static function flush(): void
    {
        self::$ring = null;
    }

    public static function activeId(): string
    {
        return self::ring()['active'];
    }

    /** Raw 32-byte master key for an id; throws when the id is unknown. */
    public static function get(string $id): string
    {
        $keys = self::ring()['keys'];
        if (!isset($keys[$id])) {
            throw new RuntimeException("File encryption key \"{$id}\" is not in the key ring. Restore it from the key backup (docs/FILE_ENCRYPTION.md).");
        }

        return $keys[$id];
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::ring()['keys']);
    }

    public static function exists(): bool
    {
        return self::envKey() !== null || is_file(self::path());
    }

    /** @return array{active: string, keys: array<string, string>} */
    public static function ring(): array
    {
        if (self::$ring !== null) {
            return self::$ring;
        }

        if (($env = self::envKey()) !== null) {
            return self::$ring = ['active' => 'env', 'keys' => ['env' => $env]];
        }

        $path = self::path();
        self::assertSafeLocation($path);

        if (!is_file($path)) {
            if (!app()->environment(['local', 'testing'])) {
                throw new RuntimeException('File encryption key ring not found at ' . $path . '. Run: php artisan pcg:file-keys init (or restore it from the key backup).');
            }
            self::write(['active' => $id = self::newId(), 'keys' => [$id => random_bytes(32)]]);
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || !isset($json['active'], $json['keys']) || !is_array($json['keys'])) {
            throw new RuntimeException("File encryption key ring {$path} is not valid JSON.");
        }

        $keys = [];
        foreach ($json['keys'] as $id => $value) {
            $raw = base64_decode(preg_replace('/^base64:/', '', (string) $value), true);
            if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) $id) || $raw === false || strlen($raw) !== 32) {
                throw new RuntimeException("File encryption key \"{$id}\" in {$path} is not a 32-byte base64 key.");
            }
            $keys[(string) $id] = $raw;
        }
        if (!isset($keys[$json['active']])) {
            throw new RuntimeException("The active file encryption key \"{$json['active']}\" is missing from {$path}.");
        }

        return self::$ring = ['active' => (string) $json['active'], 'keys' => $keys];
    }

    /** Persist a key ring (raw keys) with owner-only permissions. */
    public static function write(array $ring): void
    {
        $path = self::path();
        self::assertSafeLocation($path);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create the key folder {$dir}.");
        }

        $json = json_encode([
            'active' => $ring['active'],
            'keys' => array_map(fn ($k) => 'base64:' . base64_encode($k), $ring['keys']),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $old = umask(0077);
        try {
            if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
                throw new RuntimeException("Cannot write {$tmp}.");
            }
            @chmod($tmp, self::mode());
            if (!rename($tmp, $path)) {
                @unlink($tmp);
                throw new RuntimeException("Cannot replace {$path}.");
            }
        } finally {
            umask($old);
        }
        self::flush();
    }

    /** 0600, or 0640 when the web app reads the ring through its group (PCG_FILE_KEYS_MODE). */
    public static function mode(): int
    {
        return (string) config('pcg.file_keys_mode') === '0640' ? 0640 : 0600;
    }

    public static function newId(): string
    {
        return 'k' . now()->format('Ymd') . bin2hex(random_bytes(2));
    }

    /**
     * Keys must never sit with the files they protect, nor anywhere the web
     * server publishes.
     */
    public static function assertSafeLocation(string $path): void
    {
        $dir = self::norm(dirname($path));
        $forbidden = array_filter([
            public_path(),
            config('pcg.upload_root'),
            config('pcg.selfie_dir'),
            config('pcg.document_root'),
        ]);
        foreach ($forbidden as $root) {
            $root = self::norm((string) $root);
            if ($dir === $root || str_starts_with($dir . '/', $root . '/')) {
                throw new RuntimeException("The file encryption key ring ({$path}) must not be inside {$root}: keep keys away from the uploaded files and the web root.");
            }
        }
    }

    private static function norm(string $p): string
    {
        $real = realpath($p);

        return rtrim(str_replace('\\', '/', $real ?: $p), '/');
    }

    private static function envKey(): ?string
    {
        $value = (string) config('pcg.file_key');
        if ($value === '') {
            return null;
        }
        $raw = base64_decode(preg_replace('/^base64:/', '', $value), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('PCG_FILE_KEY must be a base64-encoded 32-byte key.');
        }

        return $raw;
    }
}
