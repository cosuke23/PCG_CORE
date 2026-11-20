<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Replace APP_KEY (e.g. after it was exposed) and re-encrypt what is stored with
 * it: admin_users.two_factor_secret and two_factor_recovery_codes (the model's
 * `encrypted` casts). The old key is not kept, so cookies and sessions made with
 * it stop working: everyone signs in again once. Uploaded files are not affected
 * (they use their own keys, pcg:file-keys). Dry run by default; --apply backs up
 * .env next to it (.env.bak-<time>, mode 600), re-encrypts in one transaction and
 * writes the new key. Keys are never printed. Run `php artisan config:cache`
 * afterwards when the configuration is cached.
 */
class AppKeyRotate extends Command
{
    protected $signature = 'pcg:app-key-rotate {--apply : Make the change (default: dry run)}';

    protected $description = 'Replace APP_KEY and re-encrypt the data stored with it';

    private const COLUMNS = ['two_factor_secret', 'two_factor_recovery_codes'];

    public function handle(): int
    {
        $cipher = (string) config('app.cipher', 'AES-256-CBC');
        $oldKey = (string) config('app.key');
        if ($oldKey === '') {
            $this->error('APP_KEY is not set.');
            return self::FAILURE;
        }
        $old = new Encrypter($this->raw($oldKey), $cipher);
        $newRaw = Encrypter::generateKey($cipher);
        $new = new Encrypter($newRaw, $cipher);
        $newKey = 'base64:' . base64_encode($newRaw);

        $rows = Schema::hasTable('admin_users')
            ? DB::table('admin_users')->where(fn ($q) => $q->whereNotNull('two_factor_secret')->orWhereNotNull('two_factor_recovery_codes'))
                ->get(array_merge(['id'], self::COLUMNS))
            : collect();
        $updates = [];
        $failed = 0;
        foreach ($rows as $r) {
            $set = [];
            foreach (self::COLUMNS as $c) {
                if ($r->$c === null || $r->$c === '') {
                    continue;
                }
                try {
                    $set[$c] = $new->encryptString($old->decryptString($r->$c));
                } catch (\Throwable $e) {
                    $failed++;
                }
            }
            if ($set) {
                $updates[$r->id] = $set;
            }
        }

        $envFile = $this->laravel->environmentFilePath();
        $real = realpath($envFile) ?: $envFile;
        $this->line(sprintf('Admin accounts with two-factor data: %d (values that could not be read with the current key: %d)', count($updates), $failed));
        $this->line('.env: ' . $real);
        if ($failed > 0) {
            $this->error('Some stored values do not open with the current key; nothing changed. Check them first.');
            return self::FAILURE;
        }
        if (!$this->option('apply')) {
            $this->info('Dry run: nothing changed. Run again with --apply.');
            return self::SUCCESS;
        }
        if (!is_file($real) || !is_writable($real)) {
            $this->error('.env is missing or not writable; nothing changed.');
            return self::FAILURE;
        }

        $env = File::get($real);
        $backup = $real . '.bak-' . now()->format('Ymd-His');
        File::copy($real, $backup);
        @chmod($backup, 0600);

        $lines = preg_split('/\r\n|\n/', $env);
        $out = [];
        $seen = false;
        foreach ($lines as $line) {
            if (preg_match('/^\s*APP_PREVIOUS_KEYS\s*=/', $line)) {
                continue;                                    // the old key must not stay accepted
            }
            if (preg_match('/^\s*APP_KEY\s*=/', $line)) {
                $out[] = 'APP_KEY=' . $newKey;
                $seen = true;
                continue;
            }
            $out[] = $line;
        }
        if (!$seen) {
            array_unshift($out, 'APP_KEY=' . $newKey);
        }
        $eol = str_contains($env, "\r\n") ? "\r\n" : "\n";

        DB::transaction(function () use ($updates, $real, $out, $eol) {
            foreach ($updates as $id => $set) {
                DB::table('admin_users')->where('id', $id)->update($set);
            }
            $tmp = $real . '.tmp-' . bin2hex(random_bytes(4));
            File::put($tmp, implode($eol, $out));
            @chmod($tmp, fileperms($real) & 0777);
            if (!@rename($tmp, $real)) {                     // same folder, so the swap is atomic
                @unlink($tmp);
                throw new \RuntimeException('Could not write .env.');
            }
        });

        $this->info(sprintf('New APP_KEY written; %d admin accounts re-encrypted. Backup: %s', count($updates), basename($backup)));
        $this->warn('Next: php artisan config:cache (if the configuration is cached). Everyone signs in again once.');
        return self::SUCCESS;
    }

    private function raw(string $key): string
    {
        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }
}
