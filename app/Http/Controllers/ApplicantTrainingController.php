<?php

namespace App\Http\Controllers;

use App\Models\TrainingApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApplicantTrainingController extends Controller
{
    public function administrative()
    {
        return $this->training('ACT');
    }

    public function operational()
    {
        return $this->training('OCT');
    }

    private function training($type)
    {
        $user = Auth::user();

        $rank = $user->rank;

        $equivalentRank = $this->getEquivalentRank($rank);

        $yearFilter = request('year_in_grade', '1');

        if ($type === 'ACT') {

            $table = 'administrative_tracks';

            $title = 'Administrative Career and Training Track';
        } else {

            $table = 'operation_tracks';

            $title = 'Operational Career and Training Track';
        }

        $query = DB::table($table)
            ->leftJoin('training_applications', function ($join) use (
                $user,
                $type,
                $table
            ) {
                $join->on(
                    'training_applications.track_id',
                    '=',
                    $table . '.id'
                )
                    ->where(
                        'training_applications.serial_number',
                        '=',
                        $user->serial_number
                    )
                    ->where(
                        'training_applications.training_type',
                        '=',
                        $type
                    );
            })
            ->where(
                $table . '.rank',
                'LIKE',
                '%' . $equivalentRank . '%'
            );

        // A rank with no equivalent used to match every track (LIKE '%%').
        if ($equivalentRank === null) {
            $query->whereRaw('1 = 0');
        }

        if ($yearFilter === '1') {

            $query->where(
                $table . '.year_in_grade',
                1
            );
        } elseif ($yearFilter === '2') {

            $query->where(
                $table . '.year_in_grade',
                2
            );
        } elseif ($yearFilter === 'more_than_2') {

            $query->where(
                $table . '.year_in_grade',
                '>',
                2
            );
        }

        $tracks = $query
            ->select(
                $table . '.*',
                'training_applications.status as application_status'
            )
            ->orderBy(
                $table . '.year_in_grade'
            )
            ->get();

        return view(
            'applicant.training.index',
            compact(
                'tracks',
                'rank',
                'equivalentRank',
                'yearFilter',
                'type',
                'title'
            )
        );
    }

    private function getEquivalentRank($rank)
    {
        $rankEquivalency = [
            'ASN'  => 'E-1',
            'SN2'  => 'E-2',
            'SN1'  => 'E-3',
            'PO3'  => 'E-4',
            'PO2'  => 'E-5',
            'PO1'  => 'E-6',
            'ENS'  => 'O-1',
            'LTJG' => 'O-2',
        ];

        return $rankEquivalency[$rank] ?? null;
    }

    public function apply(Request $request)
    {
        $request->validate([
            'track_id' => ['required', 'integer'],
            'type' => ['required', 'in:ACT,OCT'],
        ]);

        $user = Auth::user();

        $serial_number = $user->serial_number;

        $table = $request->type === 'ACT'
            ? 'administrative_tracks'
            : 'operation_tracks';

        $track = DB::table($table)
            ->where('id', $request->track_id)
            ->first();

        if (!$track) {
            abort(404, 'Training track not found.');
        }

        $type = $request->type;

        return view('applicant.training.apply', compact(
            'track',
            'type',
            'serial_number'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'track_id' => ['required', 'integer'],
            'type' => ['required', 'in:ACT,OCT'],
        ]);

        $user = Auth::user();

        $table = $request->type === 'ACT'
            ? 'administrative_tracks'
            : 'operation_tracks';

        $track = DB::table($table)
            ->where('id', $request->track_id)
            ->first();

        if (!$track) {
            abort(404, 'Training track not found.');
        }

        $existingApplication = TrainingApplication::where(
            'serial_number',
            $user->serial_number
        )
            ->where('training_type', $request->type)
            ->where('track_id', $track->id)
            ->exists();

        if ($existingApplication) {
            return back()->with(
                'error',
                'You have already applied for this training.'
            );
        }

        TrainingApplication::create([
            'serial_number' => $user->serial_number,
            'training_type' => $request->type,
            'track_id' => $track->id,
            'course_training' => $track->course_training,
            'rank' => $track->rank,
            'division_unit' => $track->division_unit,
            'year_in_grade' => $track->year_in_grade,
            'provider' => $track->provider,
            'remarks' => $track->remarks,
            'status' => 'Pending',
            'applied_at' => now(),
        ]);

        return back()->with(
            'success',
            'Training application submitted successfully.'
        );
    }
}
