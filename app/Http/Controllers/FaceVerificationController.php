<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Profile;

class FaceVerificationController extends Controller
{
    // Display Blade page
      public function showFaceVerification()
    {
        return view('verify-face'); // Blade only shows live camera scan
    }
    // public function showFaceVerification($id)
    // {
    //     $applicant = Profile::findOrFail($id);
    //     return view('verify-face', compact('applicant'));
    // }

    // Optional: Save AJAX result
    public function saveResult(Request $request)
    {
        $request->validate([
            'applicant_id' => 'required|exists:applicants,id',
            'match' => 'required|boolean',
            'distance' => 'required|numeric',
        ]);

        $applicant = Profile::findOrFail($request->applicant_id);
        $applicant->face_verified = $request->match;
        $applicant->face_distance = $request->distance;
        $applicant->face_verified_at = now();
        $applicant->save();

        return response()->json(['success' => true]);
    }
}

