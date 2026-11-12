<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\TemporaryPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Super admins manage administrator accounts and read the audit log.
 * Every change here is also recorded by the admin.audit middleware.
 */
class AdminAccountController extends Controller
{
    public function index()
    {
        return view('admin.accounts.index', [
            'admins' => AdminUser::with('orgUnit')->orderBy('name')->get(),
            'roles' => AdminUser::ROLES,
            'units' => \App\Models\OrgUnit::flatTree()->where('is_active', true)->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:admin_users,email',
            'role' => ['required', Rule::in(array_keys(AdminUser::ROLES))],
            'org_unit_id' => $this->scopeRule(),
        ]);
        if ($problem = TemporaryPassword::mailProblem()) {
            return back()->withInput()->with('error', $problem);
        }
        if (!TemporaryPassword::flagReady('admin_users')) {
            return back()->withInput()->with('error', 'Run the database update first (deploy with -Migrate): accounts need the "must change password" setting.');
        }
        if ($error = $this->scopeError($data['role'], $data['org_unit_id'] ?? null)) {
            return back()->withInput()->withErrors(['org_unit_id' => $error]);
        }

        $wasBootstrap = !AdminLoginController::accountsEnabled();

        // Nobody types the password: it is generated, emailed to the account
        // holder and must be changed at the first sign-in. If the email cannot
        // be sent, the account is not created (the transaction rolls back).
        $password = TemporaryPassword::generate();
        try {
            DB::transaction(function () use ($data, $password) {
                $admin = AdminUser::create([
                    'name' => $data['name'],
                    'email' => strtolower($data['email']),
                    'password' => Hash::make($password),
                    'role' => $data['role'],
                    'org_unit_id' => $data['org_unit_id'] ?? null,
                    'is_active' => true,
                    'must_change_password' => true,
                ]);
                TemporaryPassword::send($admin->email, $admin->name, $password, self::signInUrl($admin->role), 'PCG HRMIS Administration');
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', 'The account was not created. ' . $e->getMessage());
        }

        $message = "Admin account created. A temporary password was emailed to {$data['email']}; they must choose their own password when they first sign in.";
        if ($wasBootstrap) {
            $message .= ' The setup account from .env no longer works — sign in with the new account.';
        }

        return back()->with('success', $message);
    }

    public function updateRole(Request $request, AdminUser $admin)
    {
        $request->validate([
            'role' => ['required', Rule::in(array_keys(AdminUser::ROLES))],
            'org_unit_id' => $this->scopeRule(),
        ]);
        $scope = $request->input('org_unit_id') ?: null;
        if ($error = $this->scopeError($request->role, $scope)) {
            return back()->with('error', $error);
        }

        if ($admin->role === AdminUser::ROLE_SUPER && $request->role !== AdminUser::ROLE_SUPER && $this->activeSupers() <= 1) {
            return back()->with('error', 'There must be at least one active super admin.');
        }

        $admin->update(['role' => $request->role, 'org_unit_id' => $scope]);

        return back()->with('success', "Access of {$admin->email} changed. It applies from their next page load.");
    }

    public function updateStatus(Request $request, AdminUser $admin)
    {
        $request->validate(['is_active' => 'required|boolean']);
        $active = $request->boolean('is_active');

        if (!$active && $admin->id === session('admin_user_id')) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        if (!$active && $admin->role === AdminUser::ROLE_SUPER && $this->activeSupers() <= 1) {
            return back()->with('error', 'There must be at least one active super admin.');
        }

        $admin->update(['is_active' => $active]);

        return back()->with('success', $active ? "{$admin->email} activated." : "{$admin->email} deactivated.");
    }

    /** Emails a new temporary password; the account must change it at its next sign-in. */
    public function resetPassword(AdminUser $admin)
    {
        if ($problem = TemporaryPassword::mailProblem()) {
            return back()->with('error', $problem);
        }
        if (!TemporaryPassword::flagReady('admin_users')) {
            return back()->with('error', 'Run the database update first (deploy with -Migrate).');
        }
        $password = TemporaryPassword::generate();
        try {
            DB::transaction(function () use ($admin, $password) {
                $admin->update(['password' => Hash::make($password), 'must_change_password' => true]);
                TemporaryPassword::send($admin->email, $admin->name, $password, self::signInUrl($admin->role), 'PCG HRMIS Administration', true);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', 'The password was not changed. ' . $e->getMessage());
        }

        return back()->with('success', "A new temporary password was emailed to {$admin->email}. They must choose their own password at their next sign-in.");
    }

    /** The sign-in page an account of this role uses. */
    public static function signInUrl(string $role): string
    {
        return match ($role) {
            AdminUser::ROLE_ADJUTANT => route('adjutant.login'),
            AdminUser::ROLE_REENLISTMENT => route('reenlistment.login'),
            default => route('admin.login'),
        };
    }

    public function resetTwoFactor(AdminUser $admin)
    {
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return back()->with('success', "Two-factor sign-in of {$admin->email} reset. They will set it up again at their next sign-in.");
    }

    public function auditLog(Request $request)
    {
        $query = DB::table('admin_audit_logs')->orderByDesc('id');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', $action . '%');
        }
        if ($who = $request->query('admin')) {
            $query->where('admin_email', $who);
        }

        return view('admin.accounts.audit', [
            'entries' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only('action', 'admin'),
        ]);
    }

    private function scopeRule(): array
    {
        return ['nullable', 'integer', Rule::exists('org_units', 'id')->where('is_active', true)];
    }

    /** Only HR admins can be limited to a command/unit; super and LMS admins are organisation-wide. */
    private function scopeError(string $role, $unitId): ?string
    {
        return $unitId && !in_array($role, \App\Support\AdminPermissions::scopableRoles(), true)
            ? 'Super admins and LMS admins are organisation-wide and cannot be limited to a command or unit.'
            : null;
    }

    private function activeSupers(): int
    {
        return AdminUser::where('role', AdminUser::ROLE_SUPER)->where('is_active', true)->count();
    }
}
