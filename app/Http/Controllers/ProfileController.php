<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    //
    public function generateSOI()
    {
        try {

            // =========================
            // GET SESSION EMAIL
            // =========================
            $email = session('user_email');

            // =========================
            // CHECK SESSION
            // =========================
            if (!$email) {

                Log::warning('SOI Session Email Missing', [
                    'ip_address' => request()->ip(),
                    'time' => now(),
                ]);

                abort(401, 'Session expired. Please login again.');
            }

            // =========================
            // LOG REQUEST
            // =========================
            Log::info('SOI Generation Started', [
                'email' => $email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'generated_at' => now(),
            ]);

            // =========================
            // GET USER DATA (FOR SERIAL NUMBER)
            // =========================
            $user = DB::table('users')
                ->where('email', $email)
                ->first();

            if (!$user) {

                Log::warning('SOI User Not Found', [
                    'email' => $email,
                    'time' => now(),
                ]);

                abort(404, 'User not found');
            }

            // =========================
            // GET APPLICANT DATA
            // =========================
            $applicant = DB::table('reinlist_basic_info as b')
                ->leftJoin('reinlist_personal_info as p', 'p.serial_number', '=', 'b.serial_number')
                ->where('b.serial_number', $user->serial_number)
                ->select('b.*', 'p.*')
                ->first();

            $academics = DB::table('reinlist_academic')
                ->where('serial_number', $user->serial_number)
                ->get();

            $dependents = DB::table('reinlist_dependents')
                ->where('serial_number', $user->serial_number)
                ->get();
            $academics = collect($academics)->map(function ($item) {

                $item->level = strtoupper($item->level); // normalize just in case

                return $item;
            });

            $groupedAcademics = $academics->groupBy('level');
            $groupedAcademics = collect($academics)->groupBy('level');

            $trainings = DB::table('reinlist_trainings')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedTrainings = $trainings->groupBy('category');
            $appointments = DB::table('reinlist_appointments')
                ->where('serial_number', $user->serial_number)
                ->orderBy('date', 'desc') // Present to Past
                ->get();

            $groupedAppointments = $appointments->groupBy('authority');

            $duties = DB::table('reinlist_duties')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedDuties = $duties->groupBy('type');

            $cgAwardsDecorations = DB::table('reinlist_cg_awards_decorations')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedCgAwardsDecorations = $cgAwardsDecorations->groupBy('type');

            $cgLetters = DB::table('reinlist_cg_letters')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedCgLetters = $cgLetters->groupBy('type');

            $awards = DB::table('reinlist_cg_awards_decorations')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedAwards = $awards->groupBy('type');

            $appreciations = DB::table('reinlist_cg_appreciations')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedAppreciations = $appreciations->groupBy('type');

            $plaques = DB::table('reinlist_cg_plaques')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedPlaques = $plaques->groupBy('type');
            $awardsMilitary = DB::table('reinlist_awards')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedAwardsMilitary = $awardsMilitary
                ->groupBy('category')
                ->map(function ($categoryGroup) {
                    return $categoryGroup->groupBy('type')->map(function ($items) {
                        return $items->values();
                    });
                });
            $otherLetters = DB::table('reinlist_other_letters')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedOtherLetters = $otherLetters->groupBy('type');
            $otherAppreciations = DB::table('reinlist_other_appreciations')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedOtherAppreciations = $otherAppreciations->groupBy('type');
            $otherPlaques = DB::table('reinlist_other_plaques')
                ->where('serial_number', $user->serial_number)
                ->get();

            $groupedOtherPlaques = $otherPlaques->groupBy('type');
            // =========================
            // APPLICANT NOT FOUND
            // =========================
            if (!$applicant) {

                Log::warning('SOI Applicant Not Found', [
                    'email' => $email,
                    'serial_number' => $user->serial_number,
                    'time' => now(),
                ]);

                abort(404, 'Applicant not found');
            }

            // =========================
            // SUCCESS LOG
            // =========================
            Log::info('SOI Generated Successfully', [
                'serial_number' => $user->serial_number,
                'email' => $email,
                'fullname' => trim(
                    ($applicant->firstname ?? '') . ' ' .
                        ($applicant->middlename ?? '') . ' ' .
                        ($applicant->lastname ?? '')
                ),
                'course' => $applicant->course ?? 'N/A',
                'generated_at' => now(),
            ]);

            $personnel_data = DB::table('personnel_data')
                ->where('serial_number', $user->serial_number)
                ->first();

            // ----------------------------------
            // 📌 CAREER MANAGEMENT PROFILE
            // ----------------------------------
            $careers = DB::table('reinlist_careers')
                ->where('serial_number', $user->serial_number)
                ->first();
            $civilianAwards  = DB::table('reinlist_civilian_awards')
                ->where('serial_number', $user->serial_number)
                ->get();
            function formatCareerDuration($value)
            {
                if (empty($value) || $value == 0) {
                    return 'N/A';
                }

                return $value;
            }
            // =========================
            // RETURN VIEW
            // =========================
            //   dd($groupedTrainings);
            return view('applicant.soi_template', compact(
                'personnel_data',
                'applicant',
                'dependents',
                'academics',
                'groupedAcademics',
                'trainings',
                'groupedTrainings',
                'appointments',
                'groupedAppointments',
                'duties',
                'groupedDuties',
                'cgAwardsDecorations',
                'groupedCgAwardsDecorations',
                'cgLetters',
                'groupedCgLetters',
                'awards',
                'groupedAwards',
                'appreciations',
                'groupedAppreciations',
                'plaques',
                'groupedPlaques',
                'awardsMilitary',
                'groupedAwardsMilitary',
                'otherLetters',
                'groupedOtherLetters',
                'otherAppreciations',
                'groupedOtherAppreciations',
                'otherPlaques',
                'groupedOtherPlaques',
                'careers',
                'civilianAwards'
            ));
        } catch (\Exception $e) {

            // =========================
            // ERROR LOG
            // =========================
            Log::error('SOI Generation Failed', [
                'email' => session('user_email'),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'time' => now(),
            ]);

            abort(500, 'Something went wrong while generating SOI.');
        }
    }
}
