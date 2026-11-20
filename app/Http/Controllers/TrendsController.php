<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Services\OrgScope;
use App\Support\Trends;

/** Command dashboard trends (App\Support\Trends), limited to the admin's OrgScope; personnel.view. */
class TrendsController extends Controller
{
    public function index(OrgScope $scope)
    {
        $serials = $scope->isOrgWide() ? null : $scope->personnelQuery()->pluck('serial_number')
            ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
        return view('admin.trends', Trends::build($scope->personnelQuery(), $serials) + [
            'scopeName' => $scope->isOrgWide() ? 'all units' : (optional($scope->scopeUnit())->name ?? 'your command'),
        ]);
    }
}
