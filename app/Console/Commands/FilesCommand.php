<?php

namespace App\Console\Commands;

use App\Support\FileCrypto;
use App\Support\FileKeys;
use App\Support\SecureFiles;
use Illuminate\Console\Command;

/**
 * Uploaded-file encryption maintenance (docs/FILE_ENCRYPTION.md).
 *
 *   status    count encrypted / not yet encrypted files per folder and key
 *   encrypt   encrypt files written before encryption existed (dry run unless --apply);
 *             also moves old profile photos off the public disk
 *   verify    decrypt every file to prove the keys and files are intact (after a restore)
 *   rewrap    move every file to the active master key after `pcg:file-keys rotate`
 *
 * encrypt and rewrap replace files in place; back up the upload folders and
 * the key ring first.
 */
class FilesCommand extends Command
{
    protected $signature = 'pcg:files
        {action=status : status | encrypt | verify | rewrap}
        {--apply : Make the changes (encrypt, rewrap); without it nothing is changed}';

    protected $description = 'Uploaded-file encryption: status, encrypt existing files, verify, rewrap';

    public function handle(): int
    {
        FileKeys::ring(); // fail early, with a clear message, when the keys are missing

        return match ($this->argument('action')) {
            'status' => $this->status(),
            'encrypt' => $this->encrypt((bool) $this->option('apply')),
            'verify' => $this->verify(),
            'rewrap' => $this->rewrap((bool) $this->option('apply')),
            default => tap(self::FAILURE, fn () => $this->error('Unknown action. Use status, encrypt, verify or rewrap.')),
        };
    }

    private function status(): int
    {
        $rows = [];
        foreach (SecureFiles::walk() as [$full, , $area]) {
            $rows[$area] ??= ['encrypted' => 0, 'plain' => 0, 'keys' => []];
            if (FileCrypto::isEncrypted($full)) {
                $rows[$area]['encrypted']++;
                $kid = FileCrypto::info($full)['kid'];
                $rows[$area]['keys'][$kid] = ($rows[$area]['keys'][$kid] ?? 0) + 1;
            } else {
                $rows[$area]['plain']++;
            }
        }
        $legacy = $this->legacyPhotos();
        $this->table(['Folder', 'Encrypted', 'Not encrypted', 'By key'], array_map(
            fn ($area, $r) => [$area, $r['encrypted'], $r['plain'], implode(', ', array_map(fn ($k, $n) => "$k: $n", array_keys($r['keys']), $r['keys']))],
            array_keys($rows), $rows
        ));
        if ($legacy) {
            $this->warn(count($legacy) . ' profile photo(s) still on the public disk (storage/app/public/profile_photos): run `pcg:files encrypt --apply`.');
        }
        $plain = array_sum(array_column($rows, 'plain')) + count($legacy);
        $plain ? $this->warn("{$plain} file(s) are not encrypted yet.") : $this->info('Every stored file is encrypted.');

        return self::SUCCESS;
    }

    private function encrypt(bool $apply): int
    {
        $this->info($apply ? 'APPLY: encrypting files in place.' : 'DRY RUN: nothing will be changed. Back up the upload folders and the key ring, then add --apply.');
        $done = $failed = 0;

        $jobs = [];
        foreach (SecureFiles::walk() as [$full, $logical]) {
            if (!FileCrypto::isEncrypted($full)) {
                $jobs[] = [$full, $full, $logical];
            }
        }
        $photoDir = SecureFiles::areas()['profile_photos'];
        foreach ($this->legacyPhotos() as $name => $full) {
            $jobs[] = [$full, $photoDir . '/' . $name, 'profile_photos/' . $name];
        }

        foreach ($jobs as [$source, $dest, $logical]) {
            if (!$apply) {
                $this->line("  would encrypt {$logical}" . ($source !== $dest ? ' (and move it off the public disk)' : ''));
                $done++;
                continue;
            }
            try {
                $this->encryptOne($source, $dest, $logical);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  {$logical}: {$e->getMessage()}");
            }
        }

        $this->info(($apply ? 'Encrypted' : 'To encrypt') . ": {$done}" . ($failed ? ", failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Encrypt to a temporary file, prove it decrypts to the same bytes, then replace. */
    private function encryptOne(string $source, string $dest, string $logical): void
    {
        $sha = hash_file('sha256', $source);
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("cannot create {$dir}");
        }
        $tmp = $dir . '/.' . basename($dest) . '.' . bin2hex(random_bytes(6)) . '.enc';
        FileCrypto::encryptFile($source, $tmp, $logical);

        $ctx = hash_init('sha256');
        foreach (FileCrypto::decryptChunks($tmp, $logical) as $chunk) {
            hash_update($ctx, $chunk);
        }
        if (!hash_equals($sha, hash_final($ctx))) {
            @unlink($tmp);
            throw new \RuntimeException('verification failed; original left unchanged');
        }
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new \RuntimeException('could not replace the file; original left unchanged');
        }
        if ($source !== $dest) {
            @unlink($source); // the public, readable copy
        }
    }

    private function verify(): int
    {
        $ok = $bad = $plain = 0;
        foreach (SecureFiles::walk() as [$full, $logical]) {
            if (!FileCrypto::isEncrypted($full)) {
                $plain++;
                continue;
            }
            try {
                foreach (FileCrypto::decryptChunks($full, $logical) as $chunk) {
                    // read to the end: every chunk tag is checked
                }
                $ok++;
            } catch (\Throwable $e) {
                $bad++;
                $this->error("  {$logical}: {$e->getMessage()}");
            }
        }
        $this->info("Verified: {$ok}" . ($bad ? ", FAILED: {$bad}" : '') . ($plain ? ", not encrypted: {$plain}" : ''));

        return $bad ? self::FAILURE : self::SUCCESS;
    }

    private function rewrap(bool $apply): int
    {
        $active = FileKeys::activeId();
        $this->info($apply ? "APPLY: re-wrapping files to key {$active}." : "DRY RUN: files that would move to key {$active}. Add --apply.");
        $moved = $failed = 0;
        foreach (SecureFiles::walk() as [$full, $logical]) {
            if (!FileCrypto::isEncrypted($full) || FileCrypto::info($full)['kid'] === $active) {
                continue;
            }
            if (!$apply) {
                $moved++;
                continue;
            }
            try {
                FileCrypto::rewrap($full) && $moved++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  {$logical}: {$e->getMessage()}");
            }
        }
        $this->info(($apply ? 'Re-wrapped' : 'To re-wrap') . ": {$moved}" . ($failed ? ", failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, string> file name => full path */
    private function legacyPhotos(): array
    {
        $dir = (string) config('pcg.legacy_photo_dir');
        $out = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $name) {
            if ($name[0] !== '.' && is_file("$dir/$name") && !is_link("$dir/$name")) {
                $out[$name] = "$dir/$name";
            }
        }

        return $out;
    }
}
