<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Effective-dated placement of a person in an org unit (end_date NULL = current). */
class PersonnelAssignment extends Model
{
    protected $fillable = [
        'personnel_id', 'org_unit_id', 'position', 'start_date', 'end_date',
        'order_reference', 'remarks', 'created_by', 'ended_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function personnel()
    {
        return $this->belongsTo(Personnel::class);
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class);
    }
}
