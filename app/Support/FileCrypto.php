<?php

namespace App\Support;

use RuntimeException;

/**
 * AES-256-GCM encryption of uploaded files (see docs/FILE_ENCRYPTION.md).
 *
 * Envelope encryption: every file gets its own random 256-bit data key,
 * stored in the file header wrapped (AES-256-GCM) by a master key from
 * FileKeys. The content is encrypted in 1 MiB chunks so it can be streamed;
 * each chunk has its own random nonce and authentication tag, and its
 * associated data binds it to this file, its position, whether it is the
 * last chunk, the plaintext size and the file's logical path. Reordering,
 * truncating, swapping files between records or editing any byte makes
 * decryption fail.
 *
 * Layout (integers big-endian):
 *   "PCGF" 0x01 | key-id length (1) | key id | file id (16) | plaintext size (8)
 *   | chunk size (4) | wrap nonce (12) | wrap tag (16) | wrapped data key (32)
 *   then per chunk: last flag (1) | nonce (12) | length (4) | ciphertext | tag (16)
 */
class FileCrypto
{
    public const MAGIC = "PCGF\x01";
    private const CIPHER = 'aes-256-gcm';
    private const CHUNK = 1048576;

    public static function isEncrypted(string $path): bool
    {
        $h = @fopen($path, 'rb');
        if (!$h) {
            return false;
        }
        $head = fread($h, 5);
        fclose($h);

        return $head === self::MAGIC;
    }

    /** Encrypt a plaintext file into $dest (written atomically, mode 0640). */
    public static function encryptFile(string $source, string $dest, string $logicalPath): void
    {
        $in = fopen($source, 'rb');
        if (!$in) {
            throw new RuntimeException('Cannot read the uploaded file.');
        }
        try {
            self::writeAtomic($dest, fn ($out) => self::encryptStream($in, $out, (int) filesize($source), $logicalPath));
        } finally {
            fclose($in);
        }
    }

    public static function encryptBytes(string $bytes, string $dest, string $logicalPath): void
    {
        $in = fopen('php://temp', 'w+b');
        fwrite($in, $bytes);
        rewind($in);
        try {
            self::writeAtomic($dest, fn ($out) => self::encryptStream($in, $out, strlen($bytes), $logicalPath));
        } finally {
            fclose($in);
        }
    }

    /** @param resource $in @param resource $out */
    public static function encryptStream($in, $out, int $size, string $logicalPath): void
    {
        $kid = FileKeys::activeId();
        $fileId = random_bytes(16);
        $dek = random_bytes(32);

        $wrapNonce = random_bytes(12);
        $wrapped = openssl_encrypt($dek, self::CIPHER, FileKeys::get($kid), OPENSSL_RAW_DATA, $wrapNonce, $wrapTag, self::wrapAad($kid, $fileId), 16);
        if ($wrapped === false) {
            throw new RuntimeException('Key wrapping failed.');
        }

        fwrite($out, self::MAGIC . chr(strlen($kid)) . $kid . $fileId . pack('J', $size) . pack('N', self::CHUNK) . $wrapNonce . $wrapTag . $wrapped);

        $index = 0;
        $done = 0;
        do {
            $plain = $size - $done > 0 ? (string) fread($in, min(self::CHUNK, $size - $done)) : '';
            $done += strlen($plain);
            $last = $done >= $size || feof($in);
            $nonce = random_bytes(12);
            $ct = openssl_encrypt($plain, self::CIPHER, $dek, OPENSSL_RAW_DATA, $nonce, $tag, self::chunkAad($fileId, $size, $index, $last, $logicalPath), 16);
            if ($ct === false) {
                throw new RuntimeException('Encryption failed.');
            }
            fwrite($out, chr($last ? 1 : 0) . $nonce . pack('N', strlen($ct)) . $ct . $tag);
            $index++;
        } while (!$last);

        if ($done !== $size) {
            throw new RuntimeException('The file changed size while it was being encrypted.');
        }
        self::wipe($dek);
    }

    /**
     * Authenticated plaintext, chunk by chunk. Each chunk is verified before
     * it is returned; a damaged or tampered file throws.
     *
     * @return \Generator<string>
     */
    public static function decryptChunks(string $path, string $logicalPath): \Generator
    {
        $h = fopen($path, 'rb');
        if (!$h) {
            throw new RuntimeException('Cannot open the encrypted file.');
        }
        try {
            $hdr = self::readHeader($h);
            $dek = self::unwrap($hdr);
            $index = 0;
            $total = 0;
            while (true) {
                $meta = self::readExact($h, 17);
                $last = ord($meta[0]) === 1;
                $nonce = substr($meta, 1, 12);
                $len = unpack('N', substr($meta, 13, 4))[1];
                if ($len > $hdr['chunk']) {
                    throw new RuntimeException('Encrypted file is damaged (chunk too large).');
                }
                $ct = $len ? self::readExact($h, $len) : '';
                $tag = self::readExact($h, 16);
                $plain = openssl_decrypt($ct, self::CIPHER, $dek, OPENSSL_RAW_DATA, $nonce, $tag, self::chunkAad($hdr['id'], $hdr['size'], $index, $last, $logicalPath));
                if ($plain === false) {
                    throw new RuntimeException('Encrypted file failed its integrity check (wrong path, wrong key or tampered).');
                }
                $total += strlen($plain);
                if ($plain !== '') {
                    yield $plain;
                }
                $index++;
                if ($last) {
                    break;
                }
            }
            if ($total !== $hdr['size'] || fread($h, 1) !== '') {
                throw new RuntimeException('Encrypted file is damaged (unexpected length).');
            }
        } finally {
            fclose($h);
        }
    }

    public static function decryptToString(string $path, string $logicalPath): string
    {
        $out = '';
        foreach (self::decryptChunks($path, $logicalPath) as $chunk) {
            $out .= $chunk;
        }

        return $out;
    }

    /** First bytes of the plaintext (for content-type detection). */
    public static function peek(string $path, string $logicalPath, int $bytes = 4096): string
    {
        foreach (self::decryptChunks($path, $logicalPath) as $chunk) {
            return substr($chunk, 0, $bytes);
        }

        return '';
    }

    /** @return array{kid: string, id: string, size: int} */
    public static function info(string $path): array
    {
        $h = fopen($path, 'rb');
        if (!$h) {
            throw new RuntimeException('Cannot open the encrypted file.');
        }
        try {
            $hdr = self::readHeader($h);
        } finally {
            fclose($h);
        }

        return ['kid' => $hdr['kid'], 'id' => bin2hex($hdr['id']), 'size' => $hdr['size']];
    }

    /**
     * Re-wrap the file's data key with the active master key. Only the header
     * changes; the encrypted content is copied as it is.
     * Returns false when the file already uses the active key.
     */
    public static function rewrap(string $path): bool
    {
        $active = FileKeys::activeId();
        $h = fopen($path, 'rb');
        if (!$h) {
            throw new RuntimeException('Cannot open the encrypted file.');
        }
        $tmp = null;
        try {
            $hdr = self::readHeader($h);
            if ($hdr['kid'] === $active) {
                return false;
            }
            $dek = self::unwrap($hdr);
            $wrapNonce = random_bytes(12);
            $wrapped = openssl_encrypt($dek, self::CIPHER, FileKeys::get($active), OPENSSL_RAW_DATA, $wrapNonce, $wrapTag, self::wrapAad($active, $hdr['id']), 16);
            self::wipe($dek);
            if ($wrapped === false) {
                throw new RuntimeException('Key wrapping failed.');
            }
            $tmp = dirname($path) . '/.' . basename($path) . '.' . bin2hex(random_bytes(6)) . '.part';
            $out = fopen($tmp, 'xb');
            if (!$out) {
                throw new RuntimeException('Cannot write next to ' . $path);
            }
            fwrite($out, self::MAGIC . chr(strlen($active)) . $active . $hdr['id'] . pack('J', $hdr['size']) . pack('N', $hdr['chunk']) . $wrapNonce . $wrapTag . $wrapped);
            stream_copy_to_stream($h, $out);
            fclose($out);
        } catch (\Throwable $e) {
            if ($tmp) {
                @unlink($tmp);
            }
            throw $e;
        } finally {
            fclose($h);
        }
        // the source handle is closed before the rename (Windows refuses to replace an open file)
        @chmod($tmp, 0640);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Cannot replace {$path}.");
        }

        return true;
    }

    // ---------------------------------------------------------------- internals

    private static function wipe(string &$secret): void
    {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($secret);
        } else {
            $secret = str_repeat("\0", strlen($secret));
        }
    }

    private static function readHeader($h): array
    {
        if (fread($h, 5) !== self::MAGIC) {
            throw new RuntimeException('Not an encrypted file.');
        }
        $kidLen = ord(self::readExact($h, 1));
        $kid = self::readExact($h, $kidLen);
        $id = self::readExact($h, 16);
        $size = unpack('J', self::readExact($h, 8))[1];
        $chunk = unpack('N', self::readExact($h, 4))[1];
        $wrapNonce = self::readExact($h, 12);
        $wrapTag = self::readExact($h, 16);
        $wrapped = self::readExact($h, 32);

        return compact('kid', 'id', 'size', 'chunk', 'wrapNonce', 'wrapTag', 'wrapped');
    }

    private static function unwrap(array $hdr): string
    {
        $dek = openssl_decrypt($hdr['wrapped'], self::CIPHER, FileKeys::get($hdr['kid']), OPENSSL_RAW_DATA, $hdr['wrapNonce'], $hdr['wrapTag'], self::wrapAad($hdr['kid'], $hdr['id']));
        if ($dek === false || strlen($dek) !== 32) {
            throw new RuntimeException('The file key could not be unwrapped (wrong master key or damaged header).');
        }

        return $dek;
    }

    private static function wrapAad(string $kid, string $fileId): string
    {
        return "PCGF1|wrap|{$kid}|" . $fileId;
    }

    private static function chunkAad(string $fileId, int $size, int $index, bool $last, string $logicalPath): string
    {
        return 'PCGF1|' . $fileId . pack('J', $size) . pack('N', $index) . ($last ? "\x01" : "\x00") . '|' . self::normalizePath($logicalPath);
    }

    public static function normalizePath(string $logicalPath): string
    {
        return ltrim(str_replace('\\', '/', $logicalPath), '/');
    }

    private static function readExact($h, int $len): string
    {
        $buf = '';
        while (strlen($buf) < $len) {
            $part = fread($h, $len - strlen($buf));
            if ($part === false || $part === '') {
                throw new RuntimeException('Encrypted file is damaged (truncated).');
            }
            $buf .= $part;
        }

        return $buf;
    }

    /** Write via a temporary file in the same folder, then rename over $dest. */
    private static function writeAtomic(string $dest, callable $writer): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create the folder {$dir}.");
        }
        $tmp = $dir . '/.' . basename($dest) . '.' . bin2hex(random_bytes(6)) . '.part';
        $out = fopen($tmp, 'xb');
        if (!$out) {
            throw new RuntimeException("Cannot write in {$dir}.");
        }
        try {
            $writer($out);
            fflush($out);
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($tmp);
            throw $e;
        }
        fclose($out);
        @chmod($tmp, 0640);
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new RuntimeException("Cannot move the encrypted file into place ({$dest}).");
        }
    }
}
