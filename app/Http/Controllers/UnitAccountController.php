<?php

namespace App\Http\Controllers;

use App\Models\UnitUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Admin page for unit-checker accounts (unit_users): list, set password,
 * grant/remove M1 (all units) access, activate/deactivate. Creation is handled by UnitUserController::store
 * (POST /unit-users/store).
 */
class UnitAccountController extends Controller
{
    // Units offered by the original "Add Unit Account" form.
    public const UNITS = ['MEPCOM', 'MEPLEF', 'NOCOP', 'MSFIS', 'MEPRCGS', 'MEPTDI', 'MEPG'];

    public function index()
    {
        $accounts = DB::table('unit_users')
            ->select('id', 'full_name', 'email', 'username', 'role', 'unit_name', 'unit_code', 'is_active', 'password', 'created_at')
            ->orderBy('unit_name')
            ->orderBy('full_name')
            ->get()
            ->map(function ($a) {
                $a->has_password = !empty($a->password);
                unset($a->password);
                return $a;
            });

        return view('admin.unit-users.index', [
            'accounts' => $accounts,
            'units' => self::UNITS,
        ]);
    }

    public function updatePassword(Request $request, int $id)
    {
        $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $updated = DB::table('unit_users')->where('id', $id)->update([
            'password' => Hash::make($request->password),
            'updated_at' => now(),
        ]);

        abort_unless($updated, 404);

        Log::info('Unit account password set by admin', ['unit_user_id' => $id]);

        return back()->with('success', 'Password updated. Share it with the account holder securely.');
    }

    public function updateRole(Request $request, int $id)
    {
        $request->validate([
            'role' => 'required|in:' . UnitUser::ROLE_UNIT . ',' . UnitUser::ROLE_M1,
        ]);

        $updated = DB::table('unit_users')->where('id', $id)->update([
            'role' => $request->role,
            'updated_at' => now(),
        ]);

        abort_unless($updated, 404);

        Log::info('Unit account role changed by admin', ['unit_user_id' => $id, 'role' => $request->role]);

        return back()->with('success', $request->role === UnitUser::ROLE_M1
            ? 'M1 access granted: the account can review all units, 201 files and disciplinary records.'
            : 'M1 access removed: the account reviews its own unit only.');
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $updated = DB::table('unit_users')->where('id', $id)->update([
            'is_active' => $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        abort_unless($updated, 404);

        return back()->with('success', $request->boolean('is_active') ? 'Account activated.' : 'Account deactivated. It can no longer sign in.');
    }
}
