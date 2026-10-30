<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class AdminLoginController extends Controller
{
    // Show admin login page
    public function showLogin()
    {
        return view('admin.login');
    }

    // Handle admin login
    public function login(Request $request)
    {
        // Validate input
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // STATIC ADMIN LOGIN
        $adminEmail    = 'emsorix19@gmail.com';
        $adminPassword = 'Juls@0730';

        if ($request->email === $adminEmail && $request->password === $adminPassword) {
            // Store admin session
            session([
                'admin_logged_in' => true,
                'admin_email'     => $adminEmail,
            ]);
            $pendingUsers  = User::where('status', 'Pending')->get();
            $approvedUsers = User::where('status', 'Approved')->count();
            $rejectedUsers = User::where('status', 'Rejected')->count();
            $totalUsers    = User::count();
            return redirect()
                ->route('admin.dashboard')
                ->with('success', 'Admin login successful!')
                ->with('pendingUsers', $pendingUsers)
                ->with('approvedUsers', $approvedUsers)
                ->with('rejectedUsers', $rejectedUsers)
                ->with('totalUsers', $totalUsers);
        }

        // Invalid credentials
        return back()
            ->withErrors(['email' => 'Invalid admin credentials'])
            ->withInput($request->only('email'));
    }

    // Admin logout
    public function logout()
    {
        session()->forget('admin_logged_in');
        return redirect()->route('admin.login')->with('success', 'Admin logged out successfully');
    }
}
