<?php

namespace App\Http\Controllers;

use App\Support\SoiLock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * PCG HRMIS Android app (owner request 2026-11-20): members whose SOI is
 * submitted (users.soi_submitted_at, App\Support\SoiLock) may download the APK.
 * The file lives outside public/ (config pcg.android_app.apk_path) and is only
 * sent after that check; every download goes to the audit log.
 */
class MobileAppController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $apk = self::apk();

        return view('applicant.mobile-app', [
            'user' => $user,
            'eligible' => SoiLock::locked($user),
            'apk' => $apk,
        ]);
    }

    public function download(Request $request)
    {
        $user = $request->user();
        if (!SoiLock::locked($user)) {
            return redirect()->route('mobile.app')
                ->with('error', 'Submit your SOI first. The Android app is available once your SOI is submitted.');
        }

        $apk = self::apk();
        if ($apk === null) {
            return redirect()->route('mobile.app')
                ->with('error', 'The Android app is not available for download yet. Please try again later.');
        }

        Log::channel('audit')->info('mobile_app.downloaded', [
            'user_id' => $user->id,
            'serial_number' => $user->serial_number,
            'version' => $apk['version'],
            'ip' => $request->ip(),
        ]);

        return response()->download($apk['path'], $apk['download_name'], [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Details of the published APK, or null when none is in place. */
    public static function apk(): ?array
    {
        $path = (string) config('pcg.android_app.apk_path');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $version = trim((string) config('pcg.android_app.version'));
        $safeVersion = preg_replace('/[^0-9A-Za-z.\-]/', '', $version);

        return [
            'path' => $path,
            'version' => $version,
            'size_mb' => round(filesize($path) / 1048576, 1),
            'updated_at' => Carbon::createFromTimestamp(filemtime($path))->timezone('Asia/Manila'),
            'download_name' => 'PCG-HRMIS' . ($safeVersion !== '' ? '-' . $safeVersion : '') . '.apk',
        ];
    }
}
