<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\PersonnelDocument;
use App\Models\PersonnelRegistration;
use App\Models\PersonnelTraining;
use App\Models\Promotion;
use App\Models\Rank;
use App\Services\OrgScope;
use App\Support\AdminPermissions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * HR reports. Every report reads only records inside the admin's scope
 * (OrgScope) and can be viewed, printed or exported as CSV (opens in Excel).
 */
class ReportController extends Controller
{
    public const REPORTS = [
        'roster' => ['Personnel roster', false],
        'by_unit' => ['Personnel by unit', false],
        'by_rank' => ['Personnel by rank', false],
        'classification' => ['Officer and non-officer distribution', false],
        'assignments' => ['Assignment history', true],
        'movements' => ['Personnel movements (transfers)', true],
        'promotions' => ['Promotions', true],
        'registrations' => ['Registration status', true],
        'incomplete' => ['Missing or incomplete information', false],
        'documents' => ['Document verification status', false],
        'trainings' => ['Training and qualifications', true],
    ];

    public function __construct(private OrgScope $scope)
    {
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:' . implode(',', array_keys(self::REPORTS))],
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'unit' => 'nullable|integer',
            'format' => 'nullable|in:html,csv',
        ]);

        $type = $data['type'] ?? null;
        $units = OrgUnit::flatTree()->filter(fn ($u) => $this->scope->canUseUnit($u->id))->values();
        if (!$type) {
            return view('admin.reports.index', ['type' => null, 'units' => $units, 'filters' => $data]);
        }

        $from = Carbon::parse($data['from'] ?? now()->subYear()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();
        $unitIds = $this->unitFilter($data['unit'] ?? null);

        [$columns, $rows] = $this->{'report' . str_replace('_', '', ucwords($type, '_'))}($from, $to, $unitIds);
        $title = self::REPORTS[$type][0];

        \App\Support\AdminAudit::record('report.' . ($data['format'] ?? 'html'), $type, ['rows' => count($rows)]);

        if (($data['format'] ?? 'html') === 'csv') {
            return $this->csv($type, $columns, $rows);
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(array_slice($rows, ($page - 1) * 50, 50), count($rows), 50, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.reports.index', [
            'type' => $type, 'title' => $title, 'dated' => self::REPORTS[$type][1],
            'columns' => $columns, 'rows' => $paginator, 'total' => count($rows),
            'from' => $from, 'to' => $to, 'units' => $units, 'filters' => $data,
            'scopeUnit' => $this->scope->scopeUnit(),
        ]);
    }

    /** Unit ids for the optional unit filter, never wider than the scope. */
    private function unitFilter(?int $unitId): ?array
    {
        $scopeIds = $this->scope->unitIds();
        if (!$unitId) {
            return $scopeIds;
        }
        $unit = OrgUnit::find($unitId);
        if (!$unit || !$this->scope->canUseUnit($unit->id)) {
            return $scopeIds;
        }

        return $unit->subtreeIds();
    }

    /** Personnel ids in scope, optionally narrowed to a unit subtree (by current assignment). */
    private function personnelIds(?array $unitIds)
    {
        return $this->scope->personnelQuery()
            ->when($unitIds !== null, fn ($q) => $q->whereHas('currentAssignment', fn ($a) => $a->whereIn('org_unit_id', $unitIds)))
            ->select('personnel.id');
    }

    private function d($date): string
    {
        return $date ? Carbon::parse($date)->format('Y-m-d') : '';
    }

    private function reportRoster($from, $to, ?array $unitIds): array
    {
        $rows = Personnel::with(['rank', 'currentAssignment.orgUnit'])->whereIn('id', $this->personnelIds($unitIds))->orderBy('full_name')->get()
            ->map(fn ($p) => [$p->serial_number, $p->full_name, $p->rank?->code, Rank::CLASSIFICATIONS[$p->classification] ?? '',
                $p->currentAssignment?->orgUnit->code, $p->currentAssignment?->position, $this->d($p->currentAssignment?->start_date),
                Personnel::STATUSES[$p->service_status] ?? $p->service_status])->all();

        return [['Serial number', 'Name', 'Rank', 'Classification', 'Unit', 'Position', 'Assigned since', 'Status'], $rows];
    }

    private function reportByUnit($from, $to, ?array $unitIds): array
    {
        $counts = PersonnelAssignment::whereNull('end_date')->whereIn('personnel_id', $this->personnelIds($unitIds))
            ->select('org_unit_id', DB::raw('COUNT(*) as n'))->groupBy('org_unit_id')->pluck('n', 'org_unit_id');

        $rows = OrgUnit::flatTree()->filter(fn ($u) => $unitIds === null || in_array($u->id, $unitIds, true))
            ->map(fn ($u) => [str_repeat('  ', $u->depth) . $u->code, $u->name, $u->typeLabel(), $u->is_active ? 'Active' : 'Inactive',
                (int) ($counts[$u->id] ?? 0), collect($u->subtreeIds())->sum(fn ($id) => (int) ($counts[$id] ?? 0))])
            ->values()->all();

        return [['Unit', 'Name', 'Level', 'Status', 'Assigned directly', 'Including subordinate units'], $rows];
    }

    private function reportByRank($from, $to, ?array $unitIds): array
    {
        $counts = Personnel::whereIn('id', $this->personnelIds($unitIds))->select('rank_id', DB::raw('COUNT(*) as n'))->groupBy('rank_id')->pluck('n', 'rank_id');
        $rows = Rank::ordered()->get()->map(fn ($r) => [$r->code, $r->name, Rank::CLASSIFICATIONS[$r->classification], (int) ($counts[$r->id] ?? 0)])->all();
        if ($none = (int) $counts->get('', 0)) {
            $rows[] = ['—', 'Rank not recorded', '', $none];
        }

        return [['Rank', 'Name', 'Classification', 'Personnel'], $rows];
    }

    private function reportClassification($from, $to, ?array $unitIds): array
    {
        $counts = Personnel::whereIn('id', $this->personnelIds($unitIds))->select('classification', DB::raw('COUNT(*) as n'))->groupBy('classification')->pluck('n', 'classification');
        $total = max(1, $counts->sum());
        $rows = [];
        foreach (Rank::CLASSIFICATIONS + ['' => 'Not classified'] as $key => $label) {
            $n = (int) $counts->get($key, 0);
            $rows[] = [$label, $n, round($n / $total * 100, 1) . '%'];
        }

        return [['Classification', 'Personnel', 'Share'], $rows];
    }

    private function reportAssignments($from, $to, ?array $unitIds): array
    {
        $rows = PersonnelAssignment::with(['personnel', 'orgUnit'])->whereIn('personnel_id', $this->personnelIds($unitIds))
            ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])->orderBy('start_date')->get()
            ->map(fn ($a) => [$a->personnel->serial_number, $a->personnel->full_name, $a->orgUnit->code, $a->position,
                $this->d($a->start_date), $this->d($a->end_date) ?: 'present', $a->order_reference])->all();

        return [['Serial number', 'Name', 'Unit', 'Position', 'Start', 'End', 'Order / reference'], $rows];
    }

    private function reportMovements($from, $to, ?array $unitIds): array
    {
        $assignments = PersonnelAssignment::with(['personnel', 'orgUnit'])->whereIn('personnel_id', $this->personnelIds($unitIds))
            ->orderBy('personnel_id')->orderBy('start_date')->get()->groupBy('personnel_id');

        $rows = [];
        foreach ($assignments as $list) {
            $prev = null;
            foreach ($list as $a) {
                if ($prev && $a->start_date->between($from, $to)) {
                    $rows[] = [$this->d($a->start_date), $a->personnel->serial_number, $a->personnel->full_name, $prev->orgUnit->code, $a->orgUnit->code, $a->order_reference];
                }
                $prev = $a;
            }
        }
        usort($rows, fn ($x, $y) => strcmp($x[0], $y[0]));

        return [['Effective', 'Serial number', 'Name', 'From', 'To', 'Order / reference'], $rows];
    }

    private function reportPromotions($from, $to, ?array $unitIds): array
    {
        $rows = Promotion::with(['fromRank', 'toRank'])->whereIn('personnel_id', $this->personnelIds($unitIds))
            ->whereBetween('effective_date', [$from->toDateString(), $to->toDateString()])->orderBy('effective_date')->get()
            ->map(function ($p) {
                $person = Personnel::find($p->personnel_id);

                return [$this->d($p->effective_date), $person->serial_number, $person->full_name, $p->fromRank?->code, $p->toRank->code, $p->order_reference];
            })->all();

        return [['Effective', 'Serial number', 'Name', 'From rank', 'To rank', 'Order / reference'], $rows];
    }

    private function reportRegistrations($from, $to, ?array $unitIds): array
    {
        $rows = PersonnelRegistration::with(['rank', 'orgUnit'])
            ->when($unitIds !== null, fn ($q) => $q->whereIn('org_unit_id', $unitIds))
            ->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get()
            ->map(fn ($r) => [$this->d($r->created_at), $r->serial_number, $r->fullName(), $r->rank?->code, $r->orgUnit->code,
                PersonnelRegistration::STATUSES[$r->status], $this->d($r->submitted_at), $this->d($r->decided_at)])->all();

        return [['Created', 'Serial number', 'Name', 'Rank', 'Proposed unit', 'Status', 'Submitted', 'Decided'], $rows];
    }

    private function reportIncomplete($from, $to, ?array $unitIds): array
    {
        $checks = ['rank_id' => 'rank', 'date_of_birth' => 'date of birth', 'date_entered_service' => 'service entry date',
            'email' => 'email', 'contact_number' => 'contact number'];

        $rows = Personnel::with(['currentAssignment.orgUnit', 'rank'])->whereIn('id', $this->personnelIds($unitIds))->orderBy('full_name')->get()
            ->map(function ($p) use ($checks) {
                $missing = collect($checks)->filter(fn ($label, $field) => empty($p->$field))->values();
                if (!$p->currentAssignment) {
                    $missing->push('assignment');
                }

                return $missing->isEmpty() ? null : [$p->serial_number, $p->full_name, $p->currentAssignment?->orgUnit->code, $missing->implode(', ')];
            })->filter()->values()->all();

        return [['Serial number', 'Name', 'Unit', 'Missing'], $rows];
    }

    private function reportDocuments($from, $to, ?array $unitIds): array
    {
        $rows = PersonnelDocument::with(['personnel', 'category'])->where('is_current', true)
            ->whereIn('personnel_id', $this->personnelIds($unitIds))
            ->when(!AdminPermissions::can('documents.restricted'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('is_restricted', false)))
            ->orderBy('status')->orderBy('personnel_id')->get()
            ->map(fn ($d) => [$d->personnel->serial_number, $d->personnel->full_name, $d->category->name, $d->title, 'v' . $d->version,
                PersonnelDocument::STATUSES[$d->status], $this->d($d->verified_at), $this->d($d->expires_on) . ($d->isExpired() ? ' (expired)' : '')])->all();

        return [['Serial number', 'Name', 'Category', 'Title', 'Version', 'Status', 'Verified', 'Expires'], $rows];
    }

    private function reportTrainings($from, $to, ?array $unitIds): array
    {
        $rows = PersonnelTraining::whereIn('personnel_id', $this->personnelIds($unitIds))
            ->where(fn ($q) => $q->whereBetween('end_date', [$from->toDateString(), $to->toDateString()])
                ->orWhereBetween('valid_until', [$from->toDateString(), $to->toDateString()]))
            ->orderBy('end_date')->get()
            ->map(function ($t) {
                $person = Personnel::find($t->personnel_id);

                return [$person->serial_number, $person->full_name, $t->title, PersonnelTraining::KINDS[$t->kind] ?? $t->kind, $t->provider,
                    $this->d($t->start_date), $this->d($t->end_date), $this->d($t->valid_until)];
            })->all();

        return [['Serial number', 'Name', 'Title', 'Type', 'Provider', 'From', 'To', 'Valid until'], $rows];
    }

    private function csv(string $type, array $columns, array $rows)
    {
        $name = 'pcg-hrmis-' . $type . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly
            fputcsv($out, $columns, ',', '"', '');
            foreach ($rows as $row) {
                // neutralise spreadsheet formulas in exported text
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $row), ',', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
