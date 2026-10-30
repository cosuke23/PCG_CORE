<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Support\AdminAudit;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Admin sign-in: email + password, then a two-factor code (mandatory).
 *
 * Accounts live in admin_users (roles: super_admin, hr_admin, lms_admin).
 * The single .env admin (ADMIN_EMAIL / ADMIN_PASSWORD_HASH) only works while
 * no admin account exists yet, so the first super admin can be created.
 *
 * Session keys after a complete sign-in: admin_logged_in, admin_user_id,
 * admin_email, admin_name, admin_role.
 */
class AdminLoginController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (self::accountsEnabled()) {
            return $this->loginWithAccount($request);
        }

        return $this->loginWithBootstrapAdmin($request);
    }

    public static function accountsEnabled(): bool
    {
        try {
            return Schema::hasTable('admin_users') && AdminUser::query()->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function loginWithAccount(Request $request)
    {
        $admin = AdminUser::where('email', strtolower($request->email))->first();

        if (!$admin || !$admin->is_active || !Hash::check($request->password, $admin->password)) {
            AdminAudit::record('login.failed', $request->email, [], null, $request->email);
            Log::warning('Failed admin login', ['ip' => $request->ip()]);

            return back()
                ->withErrors(['email' => 'Invalid admin credentials'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();
        session(['admin_2fa_user_id' => $admin->id]);

        return redirect()->route($admin->hasTwoFactor() ? 'admin.two-factor.challenge' : 'admin.two-factor.setup');
    }

    private function loginWithBootstrapAdmin(Request $request)
    {
        $adminEmail = config('pcg.admin.email');
        $adminHash  = config('pcg.admin.password_hash');

        if (!$adminEmail || !$adminHash) {
            Log::error('Admin login is not configured: create an admin (php artisan pcg:create-admin) or set ADMIN_EMAIL and ADMIN_PASSWORD_HASH in .env');

            return back()
                ->withErrors(['email' => 'Admin login is not configured on this server.'])
                ->withInput($request->only('email'));
        }

        $emailMatches = hash_equals(strtolower($adminEmail), strtolower($request->email));

        if ($emailMatches && Hash::check($request->password, $adminHash)) {
            $request->session()->regenerate();

            session([
                'admin_logged_in' => true,
                'admin_user_id'   => null,
                'admin_email'     => $adminEmail,
                'admin_name'      => 'Setup administrator',
                'admin_role'      => AdminUser::ROLE_SUPER,
                'admin_bootstrap' => true,
            ]);

            AdminAudit::record('login.bootstrap', $adminEmail);

            return redirect()->route('admin.dashboard')
                ->with('warning', 'You are signed in with the setup account from .env. Create your own admin account (Admin accounts): this setup sign-in stops working as soon as one exists.');
        }

        AdminAudit::record('login.failed', $request->email, [], null, $request->email);
        Log::warning('Failed admin login', ['ip' => $request->ip()]);

        return back()
            ->withErrors(['email' => 'Invalid admin credentials'])
            ->withInput($request->only('email'));
    }

    // ---------------- two-factor ----------------

    private function pendingAdmin(): ?AdminUser
    {
        $id = session('admin_2fa_user_id');

        return $id ? AdminUser::where('id', $id)->where('is_active', true)->first() : null;
    }

    public function showSetup(Request $request)
    {
        $admin = $this->pendingAdmin();
        if (!$admin) {
            return redirect()->route('admin.login');
        }
        if ($admin->hasTwoFactor()) {
            return redirect()->route('admin.two-factor.challenge');
        }

        $secret = session('admin_2fa_secret') ?: Totp::generateSecret();
        session(['admin_2fa_secret' => $secret]);

        return view('admin.two-factor.setup', [
            'admin' => $admin,
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri' => Totp::uri($secret, $admin->email),
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $admin = $this->pendingAdmin();
        $secret = session('admin_2fa_secret');
        if (!$admin || !$secret) {
            return redirect()->route('admin.login');
        }

        $step = Totp::verify($secret, $request->code);
        if ($step === null) {
            return back()->withErrors(['code' => 'That code is not correct. Check the time on your phone and try the current code.']);
        }

        $admin->two_factor_secret = $secret;
        $admin->two_factor_confirmed_at = now();
        $admin->two_factor_last_step = $step;
        $codes = $admin->regenerateRecoveryCodes();
        $admin->save();

        session()->forget('admin_2fa_secret');
        AdminAudit::record('two_factor.enabled', $admin->email, [], null, $admin->email);

        $this->completeLogin($request, $admin);

        return redirect()->route('admin.two-factor.recovery-codes')->with('recovery_codes', $codes);
    }

    public function showChallenge()
    {
        $admin = $this->pendingAdmin();
        if (!$admin) {
            return redirect()->route('admin.login');
        }
        if (!$admin->hasTwoFactor()) {
            return redirect()->route('admin.two-factor.setup');
        }

        return view('admin.two-factor.challenge');
    }

    public function verifyChallenge(Request $request)
    {
        $request->validate(['code' => 'required|string|max:32']);

        $admin = $this->pendingAdmin();
        if (!$admin || !$admin->hasTwoFactor()) {
            return redirect()->route('admin.login');
        }

        $input = trim($request->code);
        $step = Totp::verify($admin->two_factor_secret, $input, $admin->two_factor_last_step);

        if ($step !== null) {
            $admin->two_factor_last_step = $step;
            $admin->save();
        } elseif (strlen(preg_replace('/\s+/', '', $input)) > 6 && $admin->useRecoveryCode($input)) {
            AdminAudit::record('two_factor.recovery_code_used', $admin->email, ['left' => $admin->recoveryCodesLeft()], null, $admin->email);
            session()->flash('warning', "You signed in with a recovery code. {$admin->recoveryCodesLeft()} left — ask a super admin to reset your two-factor sign-in if you lost your phone.");
        } else {
            AdminAudit::record('two_factor.failed', $admin->email, [], null, $admin->email);

            return back()->withErrors(['code' => 'That code is not correct.']);
        }

        $this->completeLogin($request, $admin);

        return redirect()->intended(route(self::homeRoute($admin->role)));
    }

    public function showRecoveryCodes()
    {
        $codes = session('recovery_codes');
        if (!$codes || !session('admin_logged_in')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.two-factor.recovery-codes', ['codes' => $codes]);
    }

    private function completeLogin(Request $request, AdminUser $admin): void
    {
        $request->session()->regenerate();
        session()->forget(['admin_2fa_user_id', 'admin_bootstrap']);

        session([
            'admin_logged_in' => true,
            'admin_user_id'   => $admin->id,
            'admin_email'     => $admin->email,
            'admin_name'      => $admin->name,
            'admin_role'      => $admin->role,
            'admin_org_unit_id' => $admin->org_unit_id,
        ]);

        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        AdminAudit::record('login.success');
    }

    public static function homeRoute(?string $role): string
    {
        if ($role === AdminUser::ROLE_LMS) {
            return 'admin.training-courses.index';
        }

        // command/unit-scoped HR admins work in the scoped personnel module
        return session('admin_org_unit_id') ? 'admin.hrmis.index' : 'admin.dashboard';
    }

    public function logout(Request $request)
    {
        AdminAudit::record('logout');
        session()->forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_org_unit_id', 'admin_bootstrap', 'admin_2fa_user_id']);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Admin logged out successfully');
    }
}
