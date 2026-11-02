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
    /**
     * Does another account already use this serial number? Case and
     * surrounding spaces are ignored; rejected accounts (which cannot sign
     * in) do not count; $exceptId skips the account being checked.
     */
    public static function serialTaken(string $serial, ?int $exceptId = null): bool
    {
        $serial = strtoupper(trim($serial));
        if ($serial === '') {
            return false;
        }

        return static::query()
            ->whereRaw('UPPER(TRIM(serial_number)) = ?', [$serial])
            ->whereRaw("LOWER(COALESCE(status, '')) <> 'rejected'")
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    public function administrativeTracks()
    {
        return $this->hasMany(
            AdministrativeTrack::class,
            'rank',
            'rank'
        );
    }
}
