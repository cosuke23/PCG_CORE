<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitUser extends Model
{
    use HasFactory;

    protected $table = 'unit_users';

    // Roles: own-unit checker, or M1 checker (all units, 201 files,
    // disciplinary records).
    public const ROLE_UNIT = 'unit_checker';
    public const ROLE_M1 = 'm1_checker';

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

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];
}
