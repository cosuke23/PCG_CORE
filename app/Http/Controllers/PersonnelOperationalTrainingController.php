<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OperationTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonnelOperationalTrainingController extends Controller
{
    public function index()
    {
        /*
    |--------------------------------------------------------------------------
    | Get Personnel
    |--------------------------------------------------------------------------
    | Get personnel with a rank and a serial number.
    | Remove duplicate serial numbers so one personnel record is displayed
    | only once.
    |--------------------------------------------------------------------------
    */

        $users = User::whereNotNull('rank')
            ->whereNotNull('serial_number')
            ->where('serial_number', '!=', '')
            ->orderBy('serial_number')
            ->get()
            ->unique('serial_number')
            ->values();


        /*
    |--------------------------------------------------------------------------
    | Get Operational Track Records
    |--------------------------------------------------------------------------
    */

        $operationTracks = DB::table('operation_tracks')
            ->orderBy('rank')
            ->orderBy('year_in_grade')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | Rank Equivalency
    |--------------------------------------------------------------------------
    |
    | users.rank              operation_tracks.rank
    |
    | ASN                     E-1 (Apprentice Seaman)
    | SN2                     E-2 (Seaman 2nd Class)
    | SN1                     E-3 (Seaman 1st Class)
    | PO3                     E-4 (PO3)
    | PO2                     E-5 (PO2)
    | PO1                     E-6 (PO1)
    |
    |--------------------------------------------------------------------------
    */

        $rankMapping = [

            'ASN' => 'E-1 (Apprentice Seaman)',

            'SN2' => 'E-2 (Seaman 2nd Class)',

            'SN1' => 'E-3 (Seaman 1st Class)',

            'PO3' => 'E-4 (PO3)',

            'PO2' => 'E-5 (PO2)',

            'PO1' => 'E-6 (PO1)',

        ];


        /*
    |--------------------------------------------------------------------------
    | Match Personnel With Operational Track
    |--------------------------------------------------------------------------
    */

        $personnelTraining = $users->map(function ($user) use (
            $operationTracks,
            $rankMapping
        ) {

            /*
        |--------------------------------------------------------------------------
        | Get User Rank
        |--------------------------------------------------------------------------
        */

            $userRank = strtoupper(trim($user->rank ?? ''));


            /*
        |--------------------------------------------------------------------------
        | Convert User Rank To Operational Track Rank
        |--------------------------------------------------------------------------
        */

            $trackRank = $rankMapping[$userRank] ?? null;


            /*
        |--------------------------------------------------------------------------
        | Find Matching Operational Track
        |--------------------------------------------------------------------------
        */

            if ($trackRank) {

                $tracks = $operationTracks
                    ->filter(function ($track) use ($trackRank) {

                        return strtolower(trim($track->rank ?? ''))
                            === strtolower(trim($trackRank));
                    })
                    ->values();
            } else {

                $tracks = collect();
            }


            /*
        |--------------------------------------------------------------------------
        | Return Personnel + Training
        |--------------------------------------------------------------------------
        */

            return [

                'user' => $user,

                'tracks' => $tracks,

                'track_rank' => $trackRank,

                'has_training' => $tracks->isNotEmpty(),

            ];
        });


        /*
    |--------------------------------------------------------------------------
    | Return View
    |--------------------------------------------------------------------------
    */

        return view(
            'personnel.operational_training',
            compact(
                'personnelTraining',
                'operationTracks'
            )
        );
    }
}
