<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitUser extends Model
{
    use HasFactory;

    protected $table = 'unit_users';

    protected $fillable = [
        'user_id',
        'personnel_id',
        'serial_number',
        'full_name',
        'email',
        'role',
        'unit_name',
        'unit_code',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];
}
