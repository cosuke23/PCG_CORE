<?php

namespace App\Http\Controllers;

use App\Models\UnitUser;
use App\Support\TemporaryPassword;
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
            ->select(array_merge(['id', 'full_name', 'email', 'username', 'role', 'unit_name', 'unit_code', 'is_active', 'password', 'created_at'],
                TemporaryPassword::flagReady('unit_users') ? ['must_change_password'] : []))
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

    /** Emails a new temporary password; the checker must change it at the next sign-in. */
    public function updatePassword(int $id)
    {
        // Every checker account opens restricted files (an own-unit checker sees
        // its unit's medical clearances, M1 every unit's restricted files), so
        // only an admin who may see restricted documents may set its password;
        // otherwise an HR admin could take over an account to read them.
        abort_unless(\App\Support\AdminPermissions::can('documents.restricted'), 403,
            'Only an admin who may see restricted documents can set a checker account password.');

        $account = DB::table('unit_users')->where('id', $id)->first(); // older tables lack some columns
        abort_unless($account, 404);
        if ($problem = TemporaryPassword::mailProblem()) {
            return back()->with('error', $problem);
        }
        if (!TemporaryPassword::flagReady('unit_users')) {
            return back()->with('error', 'Run the database update first (deploy with -Migrate).');
        }

        $password = TemporaryPassword::generate();
        try {
            DB::transaction(function () use ($account, $password) {
                DB::table('unit_users')->where('id', $account->id)->update(\App\Support\TableColumns::fit('unit_users', [
                    'password' => Hash::make($password),
                    'must_change_password' => true,
                    'updated_at' => now(),
                ]));
                TemporaryPassword::send($account->email, ($account->full_name ?? null) ?: $account->email, $password,
                    self::signInUrl($account->role ?? null), 'MEPCOM HRMIS Unit checker', true);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', 'The password was not changed. ' . $e->getMessage());
        }

        Log::channel('audit')->info('Unit account password reset by admin (emailed)', ['unit_user_id' => $id, 'admin_id' => session('admin_user_id')]);

        return back()->with('success', "A new temporary password was emailed to {$account->email}. They must choose their own password at their next sign-in.");
    }

    public function updateRole(Request $request, int $id)
    {
        $request->validate([
            'role' => 'required|in:' . UnitUser::ROLE_UNIT . ',' . UnitUser::ROLE_M1,
        ]);
        abort_if($request->role === UnitUser::ROLE_M1 && !\App\Support\AdminPermissions::can('documents.restricted'), 403,
            'Only the super admin can grant M1 (all units) access.');

        abort_unless(DB::table('unit_users')->where('id', $id)->exists(), 404);
        DB::table('unit_users')->where('id', $id)->update(\App\Support\TableColumns::fit('unit_users', [
            'role' => $request->role,
            'updated_at' => now(),
        ]));

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

        abort_unless(DB::table('unit_users')->where('id', $id)->exists(), 404);
        DB::table('unit_users')->where('id', $id)->update(\App\Support\TableColumns::fit('unit_users', [
            'is_active' => $request->boolean('is_active'),
            'updated_at' => now(),
        ]));

        return back()->with('success', $request->boolean('is_active') ? 'Account activated.' : 'Account deactivated. It can no longer sign in.');
    }

    /** The sign-in page a checker account of this role uses. */
    public static function signInUrl(?string $role): string
    {
        return url($role === UnitUser::ROLE_M1 ? '/unit/loginM1' : '/unit/login');
    }
}
