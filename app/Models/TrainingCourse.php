<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingCourse extends Model
{
    protected $fillable = [
        'course_name',
        'description',
        'status',
    ];

    public function students()
    {
        return $this->hasMany(CourseStudent::class, 'course_id');
    }
}