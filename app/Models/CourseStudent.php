<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseStudent extends Model
{
    protected $fillable = [
        'course_id',
        'serial_number',
        'full_name',
        'date_attended',
        'date_ended',
    ];

    protected $casts = [
        'date_attended' => 'date',
        'date_ended' => 'date',
    ];

    public function trainingCourse()
    {
        return $this->belongsTo(
            TrainingCourse::class,
            'course_id'
        );
    }
}