<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Copies personnel uploads out of the web root into private storage
 * (owner decision D7, 2026-10-31).
 *
 * Dry run by default. With --apply it COPIES files (checksums verified) and
 * never deletes or overwrites anything: a file that already exists at the
 * target with different content is reported as a conflict and left alone.
 * Afterwards set PCG_UPLOAD_ROOT / PCG_SELFIE_DIR as printed, check the app,
 * and only then remove the public copies by hand (keep a backup).
 */
class MoveUploads extends Command
{
    protected $signature = 'pcg:move-uploads
        {--target= : Private folder (default: storage/app/private/uploads)}
        {--apply : Copy the files (without it nothing is changed)}';

    protected $description = 'Copy uploaded personnel files out of public/ into private storage (dry run unless --apply)';

    private const AREAS = ['documents_toupload', '201_files', 'disciplinary_actions', 'selfies'];

    public function handle()
    {
        $apply = (bool) $this->option('apply');
        $target = rtrim($this->option('target') ?: storage_path('app/private/uploads'), '/\\');
        $public = realpath(public_path()) ?: public_path();

        if (str_starts_with(realpath(dirname($target)) ?: $target, $public)) {
            $this->error('The target must be outside the public folder.');

            return self::FAILURE;
        }

        $sources = [
            'documents_toupload' => config('pcg.upload_root') . '/documents_toupload',
            '201_files' => config('pcg.upload_root') . '/201_files',
            'disciplinary_actions' => config('pcg.upload_root') . '/disciplinary_actions',
            'selfies' => config('pcg.selfie_dir'),
        ];

        $this->info($apply ? "APPLY: copying into {$target}" : "DRY RUN into {$target}: nothing will be changed. Add --apply to copy.");

        $rows = [];
        $totals = ['copy' => 0, 'same' => 0, 'conflict' => 0, 'failed' => 0];
        $conflicts = [];

        foreach (self::AREAS as $area) {
            $src = $sources[$area];
            $dst = $target . DIRECTORY_SEPARATOR . $area;
            $counts = ['files' => 0, 'bytes' => 0, 'copy' => 0, 'same' => 0, 'conflict' => 0, 'failed' => 0];

            if (is_dir($src) && realpath($src) !== realpath($dst)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if ($file->isLink() || !$file->isFile()) {
                        continue;
                    }
                    $relative = substr($file->getPathname(), strlen($src) + 1);
                    $to = $dst . DIRECTORY_SEPARATOR . $relative;
                    $counts['files']++;
                    $counts['bytes'] += $file->getSize();

                    if (is_file($to)) {
                        if (hash_file('sha256', $to) === hash_file('sha256', $file->getPathname())) {
                            $counts['same']++;
                        } else {
                            $counts['conflict']++;
                            $conflicts[] = "{$area}/{$relative}";
                        }
                        continue;
                    }

                    if (!$apply) {
                        $counts['copy']++;
                        continue;
                    }

                    $dir = dirname($to);
                    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                        $counts['failed']++;
                        continue;
                    }
                    if (copy($file->getPathname(), $to) && hash_file('sha256', $to) === hash_file('sha256', $file->getPathname())) {
                        @touch($to, $file->getMTime());
                        $counts['copy']++;
                    } else {
                        @unlink($to); // only the partial copy we just wrote
                        $counts['failed']++;
                    }
                }
            }

            foreach (['copy', 'same', 'conflict', 'failed'] as $k) {
                $totals[$k] += $counts[$k];
            }
            $rows[] = [$area, is_dir($src) ? $counts['files'] : 'no folder', round($counts['bytes'] / 1048576, 1) . ' MB',
                $counts['copy'], $counts['same'], $counts['conflict'], $counts['failed']];
        }

        $this->table(['Folder', 'Files', 'Size', $apply ? 'Copied' : 'To copy', 'Already there', 'Conflicts', 'Failed'], $rows);

        foreach (array_slice($conflicts, 0, 20) as $c) {
            $this->warn("  conflict (left alone): {$c}");
        }

        if ($apply && $totals['failed'] === 0 && $totals['conflict'] === 0) {
            $this->info('All files copied and verified. Originals were NOT removed. Next:');
            $this->line("  1. In .env set:  PCG_UPLOAD_ROOT=\"{$target}\"  and  PCG_SELFIE_DIR=\"{$target}" . DIRECTORY_SEPARATOR . 'selfies"');
            $this->line('  2. php artisan config:clear && php artisan pcg:check');
            $this->line('  3. Open a few documents as admin, unit checker and personnel.');
            $this->line('  4. Only then, with a backup, delete the folders under public/ by hand.');
        } elseif ($apply) {
            $this->error('Some files were not copied (see Conflicts/Failed). Do not switch PCG_UPLOAD_ROOT yet.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
