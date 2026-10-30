<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A file in a person's 201 record. Stored privately; versions are kept. */
class PersonnelDocument extends Model
{
    public const STATUSES = ['pending' => 'Pending verification', 'verified' => 'Verified', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    protected $hidden = ['stored_path'];

    protected $casts = [
        'is_current' => 'boolean',
        'issued_on' => 'date',
        'expires_on' => 'date',
        'verified_at' => 'datetime',
    ];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class);
    }

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast() && !$this->expires_on->isToday();
    }
}
