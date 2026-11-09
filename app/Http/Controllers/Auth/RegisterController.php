<?php

namespace App\Http\Controllers\Auth;

use App\Support\SecureFiles;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RegisterController extends Controller
{
    /**
     * Show registration form
     * type = regular | lateral | recad
     */
    public function showRegistrationForm(Request $request)
    {
        $type = $request->query('type');

        // Allowed types
        $allowedTypes = ['non_officer', 'officer'];

        // no or unknown type: the sign-in page offers "Register as officer / non-officer"
        if (!in_array($type, $allowedTypes, true)) {
            return redirect()->route('login');
        }

        return view('pages.register', [
            'type' => $type
        ]);
    }

    /**
     * Handle registration form submission
     */
    public function register(Request $request)
    {
        // Never log passwords or the base64 selfie.
        Log::info('Registration attempt started', [
            'email' => $request->email,
            'serial_number' => $request->serial_number,
        ]);

        try {

            // ✅ EMAIL CHECK FIRST (POPUP FRIENDLY)
            if (User::where('email', $request->email)->exists()) {
                Log::info('Registration refused', ['fields' => ['email_exists']]);
                // never flash the password or the selfie into the session
                return back()
                    ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']))
                    ->with('email_exists', 'Email already exists.');
            }

            // ✅ VALIDATION
            $validated = $request->validate([
                'registration_type'   => 'required|in:non_officer,officer',
                'rank'                => 'required|string|max:20',
                'name'                => 'required|string|max:255',
                'serial_number'       => 'required|string|max:100',
                'email'               => 'required|email',
                'password'            => 'required|confirmed|min:8',

                'dob'                 => 'required|date',
                'date_of_appointment' => 'required|date',
                'ete'                 => 'nullable|date',

                'command'             => 'required|string|max:255',
                'category'            => 'nullable|string|max:255',
                'unit'                => 'nullable|string|max:255',
                'sub_unit'            => 'nullable|string|max:255',

                // camera capture as a data URL; about 4 MB of image at most
                'selfie_image'        => 'required|string|max:5600000',
            ]);

            // One account per serial number: every record is joined on it, so
            // a second account with someone else's serial would see and
            // overwrite that person's files (case and spaces ignored).
            $validated['serial_number'] = trim($validated['serial_number']);
            if (User::serialTaken($validated['serial_number'])) {
                return back()
                    ->withErrors(['serial_number' => 'This serial number is already registered. Sign in with that account, or ask your unit M1 for help.'])
                    ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']));
            }

            // ✅ AUTO ETE
            if (empty($validated['ete'])) {
                $validated['ete'] = \Carbon\Carbon::parse($validated['date_of_appointment'])
                    ->addYears(3)
                    ->format('Y-m-d');
            }

            // ✅ UNIT FORMAT
            $unitAssignment = $validated['unit'] . ' - ' . $validated['sub_unit'];

            // ======================================================
            // ✅ SELFIE UPLOAD (FIXED & CLEAN)
            // ======================================================

            $selfieData = $validated['selfie_image'];

            if (str_contains($selfieData, 'base64,')) {
                $selfieData = explode(',', $selfieData)[1];
            }

            $image = base64_decode($selfieData, true);

            // Only accept real PNG/JPEG data (the camera capture sends PNG).
            $imageInfo = $image !== false ? @getimagesizefromstring($image) : false;

            if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
                || $imageInfo[0] < 64 || $imageInfo[1] < 64 || $imageInfo[0] > 2048 || $imageInfo[1] > 2048) {
                return back()
                    ->withErrors(['selfie_image' => 'Please capture a valid selfie photo.'])
                    ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']));
            }

            // Re-encode: only the picture itself is stored (no extra bytes or
            // embedded data), scaled down to at most 1280 px.
            // Never store the bytes as sent: if re-encoding fails (or GD is
            // missing) the selfie is refused, so nothing hidden rides along.
            $image = self::reencodeSelfie($image);
            if ($image === null) {
                return back()
                    ->withErrors(['selfie_image' => 'The selfie could not be processed. Please capture it again.'])
                    ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']));
            }

            // Production (cPanel) sets PCG_SELFIE_DIR to the old fixed path.
            $folderPath = config('pcg.selfie_dir');

            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true);
            }

            $fileName = bin2hex(random_bytes(16)) . '.png';
            $filePath = $folderPath . '/' . $fileName;

            // ✅ SAVE RELATIVE PATH TO DB (IMPORTANT)
            $selfiePath = 'selfies/' . $fileName;
            // encrypted at rest (AES-256-GCM); served only by FileController
            SecureFiles::storeBytes($image, $filePath, $selfiePath);

            Log::info('Selfie saved', [
                'file_path' => $filePath,
                'db_path'   => $selfiePath
            ]);

            // ======================================================
            // ✅ CREATE USER
            // ======================================================

            $user = User::create([
                'rank'                => $validated['rank'],
                'serial_number'       => $validated['serial_number'],
                'name'                => $validated['name'],
                'email'               => $validated['email'],
                'password'            => Hash::make($validated['password']),
                'registration_type'   => $validated['registration_type'],

                // ✅ FIXED SELFIE FIELD
                'selfie_image'        => $selfiePath,

                'date_of_birth'       => $validated['dob'],
                'date_of_appointment' => $validated['date_of_appointment'],
                'ete'                 => $validated['ete'],

                'mother_unit'         => $validated['command'],
                'sub_unit'            => $validated['category'],
                'unit_assignment'     => $unitAssignment,

                'status'              => 'Pending',
            ]);

            Log::info('User created successfully', ['user_id' => $user->id]);

            return redirect()
                ->route('login')
                ->with('registered', 'Your account is pending admin approval.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // field names only, never the values
            Log::info('Registration refused', ['fields' => array_keys($e->errors())]);

            return back()
                ->withErrors($e->errors())
                ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']));
        } catch (\Exception $e) {

            // the reference lets the administrator find this line in the log
            $ref = strtoupper(bin2hex(random_bytes(3)));
            // A database error's message carries the whole SQL with its values
            // (password hash, name, email, birth date): log only the
            // database's own reason, e.g. "Data too long for column 'sub_unit'".
            $reason = $e instanceof \Illuminate\Database\QueryException
                ? \App\Support\DbError::reason($e)
                : $e->getMessage();
            Log::error('Registration failed', [
                'ref' => $ref,
                'error' => $reason,
                'at' => basename($e->getFile()) . ':' . $e->getLine(),
            ]);

            return back()
                ->withErrors(['error' => "The server could not save your registration (reference $ref). Nothing was saved. Please try again in a few minutes; if it happens again, send this reference to the HRMIS administrator."])
                ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']));
        }
    }

    /** PNG of the selfie scaled to at most 1280 px, or null when GD is not available. */
    private static function reencodeSelfie(string $bytes): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 1280 / max($w, $h));
        if ($scale < 1) {
            $dst = imagescale($src, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
            imagedestroy($src);
            $src = $dst ?: null;
            if (!$src) {
                return null;
            }
        }
        ob_start();
        imagepng($src, null, 6);
        imagedestroy($src);

        return ob_get_clean() ?: null;
    }
}
