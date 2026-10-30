<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class PersonnelAdministrativeTrainingController extends Controller
{
    public function index()
    {
        $records = DB::table('users as u')
            ->join('rank_equivalencies as re', function ($join) {
                $join->on('re.user_rank', '=', 'u.rank');
            })
            ->join('administrative_tracks as at', function ($join) {
                $join->whereRaw(
                    'LOWER(TRIM(at.rank)) = LOWER(TRIM(re.track_rank))'
                );
            })
            ->select(
                'u.id as user_id',
                'u.name',
                'u.rank as user_rank',

                'at.id as track_id',
                'at.division_unit',
                'at.year_in_grade',
                'at.course_training',
                'at.provider',
                'at.status',
                'at.remarks'
            )
            ->orderBy('u.name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Group all training records by personnel
        |--------------------------------------------------------------------------
        */

        $personnelTraining = $records
            ->groupBy('user_id')
            ->map(function ($trainingRecords) {

                $first = $trainingRecords->first();

                return (object) [
                    'user_id'   => $first->user_id,
                    'name'      => $first->name,
                    'user_rank' => $first->user_rank,

                    'trainings' => $trainingRecords
                        ->map(function ($training) {
                            return (object) [
                                'track_id'       => $training->track_id,
                                'division_unit'  => $training->division_unit,
                                'year_in_grade'  => $training->year_in_grade,
                                'course_training' => $training->course_training,
                                'provider'       => $training->provider,
                                'status'         => $training->status,
                                'remarks'        => $training->remarks,
                            ];
                        })
                        ->unique('track_id')
                        ->values(),
                ];
            })
            ->values();

        return view(
            'personnel_administrative_training.index',
            compact('personnelTraining')
        );
    }
}
