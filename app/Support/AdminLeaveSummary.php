<?php

namespace App\Support;

use App\Models\Personnel;
use App\Services\OrgScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave at a glance for the admin console (the HR admin's landing pages and the
 * navbar): requests waiting and members away today, within the signed-in admin's
 * OrgScope. Null when the admin may not see leave or the tables do not exist yet.
 */
class AdminLeaveSummary
{
    public static function get(): ?array
    {
        $attrs = request()->attributes;            // once per request (navbar + page)
        if ($attrs->has('admin_leave_summary')) {
            return $attrs->get('admin_leave_summary') ?: null;
        }
        if (!AdminPermissions::can('leave.view') || !Schema::hasTable('leave_requests')) {
            $attrs->set('admin_leave_summary', []);
            return null;
        }
        try {
            $scope = app(OrgScope::class);
            $serials = $scope->isOrgWide() ? null : $scope->personnelQuery()->pluck('serial_number')
                ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
            $base = DB::table('leave_requests')
                ->when($serials !== null, fn ($q) => $q->whereIn(DB::raw('UPPER(TRIM(serial_number))'), $serials ?: ['__none__']));
            $today = LeaveCalendar::today()->toDateString();
            $summary = [
                'pending' => (clone $base)->whereRaw('LOWER(status) = ?', ['pending'])->count(),
                'awayToday' => (clone $base)->whereRaw('LOWER(status) = ?', ['approved'])
                    ->where('start_date', '<=', $today)->where('end_date', '>=', $today)->count(),
                'startingThisWeek' => (clone $base)->whereRaw('LOWER(status) = ?', ['approved'])
                    ->where('start_date', '>', $today)->where('start_date', '<=', LeaveCalendar::today()->addDays(7)->toDateString())->count(),
                'canApprove' => AdminPermissions::can('leave.approve'),
            ];
        } catch (\Throwable $e) {
            $attrs->set('admin_leave_summary', []);
            return null;
        }
        $attrs->set('admin_leave_summary', $summary);
        return $summary;
    }
}
