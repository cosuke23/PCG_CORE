<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Services\OrgScope;
use App\Support\CertificateExpiry;
use Illuminate\Http\Request;

/** Admin list of training certificates and 201 documents expiring (App\Support\CertificateExpiry), within OrgScope. */
class ExpiringController extends Controller
{
    public function index(Request $request, OrgScope $scope)
    {
        $days = (int) ($request->validate(['days' => 'nullable|integer|in:30,60,90,180'])['days'] ?? 90);
        $serials = $scope->isOrgWide() ? null : $scope->personnelQuery()->pluck('serial_number')
            ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
        $list = CertificateExpiry::upcoming(null, $serials, $days);
        return view('admin.expiring', [
            'list' => $list, 'days' => $days,
            'expired' => $list->where('days_left', '<', 0)->count(), 'soon' => $list->where('days_left', '>=', 0)->count(),
            'scopeName' => $scope->isOrgWide() ? 'all units' : (optional($scope->scopeUnit())->name ?? 'your command'),
        ]);
    }
}
