<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\UnitUser;
use App\Services\OrgScope;
use App\Support\AdminAudit;
use App\Support\LeaveReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Leave monthly summary and CSV export (App\Support\LeaveReport): unit checkers
 * see their unit (M1 every unit), admins with leave.view their OrgScope. Every
 * export is audited (leave.exported).
 */
class LeaveReportController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'month' => 'nullable|string|max:7',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'status' => 'nullable|in:' . implode(',', array_keys(LeaveReport::STATUSES)),
            'unit' => 'nullable|string|max:150',
        ]);
    }

    /** @return array{0: callable, 1: string, 2: bool} scope limit, unit filter, may filter by unit */
    private function unitScope(Request $request, array $f): array
    {
        $m1 = $request->session()->get('unit_role') === UnitUser::ROLE_M1;
        $own = trim((string) $request->session()->get('unit_name', ''));
        $unit = $m1 ? trim((string) ($f['unit'] ?? '')) : $own;
        return [fn ($q) => null, $unit, $m1];
    }

    private function adminLimit(OrgScope $scope): callable
    {
        $serials = $scope->isOrgWide() ? null : $scope->personnelQuery()->pluck('serial_number')
            ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
        return function ($q) use ($serials) {
            if ($serials !== null) {
                $q->whereIn(DB::raw('UPPER(TRIM(u.serial_number))'), $serials ?: ['__none__']);
            }
        };
    }

    private function unitList(callable $limit)
    {
        $q = DB::table('users as u')->whereNotNull('u.mother_unit')->where('u.mother_unit', '<>', '');
        $limit($q);
        return $q->distinct()->orderBy('u.mother_unit')->pluck('u.mother_unit');
    }

    private function summaryData(array $f, callable $limit, string $unit): array
    {
        [$from, $to, $month] = LeaveReport::period(['month' => $f['month'] ?? null]);
        $rows = LeaveReport::rows($limit, $unit, $from, $to, 'all');
        return LeaveReport::monthly($rows, $from, $to) + [
            'month' => $month, 'from' => $from, 'to' => $to, 'unit' => $unit,
            'prev' => $month->copy()->subMonth()->format('Y-m'), 'next' => $month->copy()->addMonth()->format('Y-m'),
        ];
    }

    private function export(Request $request, array $f, callable $limit, string $unit, string $scopeLabel)
    {
        [$from, $to, , $custom] = LeaveReport::period($f);
        $status = $f['status'] ?? 'all';
        $rows = LeaveReport::rows($limit, $unit, $from, $to, $status);
        $details = ['from' => $from, 'to' => $to, 'status' => $status, 'unit' => $unit !== '' ? $unit : null, 'rows' => $rows->count()];
        if ($request->session()->get('admin_logged_in')) {
            AdminAudit::record('leave.exported', $scopeLabel, $details);
        } else {
            Log::channel('audit')->info('leave.exported', $details + ['unit_user_id' => $request->session()->get('unit_user_id')]);
        }
        $name = 'leave-' . ($unit !== '' ? preg_replace('/[^A-Za-z0-9]+/', '-', $unit) . '-' : '') . ($custom ? "$from-to-$to" : substr($from, 0, 7)) . '.csv';
        return LeaveReport::csv($rows, $name, $from, $to);
    }

    public function unitSummary(Request $request)
    {
        $f = $this->filters($request);
        [$limit, $unit, $m1] = $this->unitScope($request, $f);
        return view('unit.leave-summary', $this->summaryData($f, $limit, $unit) + [
            'isM1' => $m1, 'unitList' => $m1 ? $this->unitList($limit) : collect(),
            'action' => route('unit.leave.summary'), 'exportUrl' => route('unit.leave.export'),
            'scopeName' => $unit !== '' ? $unit : 'every unit',
        ]);
    }

    public function unitExport(Request $request)
    {
        $f = $this->filters($request);
        [$limit, $unit] = $this->unitScope($request, $f);
        return $this->export($request, $f, $limit, $unit, 'unit leave');
    }

    public function adminSummary(Request $request, OrgScope $scope)
    {
        $f = $this->filters($request);
        $limit = $this->adminLimit($scope);
        $unit = trim((string) ($f['unit'] ?? ''));
        $scopeName = $scope->isOrgWide() ? 'all units' : (optional($scope->scopeUnit())->name ?? 'your command');
        return view('admin.leave.summary', $this->summaryData($f, $limit, $unit) + [
            'unitList' => $this->unitList($limit), 'action' => route('admin.leave.summary'), 'exportUrl' => route('admin.leave.export'),
            'scopeName' => $unit !== '' ? $unit : $scopeName,
        ]);
    }

    public function adminExport(Request $request, OrgScope $scope)
    {
        $f = $this->filters($request);
        return $this->export($request, $f, $this->adminLimit($scope), trim((string) ($f['unit'] ?? '')), 'leave export');
    }
}
