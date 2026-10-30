<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdministrativeTrack extends Model
{
    use HasFactory;

    protected $table = 'administrative_tracks';

    protected $fillable = [
        'no',
        'division_unit',
        'rank',
        'year_in_grade',
        'course_training',
        'provider',
        'status',
        'remarks',
    ];
}