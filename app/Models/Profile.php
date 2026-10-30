<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'district',
        'first_name',
        'middle_name',
        'last_name',
        'extension_name',
        'birthday',
        'sex',
        'photo',
        'perm_street',
        'perm_city',
        'perm_province',
        'perm_zipcode',
        'perm_years_residency',
        'pres_street',
        'pres_city',
        'pres_province',
        'pres_zipcode',
        'pres_years_residency',
        'education_level',
        'tertiary_course',
        'tertiary_school',
        'tertiary_year_graduated',
        'secondary_school',
        'secondary_strand',
        'secondary_year_graduated',
        'elementary_school',
        'elementary_year_graduated',
        'profile_code',
    ];

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';
}
