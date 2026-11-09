<?php

namespace App\Http\Controllers;

use App\Support\SoiLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** The member submits the SOI: from then on it is read-only (App\Support\SoiLock). */
class SoiController extends Controller
{
    public function submit(Request $request)
    {
        abort_unless(SoiLock::enabled(), 409, 'Run the database update (deploy with -Migrate) first.');
        $user = $request->user();

        // the SOI must exist: step 1 saved at least
        $hasSoi = DB::table('reinlist_basic_info')->where('serial_number', $user->serial_number)->exists();
        if (!$hasSoi) {
            return response()->json(['status' => 'error', 'message' => 'Save step 1 of your SOI before submitting it.'], 422);
        }

        DB::table('users')->where('id', $user->id)->whereNull('soi_submitted_at')->update(['soi_submitted_at' => now()]);
        Log::channel('audit')->info('soi.submitted', ['user_id' => $user->id, 'serial_number' => $user->serial_number, 'ip' => $request->ip()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Your SOI has been submitted. It is now read-only; your Adjutant or HR Unit Admin can reopen it if something must change.',
        ]);
    }
}
