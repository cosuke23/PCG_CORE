<?php

namespace App\Http\Controllers;

use App\Services\OrgScope;
use App\Support\AdminAudit;
use App\Support\LeaveReport;
use App\Support\PromotionEligibility;
use Illuminate\Http\Request;

/**
 * Promotion eligibility list (App\Support\PromotionEligibility) of the admin's
 * OrgScope: personnel.view to see, reports.view to download the CSV (audited).
 */
class PromotionEligibilityController extends Controller
{
    private function data(Request $request, OrgScope $scope): array
    {
        $f = $request->validate([
            'as_of' => 'nullable|date_format:Y-m-d',
            'state' => 'nullable|in:all,' . implode(',', array_keys(PromotionEligibility::STATES)),
            'unit' => 'nullable|string|max:50',
        ]);
        $asOf = PromotionEligibility::asOf($f['as_of'] ?? null);
        $rows = PromotionEligibility::rows($scope->personnelQuery(), $asOf);
        $units = $rows->pluck('unit')->filter()->unique()->sort()->values();
        $unit = trim((string) ($f['unit'] ?? ''));
        if ($unit !== '') {
            $rows = $rows->filter(fn ($r) => strcasecmp((string) $r->unit, $unit) === 0)->values();
        }
        $counts = $rows->countBy('state');
        $state = $f['state'] ?? 'eligible';
        $shown = $state === 'all' ? $rows : $rows->where('state', $state)->values();
        return compact('asOf', 'rows', 'shown', 'counts', 'state', 'unit', 'units');
    }

    public function index(Request $request, OrgScope $scope)
    {
        return view('admin.promotions', $this->data($request, $scope) + [
            'scopeName' => $scope->isOrgWide() ? 'all units' : (optional($scope->scopeUnit())->name ?? 'your command'),
            'canExport' => \App\Support\AdminPermissions::can('reports.view'),
            'years' => config('pcg.promotion.years'),
        ]);
    }

    public function export(Request $request, OrgScope $scope)
    {
        $d = $this->data($request, $scope);
        AdminAudit::record('promotion.exported', 'promotion eligibility', ['as_of' => $d['asOf']->toDateString(), 'state' => $d['state'], 'unit' => $d['unit'] ?: null, 'rows' => $d['shown']->count()]);
        $rows = $d['shown'];
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Serial number', 'Name', 'Rank', 'Next rank', 'Unit', 'Date of rank', 'Years in grade', 'Years needed', 'Eligible from', 'Missing schooling', 'State'], ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($out, array_map([LeaveReport::class, 'cell'], [$r->serial_number, $r->name, $r->rank, $r->next, $r->unit, $r->rank_date,
                    $r->years_in_grade, $r->required_years, $r->due, implode('; ', $r->missing), PromotionEligibility::STATES[$r->state]]), ',', '"', '');
            }
            fclose($out);
        }, 'promotion-eligibility-' . $d['asOf']->toDateString() . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
