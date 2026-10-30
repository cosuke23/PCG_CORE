<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingApplication extends Model
{
    protected $fillable = [
        'serial_number',
        'training_type',
        'track_id',
        'course_training',
        'rank',
        'division_unit',
        'year_in_grade',
        'provider',
        'remarks',
        'status',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];
}