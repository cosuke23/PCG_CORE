<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseStudent extends Model
{
    protected $fillable = [
        'course_id',
        'user_id',
        'serial_number',
        'full_name',
        'date_attended',
        'date_ended',
    ];

    protected $casts = [
        'date_attended' => 'date',
        'date_ended' => 'date',
    ];

    // Enrolled personnel account (users.serial_number).
    public function personnel()
    {
        return $this->belongsTo(User::class, 'serial_number', 'serial_number');
    }

    public function trainingCourse()
    {
        return $this->belongsTo(
            TrainingCourse::class,
            'course_id'
        );
    }
}