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
                'users.registration_type',
                'users.created_at',
                'users.updated_at',
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
        $crm = $this->registrationPipeline($usersWithCourse);

        return view('admin.dashboard', compact(

            'crm',
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

    /**
     * CRM-style figures for the dashboard: monthly trend, month-on-month change,
     * oldest pending registration and a recent activity feed (registrations from
     * users.created_at, decisions from the admin audit log). Dates in Asia/Manila.
     */
    private function registrationPipeline($users): array
    {
        $tz = 'Asia/Manila';
        $now = now($tz);
        $status = fn ($u) => strtolower(trim((string) $u->status));
        $local = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v, config('app.timezone'))->timezone($tz) : null;

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $now->copy()->startOfMonth()->subMonths($i);
            $months[$m->format('Y-m')] = ['label' => $m->format('M Y'), 'registered' => 0, 'approved' => 0];
        }
        foreach ($users as $u) {
            $at = $local($u->created_at);
            if (!$at || !isset($months[$at->format('Y-m')])) {
                continue;
            }
            $months[$at->format('Y-m')]['registered']++;
            if ($status($u) === 'approved') {
                $months[$at->format('Y-m')]['approved']++;
            }
        }

        $thisKey = $now->format('Y-m');
        $lastKey = $now->copy()->startOfMonth()->subMonth()->format('Y-m');
        $thisMonth = $months[$thisKey]['registered'];
        $lastMonth = $months[$lastKey]['registered'];

        $oldestPending = $users->filter(fn ($u) => $status($u) === 'pending' && $u->created_at)
            ->map(fn ($u) => $local($u->created_at))->sort()->first();

        $byId = $users->keyBy('id');
        $activity = $users->filter(fn ($u) => $u->created_at)->map(fn ($u) => [
            'type' => 'registered', 'at' => $local($u->created_at), 'user' => $u, 'by' => null,
        ])->values();

        if (\Illuminate\Support\Facades\Schema::hasTable('admin_audit_logs')) {
            $decisions = DB::table('admin_audit_logs')
                ->where(fn ($q) => $q->where('action', 'like', 'admin/approve/%')->orWhere('action', 'like', 'admin/reject/%'))
                ->where('status', '<', 400)
                ->orderByDesc('id')->limit(40)
                ->get(['action', 'admin_email', 'created_at']);
            foreach ($decisions as $d) {
                [, $verb, $id] = array_pad(explode('/', $d->action), 3, null);
                $u = $byId->get((int) $id);
                // a refused approval still answered with a redirect: only count it if the member is approved now
                if (!$u || ($verb === 'approve' && $status($u) !== 'approved')) {
                    continue;
                }
                $activity->push(['type' => $verb === 'approve' ? 'approved' : 'rejected', 'at' => $local($d->created_at), 'user' => $u, 'by' => $d->admin_email]);
            }
        }

        return [
            'months' => array_values($months),
            'thisMonth' => $thisMonth,
            'lastMonth' => $lastMonth,
            'change' => $lastMonth > 0 ? (int) round(($thisMonth - $lastMonth) / $lastMonth * 100) : null,
            'oldestPendingDays' => $oldestPending ? (int) $oldestPending->diffInDays($now) : null,
            'activity' => $activity->sortByDesc('at')->take(8)->values(),
            'nonOfficers' => $users->filter(fn ($u) => strtolower((string) $u->registration_type) === 'non_officer')->count(),
        ];
    }

    // Approve a user
    public function approve($id)
    {
        if (!Session::get('admin_logged_in')) {
            return redirect()->route('admin.login')->withErrors(['username' => 'Please login as admin.']);
        }

        // Check and approve in one locked step, so two admins approving two
        // accounts with the same serial at the same moment cannot both succeed.
        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            $user = User::lockForUpdate()->findOrFail($id);
            $serial = strtoupper(trim((string) $user->serial_number));
            if ($serial !== '') {
                User::whereRaw('UPPER(TRIM(serial_number)) = ?', [$serial])->lockForUpdate()->pluck('id');
            }

            // Never approve a second account for a serial number that already has one.
            if (User::serialTaken((string) $user->serial_number, $user->id)) {
                return [false, $user];
            }

            $user->status = 'Approved';
            $user->save();

            return [true, $user];
        });
        [$approved, $user] = $result;

        if (!$approved) {
            $message = 'Not approved: serial number ' . $user->serial_number
                . ' already belongs to another account. Reject this registration or correct the serial first.';
            if (request()->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 409);
            }

            return back()->with('error', $message);
        }

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'status' => 'Approved', 'message' => $user->name . ' has been approved.']);
        }

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

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'status' => 'Rejected', 'message' => $user->name . ' has been rejected.']);
        }

        return back()->with('error', $user->name . ' has been rejected.');
    }
}
