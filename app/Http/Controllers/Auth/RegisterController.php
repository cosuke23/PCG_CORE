<?php

namespace App\Http\Controllers\Auth;

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

        abort_unless(in_array($type, $allowedTypes), 404);

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
                return back()
                    ->withInput()
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

                'selfie_image'        => 'required|string',
            ]);

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

            if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
                return back()
                    ->withErrors(['selfie_image' => 'Please capture a valid selfie photo.'])
                    ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']));
            }

            // Production (cPanel) sets PCG_SELFIE_DIR to the old fixed path.
            $folderPath = config('pcg.selfie_dir');

            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true);
            }

            $fileName = bin2hex(random_bytes(16)) . '.png';
            $filePath = $folderPath . '/' . $fileName;

            file_put_contents($filePath, $image);
            chmod($filePath, 0644);
            // ✅ SAVE RELATIVE PATH TO DB (IMPORTANT)
            $selfiePath = 'selfies/' . $fileName;

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

            return back()
                ->withErrors($e->errors())
                ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']));
        } catch (\Exception $e) {

            Log::error('Registration failed', [
                'error' => $e->getMessage()
            ]);

            return back()
                ->withErrors(['error' => 'Registration failed. Please try again.'])
                ->withInput($request->except(['password', 'password_confirmation', 'selfie_image']));
        }
    }
}
