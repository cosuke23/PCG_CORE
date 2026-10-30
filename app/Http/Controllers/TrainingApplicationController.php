<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TrainingApplication;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TrainingApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = TrainingApplication::query();

        if ($request->filled('type')) {
            $query->where('training_type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('course_training', 'like', "%{$search}%")
                    ->orWhere('rank', 'like', "%{$search}%")
                    ->orWhere('division_unit', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%");
            });
        }

        $applications = $query
            ->latest('applied_at')
            ->paginate(15)
            ->withQueryString();

        $totalApplications = TrainingApplication::count();

        $pendingApplications = TrainingApplication::where(
            'status',
            'Pending'
        )->count();

        $approvedApplications = TrainingApplication::where(
            'status',
            'Approved'
        )->count();

        $rejectedApplications = TrainingApplication::where(
            'status',
            'Rejected'
        )->count();

        $completedApplications = TrainingApplication::where(
            'status',
            'Completed'
        )->count();

        $actApplications = TrainingApplication::where(
            'training_type',
            'ACT'
        )->count();

        $octApplications = TrainingApplication::where(
            'training_type',
            'OCT'
        )->count();

        $monthlyApplications = TrainingApplication::selectRaw(
            "DATE_FORMAT(COALESCE(applied_at, created_at), '%Y-%m') as month,
             COUNT(*) as total"
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyLabels = $monthlyApplications->map(function ($item) {
            return Carbon::createFromFormat(
                'Y-m',
                $item->month
            )->format('M Y');
        })->values();

        $monthlyValues = $monthlyApplications
            ->pluck('total')
            ->values();

        return view('admin.training-applications.index', compact(
            'applications',
            'totalApplications',
            'pendingApplications',
            'approvedApplications',
            'rejectedApplications',
            'completedApplications',
            'actApplications',
            'octApplications',
            'monthlyLabels',
            'monthlyValues'
        ));
    }

    public function updateStatus(
        Request $request,
        TrainingApplication $application
    ) {
        $request->validate([
            'status' => [
                'required',
                'in:Pending,Approved,Rejected,Completed',
            ],
        ]);

        $application->update([
            'status' => $request->status,
        ]);

        return back()->with(
            'success',
            'Training application status updated successfully.'
        );
    }
}
