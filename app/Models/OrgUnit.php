<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A node of the PCG organisational hierarchy (org_units).
 * Units are never deleted: deactivate them so history stays intact.
 */
class OrgUnit extends Model
{
    public const TYPES = [
        'headquarters' => 'Headquarters',
        'command' => 'Command',
        'office' => 'Office / division / department',
        'unit' => 'Unit',
        'section' => 'Sub-unit / section',
    ];

    // Command groups as shown on the PCG organisational chart.
    public const CATEGORIES = [
        'functional' => 'Functional command',
        'admin_support' => 'Administrative support command',
        'operational_support' => 'Operational support command',
        'operating' => 'Operating command (district)',
        'special_service' => 'Special service command',
        'technical_service' => 'Technical service command',
    ];

    protected $fillable = [
        'parent_id', 'code', 'name', 'type', 'category', 'description',
        'is_active', 'effective_from', 'effective_to', 'sort_order', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Ids of this unit and everything below it. Loads the (small) table once
     * instead of one query per level.
     */
    public function subtreeIds(): array
    {
        $byParent = self::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [];
        $queue = [$this->id];

        while ($queue) {
            $id = array_shift($queue);
            if (in_array($id, $ids, true)) {
                continue; // defensive: never loop on bad data
            }
            $ids[] = $id;
            foreach ($byParent->get($id, collect()) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    /** Whole hierarchy as nested arrays of models, roots first. */
    public static function tree(): Collection
    {
        $all = self::query()->orderBy('sort_order')->orderBy('name')->get();
        $byParent = $all->groupBy(fn ($u) => $u->parent_id ?? 0);

        $build = function ($parentId, $depth) use (&$build, $byParent) {
            return $byParent->get($parentId, collect())->map(function ($unit) use ($build, $depth) {
                $unit->setRelation('children', $build($unit->id, $depth + 1));
                $unit->depth = $depth;

                return $unit;
            })->values();
        };

        return $build(0, 0);
    }

    /** Flat list in tree order with depth, for <select> options. */
    public static function flatTree(): Collection
    {
        $out = collect();
        $walk = function ($nodes) use (&$walk, $out) {
            foreach ($nodes as $node) {
                $out->push($node);
                $walk($node->children);
            }
        };
        $walk(self::tree());

        return $out;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
