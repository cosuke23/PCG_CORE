<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    protected $fillable = ['code', 'name', 'is_restricted', 'tracks_expiry', 'is_active', 'sort_order'];

    protected $casts = ['is_restricted' => 'boolean', 'tracks_expiry' => 'boolean', 'is_active' => 'boolean'];
}
