<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsaertTrainee extends Model
{
    protected $fillable = [
        'name',
        'company',
        'date_attended',
        'date_ended',
    ];

}