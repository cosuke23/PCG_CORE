<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\UnitUser;
use App\Support\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * First sign-in with an emailed temporary password (must_change_password):
 * the account chooses its own password before it can open anything else.
 * EnsureAdminSession / EnsureUnitSession send it here until it has.
 */
class ChoosePasswordController extends Controller
{
    private function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ];
    }

    // ---------------- administrators ----------------

    public function adminEdit()
    {
        $admin = AdminUser::find(session('admin_user_id'));
        if (!$admin || !$admin->must_change_password) {
            return redirect()->route(AdminLoginController::homeRoute(session('admin_role')));
        }

        return view('auth.choose-password', [
            'action' => route('admin.password.update'),
            'eyebrow' => 'PCG HRMIS · Administration',
            'email' => $admin->email,
            'logout' => route('admin.logout'),
        ]);
    }

    public function adminUpdate(Request $request)
    {
        $admin = AdminUser::find(session('admin_user_id'));
        if (!$admin || !$admin->must_change_password) {
            return redirect()->route(AdminLoginController::homeRoute(session('admin_role')));
        }
        $request->validate($this->rules());
        if (Hash::check($request->password, $admin->password)) {
            return back()->withErrors(['password' => 'Choose a password different from the temporary one.']);
        }

        $admin->forceFill(['password' => Hash::make($request->password), 'must_change_password' => false])->save();
        AdminAudit::record('password.changed_first_sign_in', $admin->email);

        return redirect()->route(AdminLoginController::homeRoute($admin->role))
            ->with('success', 'Your password is set. Use it from now on.');
    }

    // ---------------- unit checkers ----------------

    public function unitEdit()
    {
        $user = UnitUser::find(session('unit_user_id'));
        if (!$user || !$user->must_change_password) {
            return redirect(self::unitHome());
        }

        return view('auth.choose-password', [
            'action' => route('unit.password.update'),
            'eyebrow' => 'MEPCOM HRMIS · Unit checker',
            'email' => $user->email,
            'logout' => route('unit.logout'),
        ]);
    }

    public function unitUpdate(Request $request)
    {
        $user = UnitUser::find(session('unit_user_id'));
        if (!$user || !$user->must_change_password) {
            return redirect(self::unitHome());
        }
        $request->validate($this->rules());
        $current = \Illuminate\Support\Facades\DB::table('unit_users')->where('id', $user->id)->value('password');
        if ($current && Hash::check($request->password, $current)) {
            return back()->withErrors(['password' => 'Choose a password different from the temporary one.']);
        }

        \Illuminate\Support\Facades\DB::table('unit_users')->where('id', $user->id)->update(\App\Support\TableColumns::fit('unit_users', [
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'updated_at' => now(),
        ]));
        Log::channel('audit')->info('Unit checker chose a password at first sign-in', ['unit_user_id' => $user->id]);

        return redirect(self::unitHome())->with('success', 'Your password is set. Use it from now on.');
    }

    private static function unitHome(): string
    {
        return session('unit_role') === UnitUser::ROLE_M1 ? '/unit/dashboardM1' : '/unit/dashboard';
    }
}
