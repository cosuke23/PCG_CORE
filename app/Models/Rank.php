<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Rank reference (uniformed personnel only). */
class Rank extends Model
{
    public const CLASSIFICATIONS = [
        'officer' => 'Officer',
        'non_officer' => 'Non-officer',
    ];

    protected $fillable = ['code', 'name', 'classification', 'seniority', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static function ordered()
    {
        // officers first, most senior first
        return self::query()->orderByRaw("CASE WHEN classification = 'officer' THEN 0 ELSE 1 END")->orderByDesc('seniority');
    }
}
