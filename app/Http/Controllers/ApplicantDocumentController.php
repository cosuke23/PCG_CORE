<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // ✅ FIXED: REQUIRED
use App\Models\ApplicantDocument;

class ApplicantDocumentController extends Controller
{
    public function create()
    {
        Log::info('Applicant document upload page opened', [
            'user_id' => Auth::id()
        ]);

        return view('applicant.documents_upload');
    }
    public function store(Request $request)
    {
        try {

            Log::info('=== UPLOAD START ===');

            $user = $request->user();
            $userId = $user->id;
            $serial = $user->serial_number;

            // ================= EXISTING =================
            $existing = DB::table('personnel_data')
                ->where('personnel_id', $userId)
                ->first();

            // ================= FILE HANDLER (UPDATED) =================
            $upload = function ($file, $folder) {

                if (!$file) return null;

                // clean folder
                $folder = trim($folder, '/');
                $folder = preg_replace('#^(storage/|public/)#', '', $folder);

                // base path (REAL FILE LOCATION)
                $basePath = '/home/sghwkf6wslnz/public_html/pcg-mepcom.com/public/documents_toupload';
                // $basePath = '/home/sghwkf6wslnz/public_html/public/documents_toupload';
                // final folder path
                $pathFolder = rtrim($basePath, '/') . '/' . $folder;
                $pathFolder = preg_replace('#/+#', '/', $pathFolder);

                // create directory if not exists
                if (!file_exists($pathFolder)) {
                    mkdir($pathFolder, 0777, true);
                }

                // filename
                $fileName = time() . '_' . $file->getClientOriginalName();

                // move file
                $file->move($pathFolder, $fileName);

                // ✅ IMPORTANT: return CLEAN DB PATH (NO "storage/")
                return $folder . '/' . $fileName;
            };
            // ================= FIELD MAP =================
            $fileFields = [
                'soi' => 'documents',
                'nbi_clearance' => 'documents',
                'ombudsman_clearance' => 'documents',
                'sandiganbayan_clearance' => 'documents',
                'ofr' => 'documents',
                'medical_clearance' => 'documents',
                'pft_clearance' => 'documents',
                'whole_body_picture' => 'photos',
                'photo_2x2' => 'photos',
                'residential_sketch' => 'documents',
            ];

            $docTypeMap = [
                'soi' => 'SOI',
                'nbi_clearance' => 'NBI Clearance',
                'ombudsman_clearance' => 'Ombudsman',
                'sandiganbayan_clearance' => 'Sandiganbayan',
                'ofr' => 'OFR',
                'medical_clearance' => 'Medical',
                'pft_clearance' => 'PFT',
                'whole_body_picture' => 'Whole Body',
                'photo_2x2' => '2x2 Photo',
                'residential_sketch' => 'Residential Sketch',
            ];

            $data = [];

            // ================= PROCESS FILES =================
            foreach ($fileFields as $field => $folder) {

                if ($request->hasFile($field)) {

                    $path = $upload($request->file($field), $folder);

                    $data[$field] = $path;

                    Log::info('Uploaded file', [
                        'field' => $field,
                        'path' => $path
                    ]);

                    // 🔥 MARK REJECTED DOC AS COMPLIED
                    DB::table('rejected_documents')
                        ->where('serial_number', $serial)
                        ->where('doc_type', $docTypeMap[$field])
                        ->update([
                            'status' => 'complied',
                            'updated_at' => now()
                        ]);
                }
            }

            // ================= OTHER DATA =================
            $data['nbi_clearance_expiration'] = $request->nbi_clearance_expiration;
            $data['ombudsman_clearance_expiration'] = $request->ombudsman_clearance_expiration;
            $data['sandiganbayan_clearance_expiration'] = $request->sandiganbayan_clearance_expiration;

            $data['latitude'] = $request->latitude;
            $data['longitude'] = $request->longitude;

            $data['status'] = 'Pending';
            $data['updated_at'] = now();

            // ================= SAVE =================
            if ($existing) {

                DB::table('personnel_data')
                    ->where('personnel_id', $userId)
                    ->update($data);

                Log::warning('🟡 UPDATED RECORD');
            } else {

                DB::table('personnel_data')->insert(array_merge($data, [
                    'personnel_id' => $userId,
                    'serial_number' => $serial,
                    'created_at' => now()
                ]));

                Log::info('🟢 CREATED RECORD');
            }

            Log::info('=== UPLOAD SUCCESS ===');

            return back()->with('success', 'Documents uploaded successfully!');
        } catch (\Exception $e) {

            Log::error('❌ UPLOAD FAILED', [
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Something went wrong.');
        }
    }
    public function index_201()
    {
        $user = Auth::user();
        $serial = $user->serial_number;

        $documents = DB::table('personnel_201_files')
            ->where('serial_number', $serial)
            ->first();

        $personal = DB::table('reinlist_basic_info')
            ->where('serial_number', $serial)
            ->first();

        // ✅ CHECK IF HAS ANY DOCUMENTS
        $hasDocuments = false;

        if ($documents) {
            foreach (
                [
                    'soi',
                    'appointment',
                    'leave',
                    'awards',
                    'disciplinary',
                    'performance',
                    'legal',
                    'academic',
                    'financial',
                    'retirement',
                    'body_picture'
                ] as $field
            ) {

                if (!empty($documents->$field)) {
                    $hasDocuments = true;
                    break;
                }
            }
        }

        return view('applicant.201_files', [
            'user' => $user,
            'documents' => $documents,
            'personal' => $personal,
            'hasDocuments' => $hasDocuments
        ]);
    }

    /**
     * Store 201 Files
     */
    public function store_201(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $serial = $user->serial_number;
        $request->validate([
            'soi'            => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'appointment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'leave'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'awards'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'disciplinary'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'performance'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'legal'          => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'academic'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'financial'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'retirement'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'body_picture'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $folders = [
            'soi'          => '201_files/soi',
            'appointment'  => '201_files/appointment',
            'leave'        => '201_files/leave',
            'awards'       => '201_files/awards',
            'disciplinary' => '201_files/disciplinary',
            'performance'  => '201_files/performance',
            'legal'        => '201_files/legal',
            'academic'     => '201_files/academic',
            'financial'    => '201_files/financial',
            'retirement'   => '201_files/retirement',
            'body_picture' => '201_files/body_picture',
        ];


        // BASE STORAGE PATH (REAL SERVER PATH)
        $basePath = '/home/sghwkf6wslnz/public_html/pcg-mepcom.com/public';

        // Ensure base folder exists
        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        /**
         * FILE UPLOAD FUNCTION
         */
        $upload = function ($file, $folder) use ($basePath) {

            if (!$file) {
                return null;
            }

            // sanitize folder
            $folder = trim($folder, '/');
            $folder = preg_replace('#^(storage/|public/)#', '', $folder);

            // full folder path
            $pathFolder = rtrim($basePath, '/') . '/' . $folder;
            $pathFolder = preg_replace('#/+#', '/', $pathFolder);

            // create folder if not exists
            if (!is_dir($pathFolder)) {
                mkdir($pathFolder, 0777, true);
            }

            // safe filename
            $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());

            // move file
            $file->move($pathFolder, $fileName);

            // return CLEAN DB PATH (NO storage/, NO base path)
            return $folder . '/' . $fileName;
        };

        $uploadedFiles = [];

        foreach ($folders as $field => $folder) {

            if ($request->hasFile($field)) {
                $uploadedFiles[$field] = $upload(
                    $request->file($field),
                    $folder
                );
            }
        }
        $existing = DB::table('personnel_201_files')
            ->where('personnel_id', $userId)
            ->first();

        $data = [
            'user_id' => $userId,
            'personnel_id' => $userId,
            'serial_number' => $serial,

            'soi'          => $uploadedFiles['soi'] ?? null,
            'appointment'  => $uploadedFiles['appointment'] ?? null,
            'leave'        => $uploadedFiles['leave'] ?? null,
            'awards'       => $uploadedFiles['awards'] ?? null,
            'disciplinary' => $uploadedFiles['disciplinary'] ?? null,
            'performance'  => $uploadedFiles['performance'] ?? null,
            'legal'        => $uploadedFiles['legal'] ?? null,
            'academic'     => $uploadedFiles['academic'] ?? null,
            'financial'    => $uploadedFiles['financial'] ?? null,
            'retirement'   => $uploadedFiles['retirement'] ?? null,
            'body_picture' => $uploadedFiles['body_picture'] ?? null,
        ];

        if ($existing) {

            DB::table('personnel_201_files')
                ->where('personnel_id', $userId)
                ->update($data);
        } else {

            DB::table('personnel_201_files')->insert($data);
        }
        // OPTIONAL: Save to database
        // DB::table('personnel_201_files')->insert($uploadedFiles);

        return redirect()
            ->route('upload.201')
            ->with('success', '201 Files uploaded successfully.');
    }
}
