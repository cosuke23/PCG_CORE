<?php

namespace App\Console\Commands;

use App\Support\FileCrypto;
use App\Support\FileKeys;
use App\Support\SecureFiles;
use Illuminate\Console\Command;

/**
 * Manage the master keys that protect uploaded files (docs/FILE_ENCRYPTION.md).
 *
 *   init     create the key ring (once per installation)
 *   status   show where the key ring is and which keys it holds (never the keys)
 *   rotate   add a new master key and make it active; then run `pcg:files rewrap --apply`
 *   retire   remove an old key once no file uses it any more
 *   backup   copy the key ring to --to=<path> for safe keeping (owner-only permissions)
 */
class FileKeysCommand extends Command
{
    protected $signature = 'pcg:file-keys
        {action=status : init | status | rotate | retire | backup}
        {id? : Key id (retire)}
        {--to= : Destination file (backup)}';

    protected $description = 'Manage the master keys for uploaded-file encryption';

    public function handle(): int
    {
        try {
            return match ($this->argument('action')) {
                'init' => $this->init(),
                'status' => $this->status(),
                'rotate' => $this->rotate(),
                'retire' => $this->retire((string) $this->argument('id')),
                'backup' => $this->backup((string) $this->option('to')),
                default => $this->fail2('Unknown action. Use init, status, rotate, retire or backup.'),
            };
        } catch (\Throwable $e) {
            return $this->fail2($e->getMessage());
        }
    }

    private function init(): int
    {
        if (config('pcg.file_key')) {
            return $this->fail2('PCG_FILE_KEY is set (tests only). Remove it to use a key ring file.');
        }
        $path = FileKeys::path();
        if (is_file($path)) {
            return $this->fail2("A key ring already exists at {$path}. Use `rotate` to add a key; never replace the file, or existing uploads become unreadable.");
        }
        foreach (SecureFiles::walk() as [$full]) {
            if (FileCrypto::isEncrypted($full)) {
                return $this->fail2('Encrypted files already exist, so the key ring was lost: restore it from the key backup (docs/FILE_ENCRYPTION.md). A new key could not open them.');
            }
        }
        $id = FileKeys::newId();
        FileKeys::write(['active' => $id, 'keys' => [$id => random_bytes(32)]]);
        $this->info(sprintf('Key ring created: %s (key %s, mode %04o).', $path, $id, FileKeys::mode()));
        $this->warn('Back it up NOW, separately from the upload backups: php artisan pcg:file-keys backup --to=<offline location>. Without it the files cannot be decrypted.');

        return self::SUCCESS;
    }

    private function status(): int
    {
        $ring = FileKeys::ring();
        $path = config('pcg.file_key') ? '(PCG_FILE_KEY environment value)' : FileKeys::path();
        $this->line("Key ring: {$path}");
        if (is_file($path) && DIRECTORY_SEPARATOR === '/') {
            $mode = substr(sprintf('%o', fileperms($path)), -4);
            $want = sprintf('%04o', FileKeys::mode());
            $mode === $want ? $this->line("Permissions: {$mode}") : $this->warn("Permissions: {$mode} (should be {$want}: chmod {$want} {$path})");
        }
        $this->table(['Key id', 'Active'], array_map(fn ($id) => [$id, $id === $ring['active'] ? 'yes' : ''], array_keys($ring['keys'])));

        return self::SUCCESS;
    }

    private function rotate(): int
    {
        if (config('pcg.file_key')) {
            return $this->fail2('PCG_FILE_KEY is set (tests only); rotation needs a key ring file.');
        }
        $ring = FileKeys::ring();
        $id = FileKeys::newId();
        $ring['keys'][$id] = random_bytes(32);
        $ring['active'] = $id;
        FileKeys::write($ring);
        $this->info("New active key: {$id}. Older keys stay in the ring so existing files still open.");
        $this->line('Next: back up the key ring, then run `php artisan pcg:files rewrap --apply` to move every file to the new key, then `pcg:file-keys retire <old id>`.');

        return self::SUCCESS;
    }

    private function retire(string $id): int
    {
        $ring = FileKeys::ring();
        if ($id === '' || !isset($ring['keys'][$id])) {
            return $this->fail2('Give the id of a key in the ring (see `pcg:file-keys status`).');
        }
        if ($id === $ring['active']) {
            return $this->fail2('The active key cannot be retired. Rotate first.');
        }
        $using = 0;
        foreach (SecureFiles::walk() as [$full]) {
            if (FileCrypto::isEncrypted($full) && FileCrypto::info($full)['kid'] === $id) {
                $using++;
            }
        }
        if ($using > 0) {
            return $this->fail2("{$using} file(s) still use key {$id}. Run `php artisan pcg:files rewrap --apply` first.");
        }
        unset($ring['keys'][$id]);
        FileKeys::write($ring);
        $this->info("Key {$id} removed. Keep it in the offline key backup until every backup that may contain files using it has expired.");

        return self::SUCCESS;
    }

    private function backup(string $to): int
    {
        if ($to === '') {
            return $this->fail2('Give a destination: --to=/path/outside/the/server/backups/file-keys.json');
        }
        FileKeys::assertSafeLocation($to);
        if (file_exists($to)) {
            return $this->fail2("{$to} already exists; choose a new file name (keep older backups).");
        }
        $old = umask(0077);
        try {
            if (!copy(FileKeys::path(), $to)) {
                return $this->fail2("Could not write {$to}.");
            }
            @chmod($to, 0600);
        } finally {
            umask($old);
        }
        $this->info("Key ring copied to {$to} (mode 0600). Move it to offline storage (e.g. an encrypted USB kept by two custodians), not next to the upload backups.");

        return self::SUCCESS;
    }

    private function fail2(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
