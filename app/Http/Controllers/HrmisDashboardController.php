<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use App\Services\OrgScope;
use Illuminate\Support\Facades\DB;

/**
 * HRMIS overview: figures computed from the personnel master, limited to the
 * signed-in admin's organisational scope. No figure is estimated or sampled.
 */
class HrmisDashboardController extends Controller
{
    public function __construct(private OrgScope $scope)
    {
    }

    public function index()
    {
        $base = fn () => $this->scope->personnelQuery();
        $root = $this->scope->scopeUnit();

        $total = $base()->count();
        $byClass = $base()->select('classification', DB::raw('COUNT(*) as n'))->groupBy('classification')->pluck('n', 'classification');
        $inactive = $base()->where('service_status', '!=', 'active')->count();
        $incomplete = $base()->where(fn ($q) => $q->whereNull('rank_id')->orWhereNull('date_of_birth')->orWhereNull('date_entered_service'))->count();

        $since = now()->subDays(30)->toDateString();
        $movements = PersonnelAssignment::with(['personnel.rank', 'orgUnit'])
            ->whereIn('personnel_id', $base()->select('personnel.id'))
            ->where('start_date', '>=', $since)
            ->orderByDesc('start_date')->orderByDesc('id');

        return view('admin.hrmis.dashboard', [
            'root' => $root,
            'orgWide' => $this->scope->isOrgWide(),
            'total' => $total,
            'officers' => (int) ($byClass['officer'] ?? 0),
            'nonOfficers' => (int) ($byClass['non_officer'] ?? 0),
            'inactive' => $inactive,
            'incomplete' => $incomplete,
            'movementCount' => (clone $movements)->count(),
            'recentMovements' => $movements->limit(8)->get(),
            'byUnit' => $this->byUnit($root),
            'byRank' => $this->byRank(),
            'unassigned' => $this->scope->isOrgWide() ? Personnel::whereDoesntHave('currentAssignment')->count() : null,
            'pendingRegistrations' => $this->scope->isOrgWide() ? $this->pendingRegistrations() : null,
        ]);
    }

    /**
     * Current personnel per subordinate unit of the scope root (or per
     * top-level unit below HQ when organisation-wide), each counted with
     * everything below it. People placed directly on the root get their own row.
     */
    private function byUnit(?OrgUnit $root): array
    {
        $ids = $this->scope->unitIds();
        $counts = PersonnelAssignment::whereNull('end_date')
            ->when($ids !== null, fn ($q) => $q->whereIn('org_unit_id', $ids))
            ->select('org_unit_id', DB::raw('COUNT(*) as n'))->groupBy('org_unit_id')->pluck('n', 'org_unit_id');

        if (!$root) {
            // organisation-wide: break down by the units directly under the top level (HQ)
            $tops = OrgUnit::whereNull('parent_id')->orderBy('sort_order')->get();
            $root = $tops->count() === 1 ? $tops->first() : null;
        }

        $buckets = $root ? $root->children()->get() : OrgUnit::whereNull('parent_id')->orderBy('sort_order')->get();
        $rows = [];
        foreach ($buckets as $unit) {
            $n = collect($unit->subtreeIds())->sum(fn ($id) => (int) ($counts[$id] ?? 0));
            $rows[] = ['label' => $unit->code, 'title' => $unit->name, 'count' => $n, 'unit_id' => $unit->id, 'inactive' => !$unit->is_active];
        }
        if ($root && ($direct = (int) ($counts[$root->id] ?? 0))) {
            $rows[] = ['label' => $root->code . ' (directly)', 'title' => 'Assigned to ' . $root->name . ' itself', 'count' => $direct, 'unit_id' => null, 'inactive' => false];
        }

        return $rows;
    }

    private function byRank(): array
    {
        $counts = $this->scope->personnelQuery()->select('rank_id', DB::raw('COUNT(*) as n'))->groupBy('rank_id')->pluck('n', 'rank_id');

        $rows = Rank::ordered()->get()
            ->filter(fn ($r) => isset($counts[$r->id]))
            ->map(fn ($r) => ['label' => $r->code, 'title' => $r->name, 'count' => (int) $counts[$r->id], 'group' => $r->classification])
            ->values()->all();

        if ($none = (int) $counts->get('', 0)) { // NULL rank_id is keyed as ''
            $rows[] = ['label' => 'No rank', 'title' => 'Rank not recorded', 'count' => $none, 'group' => null];
        }

        return $rows;
    }

    private function pendingRegistrations(): ?int
    {
        try {
            return DB::table('users')->whereRaw('LOWER(status) = ?', ['pending'])->count();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
