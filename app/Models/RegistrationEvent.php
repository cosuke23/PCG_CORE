<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only history of a registration. */
class RegistrationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['registration_id', 'action', 'from_status', 'to_status', 'remarks', 'admin_user_id', 'admin_email'];

    protected $casts = ['created_at' => 'datetime'];
}
