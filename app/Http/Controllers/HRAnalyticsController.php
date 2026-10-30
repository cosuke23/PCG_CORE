<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

class HRAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);

        /*
    |--------------------------------------------------------------------------
    | Available Years
    |--------------------------------------------------------------------------
    */

        $availableYears = User::query()
            ->selectRaw('YEAR(created_at) as year')
            ->whereNotNull('created_at')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');


        /*
    |--------------------------------------------------------------------------
    | Base Query
    |--------------------------------------------------------------------------
    */

        $personnelQuery = User::whereBetween('created_at', [
            $year . '-01-01 00:00:00',
            $year . '-12-31 23:59:59'
        ]);


        /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

        $totalPersonnel = (clone $personnelQuery)->count();

        /*
     * Your users table does NOT have is_active.
     * We therefore use Approved as active personnel.
     */
        $activePersonnel = (clone $personnelQuery)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->count();

        $pendingPersonnel = (clone $personnelQuery)
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->count();

        $approvedPersonnel = (clone $personnelQuery)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->count();

        $rejectedPersonnel = (clone $personnelQuery)
            ->whereRaw('LOWER(status) = ?', ['rejected'])
            ->count();

        $unitsRepresented = (clone $personnelQuery)
            ->whereNotNull('unit')
            ->where('unit', '!=', '')
            ->distinct('unit')
            ->count('unit');


        /*
    |--------------------------------------------------------------------------
    | CHART 1: Personnel Status
    |--------------------------------------------------------------------------
    */

        $statusChart = (clone $personnelQuery)
            ->select(
                'status',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('status')
            ->orderBy('status')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | CHART 2: Officer vs Non-Officer
    |--------------------------------------------------------------------------
    */

        $registrationChart = (clone $personnelQuery)
            ->select(
                'registration_type',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('registration_type')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | CHART 3: Personnel by Unit
    |--------------------------------------------------------------------------
    */

        $unitChart = (clone $personnelQuery)
            ->select(
                'unit',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('unit')
            ->where('unit', '!=', '')
            ->groupBy('unit')
            ->orderByDesc('total')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | CHART 4: Personnel by Rank
    |--------------------------------------------------------------------------
    */

        $rankChart = (clone $personnelQuery)
            ->select(
                'rank',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('rank')
            ->where('rank', '!=', '')
            ->groupBy('rank')
            ->orderByDesc('total')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | CHART 5: Academic / Training Courses
    |--------------------------------------------------------------------------
    */

        $academicChart = DB::table('reinlist_academic as ra')
            ->join('users as u', 'u.serial_number', '=', 'ra.serial_number')
            ->whereBetween('u.created_at', [
                $year . '-01-01 00:00:00',
                $year . '-12-31 23:59:59'
            ])
            ->select(
                'ra.course',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('ra.course')
            ->where('ra.course', '!=', '')
            ->groupBy('ra.course')
            ->orderByDesc('total')
            ->limit(10)
            ->get();


        /*
    |--------------------------------------------------------------------------
    | CHART 6: Historical HR Trend
    |--------------------------------------------------------------------------
    */

        $yearlyChart = User::query()
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('created_at')
            ->whereBetween(
                DB::raw('YEAR(created_at)'),
                [2023, $year]
            )
            ->groupBy(DB::raw('YEAR(created_at)'))
            ->orderBy('year')
            ->get();


        return view('admin.hr-analytics', compact(
            'year',
            'availableYears',

            'totalPersonnel',
            'activePersonnel',
            'pendingPersonnel',
            'approvedPersonnel',
            'rejectedPersonnel',
            'unitsRepresented',

            'statusChart',
            'registrationChart',
            'unitChart',
            'rankChart',
            'academicChart',
            'yearlyChart'
        ));
    }
}
