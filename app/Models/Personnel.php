<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Personnel master record: one permanent record per person, identified by
 * the serial number. Organisational placement lives in assignments.
 */
class Personnel extends Model
{
    protected $table = 'personnel';

    public const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    protected $fillable = [
        'serial_number', 'last_name', 'first_name', 'middle_name', 'suffix', 'full_name',
        'date_of_birth', 'email', 'contact_number', 'classification', 'rank_id', 'rank_date',
        'date_entered_service', 'service_status', 'user_id', 'source', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'rank_date' => 'date',
        'date_entered_service' => 'date',
    ];

    /** Serial numbers are compared trimmed, single-spaced and upper-case. */
    public static function normalizeSerial(?string $serial): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $serial)));
    }

    public function rank()
    {
        return $this->belongsTo(Rank::class);
    }

    public function assignments()
    {
        return $this->hasMany(PersonnelAssignment::class)->orderByDesc('start_date')->orderByDesc('id');
    }

    public function currentAssignment()
    {
        return $this->hasOne(PersonnelAssignment::class)->whereNull('end_date')->latestOfMany('start_date');
    }

    public function documents()
    {
        return $this->hasMany(PersonnelDocument::class)->orderByDesc('is_current')->orderByDesc('id');
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class)->orderByDesc('effective_date')->orderByDesc('id');
    }

    public function trainings()
    {
        return $this->hasMany(PersonnelTraining::class)->orderByDesc('end_date')->orderByDesc('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
