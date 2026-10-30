<?php

namespace App\Http\Controllers;

use App\Models\OperationTrack;
use Illuminate\Http\Request;

class OperationTrackController extends Controller
{
    /**
     * Display Operational Track records.
     */
    public function index()
    {
        $operationTracks = OperationTrack::orderBy('no')->get();

        return view('operation_tracks.index', compact('operationTracks'));
    }

    /**
     * Store a new Operational Track.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'no' => 'nullable|integer',
            'division_unit' => 'required|string|max:255',
            'rank' => 'required|string|max:100',
            'year_in_grade' => 'nullable|integer',
            'course_training' => 'nullable|string|max:255',
            'provider' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        OperationTrack::create($validated);

        return redirect()
            ->route('operation_tracks.index')
            ->with('success', 'Operational Track added successfully.');
    }

    /**
     * Update an Operational Track.
     */
    public function update(Request $request, OperationTrack $operationTrack)
    {
        $validated = $request->validate([
            'no' => 'nullable|integer',
            'division_unit' => 'required|string|max:255',
            'rank' => 'required|string|max:100',
            'year_in_grade' => 'nullable|integer',
            'course_training' => 'nullable|string|max:255',
            'provider' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        $operationTrack->update($validated);

        return redirect()
            ->route('operation_tracks.index')
            ->with('success', 'Operational Track updated successfully.');
    }

    /**
     * Delete an Operational Track.
     */
    public function destroy(OperationTrack $operationTrack)
    {
        $operationTrack->delete();

        return redirect()
            ->route('operation_tracks.index')
            ->with('success', 'Operational Track deleted successfully.');
    }
}
