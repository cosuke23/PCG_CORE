<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\UnitUser;
use App\Services\OrgScope;
use App\Support\LeaveCalendar;
use App\Support\Readiness;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Manpower readiness (App\Support\Readiness) for unit checkers (own unit; M1 every
 * unit) and admins with leave.view (their OrgScope): strength, on leave, in a
 * MEPTDI class and available, on any day (default today, Asia/Manila).
 */
class ReadinessController extends Controller
{
    private function day(Request $request): Carbon
    {
        $d = (string) $request->query('date', '');
        try {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? Carbon::parse($d, LeaveCalendar::TZ)->startOfDay() : LeaveCalendar::today();
        } catch (\Throwable $e) {
            return LeaveCalendar::today();
        }
    }

    public function unit(Request $request)
    {
        $m1 = $request->session()->get('unit_role') === UnitUser::ROLE_M1;
        $own = trim((string) $request->session()->get('unit_name', ''));
        $unit = $m1 ? trim((string) $request->query('unit', '')) : $own;
        $day = $this->day($request);
        $data = Readiness::on($day->toDateString(), fn ($q) => null, $unit);

        return view('unit.leave-readiness', $data + [
            'day' => $day, 'unit' => $unit, 'isM1' => $m1,
            'unitList' => $m1 ? DB::table('users')->whereNotNull('mother_unit')->where('mother_unit', '<>', '')->distinct()->orderBy('mother_unit')->pluck('mother_unit') : collect(),
            'action' => route('unit.leave.readiness'),
        ]);
    }

    public function admin(Request $request, OrgScope $scope)
    {
        $serials = $scope->isOrgWide() ? null : $scope->personnelQuery()->pluck('serial_number')
            ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
        $limit = function ($q) use ($serials) {
            if ($serials !== null) {
                $q->whereIn(DB::raw('UPPER(TRIM(u.serial_number))'), $serials ?: ['__none__']);
            }
        };
        $unit = trim((string) $request->query('unit', ''));
        $day = $this->day($request);
        $data = Readiness::on($day->toDateString(), $limit, $unit);
        $units = DB::table('users as u')->whereNotNull('u.mother_unit')->where('u.mother_unit', '<>', '');
        $limit($units);

        return view('admin.leave.readiness', $data + [
            'day' => $day, 'unit' => $unit, 'unitList' => $units->distinct()->orderBy('u.mother_unit')->pluck('u.mother_unit'),
            'action' => route('admin.leave.readiness'),
            'scopeName' => $scope->isOrgWide() ? 'all units' : (optional($scope->scopeUnit())->name ?? 'your command'),
        ]);
    }
}
