<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A personnel registration moving through the approval workflow. */
class PersonnelRegistration extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'returned' => 'Returned for correction',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    protected $guarded = ['id', 'status', 'personnel_id', 'decided_by', 'decided_at', 'reviewed_by', 'reviewed_at', 'submitted_by', 'submitted_at'];

    protected $casts = [
        'date_of_birth' => 'date',
        'rank_date' => 'date',
        'date_entered_service' => 'date',
        'assignment_start_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'decided_at' => 'datetime',
        'confirmed_not_duplicate' => 'boolean',
    ];

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'returned'], true);
    }

    public function fullName(): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [$this->first_name, $this->middle_name, $this->last_name, $this->suffix])));
    }

    public function rank()
    {
        return $this->belongsTo(Rank::class);
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function personnel()
    {
        return $this->belongsTo(Personnel::class);
    }

    public function events()
    {
        return $this->hasMany(RegistrationEvent::class, 'registration_id')->orderBy('id');
    }
}
