<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Completed course or qualification of a person. */
class PersonnelTraining extends Model
{
    public const KINDS = ['course' => 'Course / training', 'qualification' => 'Qualification'];

    protected $fillable = ['personnel_id', 'title', 'kind', 'provider', 'start_date', 'end_date', 'certificate_number', 'valid_until', 'remarks', 'created_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'valid_until' => 'date'];
}
