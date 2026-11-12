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
        'must_change_password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    /**
     * Re-read the signed-in checker from the database on every request, so a
     * deactivated account, a removed M1 role or a changed unit takes effect
     * at once instead of when the session expires. Returns false (and clears
     * the unit session) when the account no longer exists or is inactive.
     */
    public static function refreshSession(\Illuminate\Contracts\Session\Session $session): bool
    {
        $id = $session->get('unit_user_id');
        if (!$id) {
            return false;
        }

        $user = static::query()->find($id);
        if (!$user || !$user->is_active) {
            $session->forget(['unit_user_id', 'unit_full_name', 'unit_email', 'unit_role', 'unit_name', 'unit_must_change_password']);
            return false;
        }

        $session->put([
            'unit_role' => $user->role,
            'unit_name' => $user->unit_name,
            'unit_full_name' => $user->full_name,
            'unit_must_change_password' => (bool) ($user->must_change_password ?? false),
        ]);

        return true;
    }

    /**
     * Join condition between users (u) and personnel_data (pd) matched by
     * serial number: the account that owns the record. Older rows have no
     * personnel_id; for those only an APPROVED account counts, so a pending
     * registration with someone else's serial number cannot pull that
     * person's documents into another unit.
     */
    public static function recordOwner($query)
    {
        return $query->whereColumn('pd.personnel_id', 'u.id')
            ->orWhere(fn ($q) => $q->whereNull('pd.personnel_id')->whereRaw('LOWER(TRIM(u.status)) = ?', ['approved']));
    }
}
