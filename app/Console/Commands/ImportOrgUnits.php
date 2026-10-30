<?php

namespace App\Console\Commands;

use App\Models\OrgUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports a reviewed unit tree (JSON) under an existing org unit.
 *
 * Dry run by default. With --apply it only INSERTS units whose code does not
 * exist yet; existing units are never changed, moved or deleted (they are
 * reused as parents for their children and reported if they sit elsewhere).
 *
 * File format: {"parent": "MEPCOM", "units": [{"code", "name", "type",
 * "description"?, "children": [...]}, ...]}
 */
class ImportOrgUnits extends Command
{
    protected $signature = 'pcg:import-org-units {file : JSON file, e.g. database/data/mepcom_units.json} {--apply : Create the units (without it nothing is changed)}';

    protected $description = 'Import a reviewed unit tree under an existing command (dry run unless --apply)';

    private array $stats = ['create' => 0, 'exists' => 0, 'moved' => 0];
    private array $lines = [];

    public function handle()
    {
        $path = $this->argument('file');
        $path = is_file($path) ? $path : base_path($path);
        $data = is_file($path) ? json_decode(file_get_contents($path), true) : null;
        if (!is_array($data) || empty($data['parent']) || !is_array($data['units'] ?? null)) {
            $this->error('Not a unit file: expected {"parent": "...", "units": [...]}.');

            return self::FAILURE;
        }

        $parent = OrgUnit::whereRaw('LOWER(code) = ?', [mb_strtolower($data['parent'])])->first();
        if (!$parent || !$parent->is_active) {
            $this->error("Parent unit {$data['parent']} not found or inactive. Run php artisan migrate first.");

            return self::FAILURE;
        }

        if ($errors = $this->validateTree($data['units'])) {
            foreach ($errors as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? "APPLY: importing under {$parent->code}." : "DRY RUN under {$parent->code}: nothing will be changed. Add --apply to import.");

        DB::transaction(function () use ($data, $parent, $apply) {
            $this->walk($data['units'], $parent, $apply, 0);
        });

        foreach (array_slice($this->lines, 0, 400) as $line) {
            $this->line($line);
        }
        $this->table(['New', 'Already present', 'Present under another parent (left as is)'], [[$this->stats['create'], $this->stats['exists'], $this->stats['moved']]]);
        if ($apply) {
            $this->info("Created {$this->stats['create']} units. Review them under Admin -> Organization.");
        }

        return self::SUCCESS;
    }

    private function walk(array $nodes, OrgUnit $parent, bool $apply, int $depth): void
    {
        foreach ($nodes as $i => $node) {
            $existing = OrgUnit::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($node['code']))])->first();
            $pad = str_repeat('  ', $depth);

            if ($existing) {
                if ((int) $existing->parent_id !== (int) $parent->id) {
                    $this->stats['moved']++;
                    $this->lines[] = "{$pad}= {$existing->code} (exists under another parent; not moved)";
                } else {
                    $this->stats['exists']++;
                    $this->lines[] = "{$pad}= {$existing->code} (exists)";
                }
                $unit = $existing;
            } else {
                $this->stats['create']++;
                $this->lines[] = "{$pad}+ {$node['code']} [{$node['type']}] {$node['name']}";
                $unit = $apply
                    ? OrgUnit::create([
                        'parent_id' => $parent->id, 'code' => trim($node['code']), 'name' => $node['name'], 'type' => $node['type'],
                        'description' => $node['description'] ?? null, 'sort_order' => $i + 1, 'is_active' => true,
                    ])
                    : new OrgUnit(['code' => $node['code']]); // placeholder so children are listed
            }

            if (!empty($node['children'])) {
                if (!$unit->exists) {
                    $unit->id = -1; // dry run: children of a not-yet-created unit
                }
                $this->walk($node['children'], $unit, $apply, $depth + 1);
            }
        }
    }

    private function validateTree(array $nodes, array &$seen = []): array
    {
        $errors = [];
        foreach ($nodes as $node) {
            $code = trim((string) ($node['code'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._\/&()-]*$/', $code) || strlen($code) > 50) {
                $errors[] = "Invalid code: '{$code}'";
            }
            if (isset($seen[mb_strtolower($code)])) {
                $errors[] = "Duplicate code in file: {$code}";
            }
            $seen[mb_strtolower($code)] = true;
            if (!isset(OrgUnit::TYPES[$node['type'] ?? ''])) {
                $errors[] = "Invalid type for {$code}: " . ($node['type'] ?? '(none)');
            }
            if (trim((string) ($node['name'] ?? '')) === '' || mb_strlen($node['name']) > 255) {
                $errors[] = "Invalid name for {$code}";
            }
            $errors = array_merge($errors, $this->validateTree($node['children'] ?? [], $seen));
        }

        return $errors;
    }
}
