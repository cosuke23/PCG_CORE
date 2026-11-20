<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\UnitUser;
use App\Services\OrgScope;
use App\Support\AdminPermissions;
use App\Support\Deployments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Deployment and sea-duty records (App\Support\Deployments). Unit checkers: own
 * unit by users.mother_unit, M1 every unit. Admins: leave.view to see,
 * leave.credits to record and end (their OrgScope, like leave credits).
 */
class DeploymentController extends Controller
{
    // ------------------------------------------------------------ scopes

    private function unitLimit(Request $request): array
    {
        $m1 = $request->session()->get('unit_role') === UnitUser::ROLE_M1;
        $own = trim((string) $request->session()->get('unit_name', ''));
        $limit = function ($q) use ($m1, $own) {
            if (!$m1) {
                $q->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower($own)]);
            }
        };
        return [$limit, $m1];
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

    private function members(callable $limit, string $unit)
    {
        $q = DB::table('users as u')->whereNotNull('u.serial_number')->where('u.serial_number', '<>', '')
            ->whereRaw('LOWER(TRIM(u.status)) = ?', ['approved'])
            ->when($unit !== '', fn ($w) => $w->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower($unit)]));
        $limit($q);
        return $q->orderBy('u.mother_unit')->orderBy('u.name')->get(['u.id', 'u.name', 'u.rank', 'u.serial_number', 'u.mother_unit']);
    }

    /** The member in scope, or 403/404. */
    private function member(callable $limit, int $userId): object
    {
        $q = DB::table('users as u')->where('u.id', $userId);
        $exists = (clone $q)->whereNotNull('u.serial_number')->where('u.serial_number', '<>', '')->exists();
        if (!$exists) {
            abort(404);
        }
        $limit($q);
        return $q->first(['u.id', 'u.name', 'u.rank', 'u.serial_number', 'u.mother_unit']) ?? abort(403);
    }

    private function deployment(callable $limit, int $id): array
    {
        $dep = Deployments::ready() ? DB::table('personnel_deployments')->find($id) : null;
        if (!$dep) {
            abort(404);
        }
        return [$dep, $this->member($limit, (int) $dep->user_id)];
    }

    private function unitList(callable $limit)
    {
        $q = DB::table('users as u')->whereNotNull('u.mother_unit')->where('u.mother_unit', '<>', '');
        $limit($q);
        return $q->distinct()->orderBy('u.mother_unit')->pluck('u.mother_unit');
    }

    private function view(string $view, callable $limit, string $unit, bool $showUnitFilter, bool $canEdit, array $routes, string $scopeName)
    {
        $list = Deployments::list($limit, $unit);
        return view($view, [
            'list' => $list, 'activeCount' => $list->where('active', true)->count(),
            'members' => $canEdit ? $this->members($limit, $unit) : collect(),
            'unit' => $unit, 'unitList' => $showUnitFilter ? $this->unitList($limit) : collect(), 'showUnitFilter' => $showUnitFilter,
            'canEdit' => $canEdit, 'routes' => $routes, 'scopeName' => $scopeName, 'ready' => Deployments::ready(),
            'rnrPer30' => (float) config('pcg.deployment.rnr_days_per_30', 0),
        ]);
    }

    private function store(Request $request, callable $limit, array $by)
    {
        if (!Deployments::ready()) {
            return back()->with('error', 'Deployment records are not set up yet (database update pending).');
        }
        $data = $request->validate(Deployments::rules(), ['end_date.after_or_equal' => 'The end date cannot be before the start date.']);
        $member = $this->member($limit, (int) $data['user_id']);
        [, $msg] = Deployments::record($member, $data, $by, $request->ip());
        return back()->with('success', $msg);
    }

    private function finish(Request $request, callable $limit, int $id, array $by)
    {
        [$dep, $member] = $this->deployment($limit, $id);
        if ($dep->end_date !== null && $dep->rnr_entry_id !== null) {
            return back()->with('error', 'That deployment has already ended and its RNR was granted.');
        }
        $data = $request->validate(Deployments::endRules($dep), ['end_date.after_or_equal' => 'The end date cannot be before the start date.']);
        return back()->with('success', Deployments::end($dep, $member, $data['end_date'], (float) ($data['rnr_days'] ?? 0), $by, $request->ip()));
    }

    // ------------------------------------------------------------ unit checkers

    private function unitBy(Request $request): array
    {
        return ['type' => 'unit', 'id' => (int) $request->session()->get('unit_user_id'),
            'name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email'))];
    }

    public function unitIndex(Request $request)
    {
        [$limit, $m1] = $this->unitLimit($request);
        $unit = $m1 ? trim((string) $request->query('unit', '')) : '';
        return $this->view('unit.deployments', $limit, $unit, $m1, true,
            ['index' => route('unit.deployments'), 'store' => route('unit.deployments.store'), 'end' => 'unit.deployments.end'],
            $m1 ? ($unit !== '' ? $unit : 'every unit') : (string) $request->session()->get('unit_name'));
    }

    public function unitStore(Request $request)
    {
        return $this->store($request, $this->unitLimit($request)[0], $this->unitBy($request));
    }

    public function unitEnd(Request $request, int $id)
    {
        return $this->finish($request, $this->unitLimit($request)[0], $id, $this->unitBy($request));
    }

    // ------------------------------------------------------------ admins

    private function adminBy(Request $request): array
    {
        return ['type' => 'admin', 'id' => $request->session()->get('admin_user_id'),
            'name' => (string) ($request->session()->get('admin_name') ?: $request->session()->get('admin_email'))];
    }

    public function adminIndex(Request $request, OrgScope $scope)
    {
        $unit = trim((string) $request->query('unit', ''));
        $scopeName = $scope->isOrgWide() ? 'all units' : (optional($scope->scopeUnit())->name ?? 'your command');
        return $this->view('admin.deployments', $this->adminLimit($scope), $unit, true, AdminPermissions::can('leave.credits'),
            ['index' => route('admin.deployments'), 'store' => route('admin.deployments.store'), 'end' => 'admin.deployments.end'],
            $unit !== '' ? $unit : $scopeName);
    }

    public function adminStore(Request $request, OrgScope $scope)
    {
        return $this->store($request, $this->adminLimit($scope), $this->adminBy($request));
    }

    public function adminEnd(Request $request, OrgScope $scope, int $id)
    {
        return $this->finish($request, $this->adminLimit($scope), $id, $this->adminBy($request));
    }
}
