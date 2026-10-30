<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

        // Credentials come from .env (see config/pcg.php). They were
        // previously hardcoded here.
        $adminEmail = config('pcg.admin.email');
        $adminHash  = config('pcg.admin.password_hash');

        if (!$adminEmail || !$adminHash) {
            Log::error('Admin login is not configured: set ADMIN_EMAIL and ADMIN_PASSWORD_HASH in .env');

            return back()
                ->withErrors(['email' => 'Admin login is not configured on this server.'])
                ->withInput($request->only('email'));
        }

        $emailMatches = hash_equals(strtolower($adminEmail), strtolower($request->email));

        if ($emailMatches && Hash::check($request->password, $adminHash)) {
            $request->session()->regenerate();

            session([
                'admin_logged_in' => true,
                'admin_email'     => $adminEmail,
            ]);

            return redirect()
                ->route('admin.dashboard')
                ->with('success', 'Admin login successful!');
        }

        Log::warning('Failed admin login', ['ip' => $request->ip()]);

        return back()
            ->withErrors(['email' => 'Invalid admin credentials'])
            ->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        session()->forget(['admin_logged_in', 'admin_email']);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Admin logged out successfully');
    }
}
