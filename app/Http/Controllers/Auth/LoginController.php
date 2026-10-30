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
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {

            $user = Auth::user();

            // Check if approved
            if ($user->status !== 'Approved') {

                Auth::logout();

                if ($user->status === 'Pending') {
                    return back()->withErrors([
                        'email' => 'Your account is waiting for admin approval.'
                    ]);
                }

                if ($user->status === 'rejected') {
                    return back()->withErrors([
                        'email' => 'Your account has been rejected by admin.'
                    ]);
                }
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
        ]);
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
