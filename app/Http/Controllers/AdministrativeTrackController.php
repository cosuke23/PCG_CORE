<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AdministrativeTrack;
use Illuminate\Http\Request;

class AdministrativeTrackController extends Controller
{
    /**
     * Display the administrative course track for a user.
     */
    public function index()
    {
        $administrativeTracks = AdministrativeTrack::orderBy('rank')
            ->orderBy('year_in_grade')
            ->get();

        return view('administrative_tracks.show', compact('administrativeTracks'));
    }
}
