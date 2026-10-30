<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm()
    {
        return view('pages.login');
    }

    /**
     * Handle user login and create session
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {

            $user = Auth::user();

            // Check if approved
            // Only approved accounts may log in. Statuses are stored with mixed
            // case ("Approved", "Pending", "Rejected"); compare case-insensitively
            // so that no unexpected value falls through to a successful login.
            $status = strtolower(trim((string) $user->status));

            if ($status !== 'approved') {

                Auth::logout();

                $message = match ($status) {
                    'pending'  => 'Your account is waiting for admin approval.',
                    'rejected' => 'Your account has been rejected by admin.',
                    default    => 'Your account is not active. Please contact the administrator.',
                };

                return back()
                    ->withErrors(['email' => $message])
                    ->withInput($request->only('email'));
            }

            $request->session()->regenerate();

            session([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->role ?? 'user',
            ]);

            return redirect()->intended('/dashboard')
                ->with('success', 'Welcome back!');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.'
        ])->withInput($request->only('email'));
    }

    /**
     * Logout user and destroy session
     */
    public function logout(Request $request)
    {
        Auth::logout();

        // Invalidate session & regenerate token for security
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'You have been logged out.');
    }
}
