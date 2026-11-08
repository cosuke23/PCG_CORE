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
            'explorer' => $this->explorer($root, $since),
        ]);
    }

    /**
     * Rows behind the interactive overview: one per person in scope, with only
     * what the filters need. Birth dates, contact details and addresses are
     * never sent; a missing value is sent as a flag ("missing birth date").
     * The same scope (OrgScope) as the figures above applies.
     */
    private function explorer(?OrgUnit $root, string $since): array
    {
        // unit -> bucket (the row it is counted under in "Personnel by command/unit")
        $bucketOf = [];
        $treeRoot = $root;
        if (!$treeRoot) {
            $tops = OrgUnit::whereNull('parent_id')->orderBy('sort_order')->get();
            $treeRoot = $tops->count() === 1 ? $tops->first() : null;
        }
        $buckets = $treeRoot ? $treeRoot->children()->get() : OrgUnit::whereNull('parent_id')->orderBy('sort_order')->get();
        foreach ($buckets as $b) {
            foreach ($b->subtreeIds() as $id) {
                $bucketOf[$id] = $b->code;
            }
        }
        if ($treeRoot) {
            $bucketOf[$treeRoot->id] = $treeRoot->code . ' (directly)';
        }
        $unitCodes = OrgUnit::pluck('code', 'id');

        $moved = PersonnelAssignment::where('start_date', '>=', $since)
            ->whereIn('personnel_id', $this->scope->personnelQuery()->select('personnel.id'))
            ->orderByDesc('start_date')->orderByDesc('id')
            ->get(['id', 'personnel_id', 'org_unit_id', 'start_date', 'position', 'order_reference']);
        $movedIds = $moved->pluck('personnel_id')->flip();

        $rows = $this->scope->personnelQuery()
            ->with(['rank:id,code,name,classification', 'currentAssignment'])
            ->orderBy('personnel.full_name')
            ->get(['personnel.id', 'personnel.serial_number', 'personnel.full_name', 'personnel.classification', 'personnel.rank_id',
                'personnel.service_status', 'personnel.date_of_birth', 'personnel.date_entered_service', 'personnel.rank_date'])
            ->map(function ($p) use ($bucketOf, $unitCodes, $movedIds) {
                $a = $p->currentAssignment;
                $missing = array_values(array_filter([
                    $p->rank_id ? null : 'rank',
                    $p->date_of_birth ? null : 'birth date',
                    $p->date_entered_service ? null : 'service entry date',
                ]));

                return [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'serial' => $p->serial_number,
                    'cls' => $p->classification ?: null,
                    'rank' => $p->rank?->code,
                    'rankName' => $p->rank?->name,
                    'status' => $p->service_status ?: null,
                    'unit' => $a ? ($unitCodes[$a->org_unit_id] ?? null) : null,
                    'bucket' => $a ? ($bucketOf[$a->org_unit_id] ?? null) : null,
                    'position' => $a?->position,
                    'since' => $a?->start_date?->toDateString(),
                    'missing' => $missing,
                    'moved' => isset($movedIds[$p->id]),
                ];
            })->values()->all();

        return [
            'rows' => $rows,
            'ranks' => collect($this->byRank())->map(fn ($r) => ['code' => $r['label'], 'name' => $r['title'], 'group' => $r['group']])->all(),
            'buckets' => collect($this->byUnit($root))->map(fn ($u) => ['code' => $u['label'], 'name' => $u['title']])->all(),
            'movements' => $moved->map(fn ($m) => [
                'pid' => $m->personnel_id, 'unit' => $unitCodes[$m->org_unit_id] ?? null,
                'date' => $m->start_date?->toDateString(), 'position' => $m->position, 'ref' => $m->order_reference,
            ])->all(),
            'since' => $since,
            'orgWide' => $this->scope->isOrgWide(),
            'scopeLabel' => $root ? $root->code : 'PCG',
            'unitHeading' => $root ? 'unit' : 'command',
        ];
    }

    /**
     * CSV of the people the admin filtered on the overview. Only ids inside the
     * admin's scope are exported; the export is written to the audit log.
     */
    public function export(\Illuminate\Http\Request $request)
    {
        // one comma-separated value: posting thousands of ids[] fields would
        // hit PHP's max_input_vars (1000) and silently drop the rest
        $data = $request->validate([
            'ids' => ['required', 'string', 'max:400000', 'regex:/^\d+(,\d+)*$/'],
            'filters' => 'nullable|string|max:500',
        ]);
        $ids = array_slice(array_unique(array_map('intval', explode(',', $data['ids']))), 0, 50000);

        $people = $this->scope->personnelQuery()
            ->whereIn('personnel.id', $ids)
            ->with(['rank:id,code', 'currentAssignment.orgUnit:id,code,name'])
            ->orderBy('personnel.full_name')
            ->get(['personnel.id', 'personnel.serial_number', 'personnel.full_name', 'personnel.classification', 'personnel.rank_id',
                'personnel.service_status', 'personnel.date_of_birth', 'personnel.date_entered_service']);

        \App\Support\AdminAudit::record('hrmis.overview.csv', 'personnel', ['rows' => $people->count(), 'filters' => $data['filters'] ?? '']);

        $name = 'pcg-hrmis-overview-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($people) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Serial number', 'Name', 'Rank', 'Classification', 'Status', 'Current unit', 'Assigned since', 'Missing'], ',', '"', '');
            foreach ($people as $p) {
                $missing = implode('; ', array_filter([$p->rank_id ? null : 'rank', $p->date_of_birth ? null : 'birth date', $p->date_entered_service ? null : 'service entry date']));
                $row = [$p->serial_number, $p->full_name, $p->rank?->code, $p->classification, $p->service_status,
                    $p->currentAssignment?->orgUnit?->code, $p->currentAssignment?->start_date?->toDateString(), $missing];
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $row), ',', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
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
