<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\AdministrativeTrack;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rank',
        'serial_number',
        'name',
        'email',
        'password',
        'registration_type',
        'selfie_image',
        'date_of_birth',
        'date_of_appointment',
        'ete',
        'mother_unit',
        'sub_unit',
        'unit_assignment',
        'status',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    public function administrativeTracks()
    {
        return $this->hasMany(
            AdministrativeTrack::class,
            'rank',
            'rank'
        );
    }
}
