<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // Login lives in AdminLoginController. Access to every method below is
    // enforced by the "admin.session" route middleware; the inline checks
    // are kept as a second line of defence.

    public function logout(Request $request)
    {
        // Legacy GET /admin/logout. Previously cleared the wrong session keys,
        // which left the admin logged in.
        \App\Support\AdminAudit::record('logout');
        Session::forget(['admin_logged_in', 'admin_user_id', 'admin_email', 'admin_name', 'admin_role', 'admin_bootstrap', 'admin_2fa_user_id']);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Logged out successfully.');
    }

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
            // Explicit columns: rows are embedded in the page as JSON, and
            // users.* sent every password hash to the browser.
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.rank',
                'users.serial_number',
                'users.selfie_image',
                'users.status',
                'users.mother_unit',
                'users.sub_unit',
                'users.unit_assignment',
                'users.created_at',
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
