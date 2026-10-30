<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUser extends Model
{
    public const ROLE_SUPER = 'super_admin';
    public const ROLE_HR = 'hr_admin';
    public const ROLE_LMS = 'lms_admin';

    public const ROLES = [
        self::ROLE_SUPER => 'Super admin — everything, incl. admin accounts and audit log',
        self::ROLE_HR => 'HR admin — registrations, HR analytics, unit accounts, training, messages',
        self::ROLE_LMS => 'LMS admin — training tracks, applications, courses, students, OSAERT',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'org_unit_id', 'is_active'];

    /** Organisational scope (HR admins only). NULL = organisation-wide. */
    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $casts = [
        'is_active' => 'boolean',
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_confirmed_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && !empty($this->two_factor_secret);
    }

    /** Creates 8 one-time recovery codes; stores hashes, returns the plain codes once. */
    public function regenerateRecoveryCodes(): array
    {
        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5) . '-' . Str::random(5)))->all();
        $this->two_factor_recovery_codes = array_map(fn ($c) => Hash::make($c), $codes);

        return $codes;
    }

    public function useRecoveryCode(string $code): bool
    {
        $code = Str::upper(trim($code));
        $hashes = $this->two_factor_recovery_codes ?? [];

        foreach ($hashes as $i => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashes[$i]);
                $this->two_factor_recovery_codes = array_values($hashes);
                $this->save();
                return true;
            }
        }

        return false;
    }

    public function recoveryCodesLeft(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }
}
