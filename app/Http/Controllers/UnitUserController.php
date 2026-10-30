<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UnitUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UnitUserController extends Controller
{
    // ================= LOGIN FORM =================
    public function loginForm()
    {
        return view('unit.login');
    }
    public function loginFormM1()
    {
        return view('unit.loginm1');
    }

    // ================= LOGIN =================
    /**
     * Verify email + password against unit_users. The login forms always
     * asked for a password, but it was never checked before.
     */
    private function attemptUnitLogin(Request $request): ?UnitUser
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = UnitUser::where('email', $request->email)
            ->where('is_active', 1)
            ->first();

        if (!$user || !$user->password || !Hash::check($request->password, $user->password)) {
            Log::warning('Failed unit login', ['ip' => $request->ip()]);
            return null;
        }

        $request->session()->regenerate();

        return $user;
    }

    public function loginM1(Request $request)
    {
        $user = $this->attemptUnitLogin($request);

        if (!$user) {
            return back()
                ->with('error', 'Invalid email, password, or inactive account.')
                ->withInput($request->only('email'));
        }

        session([
            'unit_user_id'   => $user->id,
            'unit_full_name' => $user->full_name,
            'unit_email'     => $user->email,
            'unit_role'      => $user->role,
            'unit_name'      => $user->unit_name,
        ]);

        return redirect('/unit/dashboardM1');
    }
    // ================= LOGIN =================
    public function login(Request $request)
    {
        $user = $this->attemptUnitLogin($request);

        if (!$user) {
            return back()
                ->with('error', 'Invalid email, password, or inactive account.')
                ->withInput($request->only('email'));
        }

        session([
            'unit_user_id'   => $user->id,
            'unit_full_name' => $user->full_name,
            'unit_email'     => $user->email,
            'unit_role'      => $user->role,
            'unit_name'      => $user->unit_name,
        ]);

        return redirect('/unit/dashboard');
    }

    // ================= DASHBOARD =================
    public function dashboard()
    {
        if (!session()->has('unit_user_id')) {
            return redirect('/unit/login');
        }

        $unitName = session('unit_name');

        $documents = DB::table('users as u')
            ->join('personnel_data as pd', 'u.serial_number', '=', 'pd.serial_number')
            ->leftJoin('reinlist_personal_info as rpi', 'u.serial_number', '=', 'rpi.serial_number')
            ->where('u.mother_unit', $unitName)
            ->select(
                'pd.*',
                'u.name as applicant_name',
                'u.serial_number',

                // ✅ ADD THESE
                'pd.nbi_clearance_expiration',
                'pd.ombudsman_clearance_expiration',
                'pd.sandiganbayan_clearance_expiration'
            )
            ->orderBy('pd.created_at', 'desc')
            ->get();

        $stats = [
            'total' => $documents->count(),
            'Pending' => $documents->where('status', 'Pending')->count(),
            'Approved' => $documents->where('status', 'Approved')->count(),
        ];

        return view('unit.dashboard', compact('documents', 'stats'));
    }
    public function dashboardM1()
    {
        if (!session()->has('unit_user_id')) {
            return redirect('/unit/login');
        }

        $documents = DB::table('users as u')
            ->join('personnel_data as pd', 'u.serial_number', '=', 'pd.serial_number')
            ->leftJoin('reinlist_personal_info as rpi', 'u.serial_number', '=', 'rpi.serial_number')
            ->select(
                'pd.*',
                'u.name as applicant_name',
                'u.serial_number',

                // ✅ ADD THESE
                'pd.nbi_clearance_expiration',
                'pd.ombudsman_clearance_expiration',
                'pd.sandiganbayan_clearance_expiration'
            )
            ->orderBy('pd.created_at', 'desc')
            ->get();

        $stats = [
            'total' => $documents->count(),
            'Pending' => $documents->where('status', 'Pending')->count(),
            'Approved' => $documents->where('status', 'Approved')->count(),
        ];

        return view('unit.dashboard_m1', compact('documents', 'stats'));
    }
    public function searchSerial($serial)
    {
        $applicant = DB::table('reinlist_basic_info')
            ->where('serial_number', $serial)
            ->first();

        if (!$applicant) {
            return response()->json([
                'success' => false
            ]);
        }

        return response()->json([
            'success' => true,
            'fullname' => trim(
                ($applicant->first_name ?? '') . ' ' .
                    ($applicant->middle_name ?? '') . ' ' .
                    ($applicant->last_name ?? '')
            ),
            'serial_number' => $applicant->serial_number
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'personnel_id'  => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'full_name'     => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:unit_users,email',
            'username'      => 'nullable|string|max:100',
            'password'      => 'required|string|min:8',
            'unit_name'     => 'nullable|required_without:role|string|max:255',
            'role'          => 'nullable|string|max:255',
            'unit_code'     => 'nullable|string|max:50',
            'is_active'     => 'nullable|boolean',
        ]);

        DB::table('unit_users')->insert([
            'user_id' => $request->personnel_id,
            'personnel_id' => $request->personnel_id,
            'serial_number' => $request->serial_number,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => "unit_checker",
            // The admin form's "role" dropdown actually lists units
            // (MEPLEF, NOCOP, ...); use it when the free-text unit is empty.
            'unit_name' => $request->unit_name ?: $request->role,
            'unit_code' => $request->unit_code,
            'permissions' => $request->permissions,
            'is_active' => $request->boolean('is_active', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Unit account created successfully.');
    }
    public function storeDisciplinary(Request $request)
    {
        Log::info('Disciplinary Upload START', [
            'serial_number' => $request->serial_number,
            'has_files' => $request->hasFile('disciplinary_files'),
            'unit_user_id' => session('unit_user_id'),
        ]);

        $request->validate([
            'serial_number' => 'required|string',
            'disciplinary_files.*' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {

            if (!$request->hasFile('disciplinary_files')) {
                Log::warning('No disciplinary files uploaded', [
                    'serial_number' => $request->serial_number
                ]);

                return back()->with('error', 'No files uploaded');
            }

            // ================= FILE HANDLER (PRODUCTION SAFE) =================
            $upload = function ($file, $folder = 'disciplinary_actions') {

                if (!$file) return null;

                $basePath = config('pcg.upload_root') . '/disciplinary_actions';

                $folder = trim($folder, '/');
                $folderPath = $basePath . '/' . $folder;

                // normalize path
                $folderPath = preg_replace('#/+#', '/', $folderPath);

                if (!file_exists($folderPath)) {
                    mkdir($folderPath, 0755, true);
                }

                $fileName = time() . '_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());

                $file->move($folderPath, $fileName);

                // return DB relative path ONLY
                return $folder . '/' . $fileName;
            };

            foreach ($request->file('disciplinary_files') as $index => $file) {

                Log::info('Processing disciplinary file', [
                    'index' => $index,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);

                $path = $upload($file, 'disciplinary_actions');

                if (!$path) {
                    Log::error('Upload failed for file index ' . $index);
                    continue;
                }

                $insert = DB::table('reinlist_disciplinary_actions')->insert([
                    'serial_number' => $request->serial_number,
                    'file_path' => $path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('DB insert result', [
                    'insert' => $insert,
                    'path' => $path,
                    'serial_number' => $request->serial_number
                ]);
            }

            Log::info('Disciplinary Upload COMPLETED', [
                'serial_number' => $request->serial_number
            ]);

            return redirect()->back()->with('success', 'Disciplinary files uploaded successfully.');
        } catch (\Exception $e) {

            Log::error('Disciplinary Upload FAILED', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return back()->with('error', 'Upload failed. Please try again.');
        }
    }
    public function deleteDisciplinary($id)
    {
        $doc = DB::table('reinlist_disciplinary_actions')
            ->where('id', $id)
            ->first();

        if ($doc) {

            // Files are written by storeDisciplinary() under
            // <upload_root>/disciplinary_actions/<file_path>.
            $fullPath = config('pcg.upload_root') . '/disciplinary_actions/' . $doc->file_path;

            if ($doc->file_path && !str_contains($doc->file_path, '..') && is_file($fullPath)) {
                unlink($fullPath);
            } elseif ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                Storage::disk('public')->delete($doc->file_path);
            }

            DB::table('reinlist_disciplinary_actions')
                ->where('id', $id)
                ->delete();
        }

        return back()->with('success', 'Deleted successfully');
    }

    public function index_201()
    {
        $personnel = DB::table('personnel_201_files as p201')
            ->leftJoin('reinlist_basic_info as pi', 'p201.serial_number', '=', 'pi.serial_number')
            ->leftJoin('users as u', 'p201.serial_number', '=', 'u.serial_number')
            ->select(
                'p201.*',
                'pi.first_name',
                'pi.middle_name',
                'pi.last_name',
                'pi.rank',
                'pi.class',
                'u.mother_unit'
            )
            ->orderBy('pi.last_name')
            ->get();

        return view('unit.201_files_index', compact('personnel'));
    }

    public function show_201($id)
    {
        $documents = DB::table('personnel_201_files')
            ->where('serial_number', $id)
            ->first();
        $personnel = DB::table('reinlist_basic_info')
            ->where('serial_number', $id)
            ->first();

        return response()->json([
            'personnel' => $personnel,
            'documents' => $documents,
        ]);
    }

    // ================= LOGOUT =================
    public function logout(Request $request)
    {
        session()->forget(['unit_user_id', 'unit_full_name', 'unit_email', 'unit_role', 'unit_name']);
        $request->session()->regenerateToken();
        return redirect('/unit/login');
    }
}
