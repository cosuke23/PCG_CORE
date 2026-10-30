<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // Static credentials
    private $adminUsername = 'colminasjulius@gmail.com';
    private $adminPassword = 'Juls@0730';

    // Show login form
    public function showLogin()
    {
        return view('admin.login');
    }

    // Handle login with static credentials
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($request->username === $this->adminUsername && $request->password === $this->adminPassword) {
            // Save admin session
            Session::put('is_admin', true);
            Session::put('admin_name', 'Julius Colminas');

            return redirect()->route('admin.dashboard')->with('success', 'Welcome, Admin!');
        }

        return back()->withErrors(['username' => 'Invalid admin credentials.']);
    }

    // Admin logout
    public function logout()
    {
        Session::forget(['is_admin', 'admin_name']);
        return redirect()->route('admin.login')->with('success', 'Logged out successfully.');
    }

    // Admin dashboard
    public function dashboard()
    {
        // Protect dashboard
        if (!Session::get('admin_logged_in')) {
            return redirect()->route('admin.login')->withErrors(['username' => 'Please login as admin.']);
        }

        $allUsers = User::all();
        $totalUsers = $allUsers->count();
        $pendingUsers  = User::where('status', 'Pending')->get();
        $approvedUsers = User::where('status', 'Approved')->count();
        $rejectedUsers = User::where('status', 'Rejected')->count();
        $usersWithCourse = DB::table('users')
            ->leftJoin('reinlist_academic', function ($join) {
                $join->on('users.serial_number', '=', 'reinlist_academic.serial_number')
                    ->whereRaw('reinlist_academic.id = (
                SELECT MIN(ra.id)
                FROM reinlist_academic AS ra
                WHERE ra.serial_number = users.serial_number
            )');
            })
            ->select(
                'users.*',
                'reinlist_academic.course',
                'reinlist_academic.level',
                'reinlist_academic.school'
            )
            ->get();
        $courseCounts = $usersWithCourse
            ->groupBy(function ($user) {
                return $user->course ?: 'N/A';
            })
            ->map->count();

        $unitCounts = $usersWithCourse
            ->groupBy(function ($user) {
                return $user->unit_assignment ?: 'N/A';
            })
            ->map->count();
        return view('admin.dashboard', compact(

            'allUsers',
            'totalUsers',
            'pendingUsers',
            'approvedUsers',
            'rejectedUsers',
            'courseCounts',
            'unitCounts',
            'usersWithCourse' // 👈 pass to blade
        ));
    }

    // Approve a user
    public function approve($id)
    {
        if (!Session::get('admin_logged_in')) {
            return redirect()->route('admin.login')->withErrors(['username' => 'Please login as admin.']);
        }

        $user = User::findOrFail($id);
        $user->status = 'Approved';
        $user->save();

        return back()->with('success', $user->name . ' has been approved.');
    }

    // Reject a user
    public function reject($id)
    {
        if (!Session::get('admin_logged_in')) {
            return redirect()->route('admin.login')->withErrors(['username' => 'Please login as admin.']);
        }

        $user = User::findOrFail($id);
        $user->status = 'Rejected';
        $user->save();

        return back()->with('error', $user->name . ' has been rejected.');
    }
}
