<?php

namespace App\Http\Controllers;

use App\Support\SecureFiles;
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

        // The "applicant.documents_upload" view never existed; send users to
        // the working upload page instead of a 500 error.
        return redirect()->route('upload.documents');
    }
    public function store(Request $request)
    {
        // Previously no server-side file validation: any file type (e.g. .php)
        // could be written into the web root.
        $request->validate([
            'soi'                     => 'nullable|file|mimes:pdf|max:10240',
            'nbi_clearance'           => 'nullable|file|mimes:pdf|max:10240',
            'ombudsman_clearance'     => 'nullable|file|mimes:pdf|max:10240',
            'sandiganbayan_clearance' => 'nullable|file|mimes:pdf|max:10240',
            'ofr'                     => 'nullable|file|mimes:pdf|max:10240',
            'medical_clearance'       => 'nullable|file|mimes:pdf|max:10240',
            'pft_clearance'           => 'nullable|file|mimes:pdf|max:10240',
            'residential_sketch'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'whole_body_picture'      => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'photo_2x2'               => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'nbi_clearance_expiration'           => 'nullable|date',
            'ombudsman_clearance_expiration'     => 'nullable|date',
            'sandiganbayan_clearance_expiration' => 'nullable|date',
            'latitude'                => 'nullable|numeric|between:-90,90',
            'longitude'               => 'nullable|numeric|between:-180,180',
        ]);

        try {

            Log::info('=== UPLOAD START ===');

            $user = $request->user();
            $userId = $user->id;
            $serial = $user->serial_number;

            // ================= EXISTING =================
            $existing = DB::table('personnel_data')
                ->where('personnel_id', $userId)
                ->first();

            // ================= WAITING FOR THE UNIT CHECKER =================
            // An uploaded document is locked until the checker approves or
            // returns it (App\Support\DocumentReview); the page hides the
            // controls, this refuses a hand-made request.
            $locked = \App\Support\DocumentReview::lockedFields($existing);
            $refused = [];
            foreach ($locked as $field) {
                $expiry = $field . '_expiration';
                $dateChanged = in_array($field, \App\Support\DocumentReview::EXPIRY_FIELDS, true)
                    && $request->exists($expiry)
                    && substr((string) ($request->input($expiry) ?: ''), 0, 10) !== substr((string) ($existing->$expiry ?? ''), 0, 10);
                if ($request->hasFile($field) || $dateChanged) {
                    $refused[$field] = 'This document is waiting for your unit checker. You can replace it after the checker approves it or returns it to you.';
                }
            }
            if ($refused) {
                return back()->withErrors($refused);
            }

            // ================= FILE HANDLER (UPDATED) =================
            $upload = function ($file, $folder) {

                if (!$file) return null;

                // clean folder
                $folder = trim($folder, '/');
                $folder = preg_replace('#^(storage/|public/)#', '', $folder);

                // base path (REAL FILE LOCATION)
                $basePath = config('pcg.upload_root') . '/documents_toupload';
                // final folder path
                $pathFolder = rtrim($basePath, '/') . '/' . $folder;
                $pathFolder = preg_replace('#/+#', '/', $pathFolder);

                // create directory if not exists
                if (!file_exists($pathFolder)) {
                    mkdir($pathFolder, 0755, true);
                }

                // filename
                $fileName = \App\Support\SecureFiles::storedName($file); // random name only: the person's own file name is not stored or logged

                // encrypted before it is written (AES-256-GCM); no readable copy is kept
                SecureFiles::store($file, $pathFolder, $fileName, 'documents_toupload/' . $folder . '/' . $fileName);

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
            // Only fields the form actually sent: a section the form hides
            // (e.g. Ombudsman/Sandiganbayan for some members) keeps its saved
            // dates instead of being wiped by every upload.
            foreach (['nbi_clearance_expiration', 'ombudsman_clearance_expiration', 'sandiganbayan_clearance_expiration', 'latitude', 'longitude'] as $field) {
                if ($request->exists($field)) {
                    $data[$field] = $request->input($field) ?: null;
                }
            }

            // What actually changed: a resubmission without any change must not
            // undo the checker's decision, and the message must say what happened.
            $uploaded = count(array_intersect_key($data, $fileFields));
            $changed = array_filter(array_diff_key($data, $fileFields), function ($value, $field) use ($existing) {
                $old = $existing->$field ?? null;
                if (in_array($field, ['latitude', 'longitude'], true)) {
                    return ($old === null) !== ($value === null) || ($value !== null && abs((float) $old - (float) $value) > 0.0000005);
                }

                return (string) $old !== (string) $value;
            }, ARRAY_FILTER_USE_BOTH);

            // a new pin answers a returned residence location
            if (array_intersect_key($changed, ['latitude' => 1, 'longitude' => 1]) && !empty($data['latitude'] ?? $existing->latitude ?? null)) {
                DB::table('rejected_documents')
                    ->where('serial_number', $serial)
                    ->where('doc_type', \App\Http\Controllers\UnitDocumentController::LOCATION_LABEL)
                    ->where('status', 'rejected')
                    ->update(['status' => 'complied', 'updated_at' => now()]);
            }

            if (!$uploaded && !$changed) {
                return back()->with('info', 'Nothing to save: no file was chosen and no date or pin changed.');
            }
            $data = array_intersect_key($data, $fileFields) + $changed;

            // documents, their validity or the residence pin changed: the unit
            // checker reviews again (the checker sees the pin on the review page)
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

            return back()->with('success', $uploaded
                ? ($uploaded === 1 ? '1 file' : $uploaded . ' files') . ' uploaded and encrypted. Your unit checker will review ' . ($uploaded === 1 ? 'it.' : 'them.')
                : 'Changes saved. Your unit checker will review them.');
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
        $basePath = config('pcg.upload_root');

        // Ensure base folder exists
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
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
                mkdir($pathFolder, 0755, true);
            }

            // safe filename
            $fileName = \App\Support\SecureFiles::storedName($file); // random name only: the person's own file name is not stored or logged

            // encrypted before it is written (AES-256-GCM); the stored value is
            // "201_files/<category>/<name>", which is also its path under /files
            SecureFiles::store($file, $pathFolder, $fileName, $folder . '/' . $fileName);

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

        if (!$uploadedFiles) {
            return redirect()->route('upload.201')->withErrors(['files' => 'Choose at least one file to upload.']);
        }

        if ($existing) {
            // Only the files uploaded now: the other 201 files stay linked
            // (setting every column used to erase the ones not re-uploaded).
            DB::table('personnel_201_files')
                ->where('personnel_id', $userId)
                ->update($uploadedFiles);
        } else {
            DB::table('personnel_201_files')->insert(array_merge(
                array_fill_keys(array_keys($folders), null),
                $uploadedFiles,
                ['user_id' => $userId, 'personnel_id' => $userId, 'serial_number' => $serial]
            ));
        }
        // OPTIONAL: Save to database
        // DB::table('personnel_201_files')->insert($uploadedFiles);

        return redirect()
            ->route('upload.201')
            ->with('success', (count($uploadedFiles) === 1 ? '1 file' : count($uploadedFiles) . ' files') . ' uploaded and encrypted to your 201 file.');
    }
}
