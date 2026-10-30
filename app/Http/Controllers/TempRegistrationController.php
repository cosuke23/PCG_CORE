<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;

class TempRegistrationController extends Controller
{
    /**
     * Save Step 1 (Basic Info) temporarily
     */
    public function saveStep1(Request $request)
    {
        // Log the incoming request
        Log::info('Temp Save Step 1 Request: ' . json_encode($request->all()));

        // The serial number always comes from the logged-in account. It used to
        // come from the request, which let any user overwrite another
        // member's reinlist_basic_info record.
        $request->merge(['serial_number' => $request->user()->serial_number]);

        try {
            // Validate only the necessary fields for step 1
            $validated = $request->validate([
                'last_name'      => 'required|string|max:255',
                'first_name'     => 'required|string|max:255',
                'middle_name'    => 'nullable|string|max:255',
                'extension_name' => 'nullable|string|max:50',
                'rank'           => 'required|string|max:50',
                'serial_number'  => 'required|string|max:100',
                'class'          => 'nullable|string|max:50',
                'birthday'       => 'required|date',
                'birth_place'    => 'nullable|string|max:255',
            ]);

            // Log the validated data
            Log::info('Temp Save Step 1 Validated Data: ' . json_encode($validated));

            // Current timestamp
            $now = Carbon::now();

            // Insert or Update based on serial_number
            DB::table('reinlist_basic_info')->updateOrInsert(
                ['serial_number' => $validated['serial_number']], // Match by serial_number
                [
                    'last_name'      => $validated['last_name'],
                    'first_name'     => $validated['first_name'],
                    'middle_name'    => $validated['middle_name'] ?? null,
                    'extension_name' => $validated['extension_name'] ?? null,
                    'rank'           => $validated['rank'],
                    'class'          => $validated['class'] ?? null,
                    'birthday'       => $validated['birthday'],
                    'birth_place'    => $validated['birth_place'] ?? null,
                    'updated_at'     => $now,
                    'created_at'     => $now, // DB will ignore if row exists
                ]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Step 1 saved successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log validation errors
            Log::warning('Temp Save Step 1 Validation Failed: ' . json_encode($e->errors()));

            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Temp Save Step 1 Error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Server error. Please try again later.'
            ], 500);
        }
    }

    public function saveStep2(Request $request)
    {
        try {
            $userId = $request->user()->id;

            // Check if serial_number exists in reinlist_personal_info
            $serialNumber = DB::table('users')->where('id', $userId)->value('serial_number');

            $existsInReinlist = DB::table('reinlist_personal_info')
                ->where('serial_number', $serialNumber)
                ->exists();

            // ✅ VALIDATION
            if (!$existsInReinlist) {
                // Run validation only if serial_number does NOT exist
                $validator = Validator::make($request->all(), [
                    'nickname' => 'nullable|string|max:100',
                    'sex' => 'nullable|string',
                    'civil_status' => 'nullable|string',
                    'citizenship' => 'nullable|string|max:100',
                    'religion' => 'nullable|string|max:100',
                    'blood_type' => 'nullable|string|max:5',
                    'height' => 'nullable|numeric',
                    'weight' => 'nullable|numeric',
                    'head_size' => 'nullable|numeric',
                    'foot_size' => 'nullable|numeric',
                    'waist_size' => 'nullable|numeric',
                    'distinct_features' => 'nullable|string|max:255',
                    'body_build' => 'nullable|string|max:50',
                    'hair_color' => 'nullable|string|max:50',
                    'skin_color' => 'nullable|string|max:50',
                    'eye_color' => 'nullable|string|max:50',
                    'contact_number' => 'nullable|string|max:20',
                    'email' => 'nullable|email|max:150',
                    'home_address' => 'nullable|string|max:255',
                    'permanent_address' => 'nullable|string|max:255',
                    'tin' => 'nullable|string|max:50',
                    'philhealth' => 'nullable|string|max:50',
                    'pagibig' => 'nullable|string|max:50',
                    'gsis' => 'nullable|string|max:50',
                    'sss' => 'nullable|string|max:50',
                    'drivers_license' => 'nullable|string|max:50',
                ]);
                if ($validator->fails()) {
                    Log::warning('❌ Validation Failed', $validator->errors()->toArray());

                    return response()->json([
                        'status' => 'error',
                        'errors' => $validator->errors()
                    ], 422);
                }
            }

            Log::info('📏 Body Measurements Received', [
                'head_size' => $request->head_size,
                'foot_size' => $request->foot_size,
                'waist_size' => $request->waist_size,
                'distinct_features' => $request->distinct_features,
            ]);
            // ✅ GET personnel_id from session
            $step1 = session('step1');


            // ✅ GET serial_number FROM DB
            $serial_number = DB::table('users')
                ->where('id', $userId)
                ->value('serial_number');

            Log::info('🔎 Fetched serial_number from DB', [
                'serial_number' => $serial_number
            ]);

            if (!$serial_number) {
                Log::error('❌ Serial number not found');

                return response()->json([
                    'status' => 'error',
                    'message' => 'Serial number not found.'
                ], 400);
            }

            // ✅ FORMAT NEW APPLICANT ID
            $personnel_id = DB::table('reinlist_personal_info')
                ->where('serial_number', $serial_number)
                ->value('personnel_id');

            Log::info('🔎 Checked personnel_id', [
                'serial_number' => $serial_number,
                'personnel_id' => $personnel_id
            ]);

            if ($personnel_id) {
                // ✅ EXISTING → skip formatting
                Log::info('⏭️ personnel_id exists, skipping personnel_id generation');

                $final_personnel_id = $personnel_id; // or keep existing personnel_id if needed

            } else {
                // ✅ NEW → generate personnel_id
                $year = date('Y');
                $final_personnel_id = "MEPCOM-{$serial_number}-{$year}";

                Log::info('🆔 Generated personnel_id', [
                    'personnel_id' => $final_personnel_id
                ]);
            }

            Log::info('🆔 Formatted Personnel ID', [
                'formatted_personnel_id' => $final_personnel_id
            ]);

            // ✅ INSERT OR UPDATE
            DB::table('reinlist_personal_info')->updateOrInsert(
                ['personnel_id' => $final_personnel_id],
                [
                    'serial_number' => $serial_number,
                    'nickname' => $request->nickname,
                    'sex' => $request->sex,
                    'civil_status' => $request->civil_status,
                    'citizenship' => $request->citizenship,
                    'religion' => $request->religion,
                    'blood_type' => $request->blood_type,

                    'height' => $request->height,
                    'weight' => $request->weight,
                    'head_size' => $request->head_size,
                    'foot_size' => $request->foot_size,
                    'waist_size' => $request->waist_size,
                    'distinct_features' => $request->distinct_features,
                    'body_build' => $request->body_build,
                    'hair_color' => $request->hair_color,
                    'skin_color' => $request->skin_color,
                    'eye_color' => $request->eye_color,

                    'contact_number' => $request->contact_number,
                    'email' => $request->email,

                    'home_address' => $request->home_address,
                    'permanent_address' => $request->permanent_address,

                    'tin' => $request->tin,
                    'philhealth' => $request->philhealth,
                    'pagibig' => $request->pagibig,
                    'gsis' => $request->gsis,
                    'sss' => $request->sss,
                    'drivers_license' => $request->drivers_license,

                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );


            return response()->json([
                'status' => 'success',
                'message' => 'Step 2 saved successfully!',
                'personnel_id' => $final_personnel_id
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function saveStep3(Request $request)
    {

        $userId = $request->user()->id;
        \Log::info($userId);
        // Check if serial_number exists in reinlist_personal_info
        $serialNumber = DB::table('users')->where('id', $userId)->value('serial_number');

        // Log incoming request
        \Log::info("Step 3 Save Request | user_id: {$userId}", $request->all());

        // Validate dependents
        $validator = Validator::make($request->all(), [
            'dependents' => 'required|array',
            'dependents.*.name' => 'required|string|max:255',
            'dependents.*.relationship' => 'required|string|max:50',
            'dependents.*.bday' => 'required|date',
        ]);

        if ($validator->fails()) {
            \Log::warning("Validation Failed | Step 3 | user_id: {$userId}", $validator->errors()->toArray());
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $dependents = $validator->validated()['dependents'];

        // Fetch personnel_id and serial_number from reinlist_personal_info
        $personalInfo = DB::table('reinlist_personal_info')
            ->where('serial_number', $serialNumber)
            ->select('personnel_id', 'serial_number')
            ->first();

        if (!$personalInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Personal info not found for this user.'
            ], 404);
        }

        try {
            // Delete old dependents first
            \Log::info("Deleting old dependents | personnel_id: {$personalInfo->personnel_id}");
            DB::table('reinlist_dependents')->where('serial_number', $personalInfo->serial_number)->delete();

            // Prepare insert data
            $insertData = [];
            foreach ($dependents as $dep) {
                $insertData[] = [
                    'personnel_id' => $personalInfo->personnel_id,
                    'serial_number' => $personalInfo->serial_number,
                    'name' => $dep['name'],
                    'relationship' => $dep['relationship'],
                    'birthdate' => $dep['bday'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert new dependents
            if (!empty($insertData)) {
                DB::table('reinlist_dependents')->insert($insertData);
            }

            \Log::info("Dependents saved successfully | personnel_id: {$personalInfo->personnel_id}", $dependents);

            return response()->json([
                'status' => 'success',
                'message' => 'Dependents saved successfully!'
            ]);
        } catch (\Exception $e) {
            \Log::error("Step 3 Save Error | personnel_id: {$personalInfo->personnel_id} | {$e->getMessage()}");

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save dependents. Please try again.'
            ], 500);
        }
    }
    public function saveStep4(Request $request)
    {
        $userId = $request->user()->id;

        \Log::info("Step 4 Save Request START | user_id: {$userId}");
        \Log::debug("Step 4 Payload", $request->all());

        // ================= GET SERIAL =================
        $serialNumber = DB::table('users')->where('id', $userId)->value('serial_number');
        \Log::info("Fetched serial_number: {$serialNumber} | user_id: {$userId}");

        // ================= VALIDATION =================
        $validator = Validator::make($request->all(), [
            'academic' => 'nullable|array',
            'masteral' => 'nullable|array',
            'vocational' => 'nullable|array',
            'trainings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            \Log::warning("Validation Failed | Step 4 | user_id: {$userId}", $validator->errors()->toArray());

            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        \Log::info("Validation Passed | user_id: {$userId}");

        // ================= GET PERSONAL INFO =================
        $personalInfo = DB::table('reinlist_personal_info')
            ->where('serial_number', $serialNumber)
            ->select('personnel_id', 'serial_number')
            ->first();

        if (!$personalInfo) {
            \Log::error("Personal info NOT FOUND | serial_number: {$serialNumber}");

            return response()->json([
                'status' => 'error',
                'message' => 'Personal info not found.'
            ], 404);
        }

        \Log::info("Personal info found", (array) $personalInfo);

        try {

            // ================= DELETE OLD =================
            \Log::info("Deleting old academic records | serial_number: {$personalInfo->serial_number}");
            DB::table('reinlist_academic')
                ->where('serial_number', $personalInfo->serial_number)
                ->delete();

            \Log::info("Deleting old training records | serial_number: {$personalInfo->serial_number}");
            DB::table('reinlist_trainings')
                ->where('serial_number', $personalInfo->serial_number)
                ->delete();

            // ================= SAVE ACADEMIC =================
            $academicInsert = [];

            foreach ($request->academic ?? [] as $edu) {
                $academicInsert[] = [
                    'personnel_id' => $personalInfo->personnel_id,
                    'serial_number' => $personalInfo->serial_number,
                    'level' => 'tertiary',
                    'course' => $edu['course'] ?? null,
                    'school' => $edu['school'] ?? null,
                    'year_start' => $edu['year_start'] ?? null,
                    'year_end' => $edu['year_end'] ?? null,
                    'remarks' => $edu['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach ($request->masteral ?? [] as $edu) {
                $academicInsert[] = [
                    'personnel_id' => $personalInfo->personnel_id,
                    'serial_number' => $personalInfo->serial_number,
                    'level' => 'masteral',
                    'course' => $edu['course'] ?? null,
                    'school' => $edu['school'] ?? null,
                    'year_start' => $edu['year_start'] ?? null,
                    'year_end' => $edu['year_end'] ?? null,
                    'remarks' => $edu['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($request->secondary) {
                $academicInsert[] = [
                    'personnel_id' => $personalInfo->personnel_id,
                    'serial_number' => $personalInfo->serial_number,
                    'level' => 'secondary',
                    'course' => null,
                    'school' => $request->secondary['school'] ?? null,
                    'year_start' => $request->secondary['year_start'] ?? null,
                    'year_end' => $request->secondary['year_end'] ?? null,
                    'remarks' => $request->secondary['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($request->primary) {
                $academicInsert[] = [
                    'personnel_id' => $personalInfo->personnel_id,
                    'serial_number' => $personalInfo->serial_number,
                    'level' => 'primary',
                    'course' => null,
                    'school' => $request->primary['school'] ?? null,
                    'year_start' => $request->primary['year_start'] ?? null,
                    'year_end' => $request->primary['year_end'] ?? null,
                    'remarks' => $request->primary['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach ($request->vocational ?? [] as $voc) {
                $academicInsert[] = [
                    'personnel_id' => $personalInfo->personnel_id,
                    'serial_number' => $personalInfo->serial_number,
                    'level' => 'vocational',
                    'course' => $voc['course'] ?? null,
                    'school' => $voc['school'] ?? null,
                    'year_start' => $voc['year_end'] ?? null,
                    'year_end' => $voc['year_end'] ?? null,
                    'remarks' => $voc['authority'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            \Log::info("Prepared academic records: " . count($academicInsert));

            if (!empty($academicInsert)) {
                DB::table('reinlist_academic')->insert($academicInsert);
                \Log::info("Inserted academic records successfully");
            }

            // ================= SAVE TRAININGS =================
            $trainingInsert = [];
            $categories = ['coastguard', 'others_local', 'others_foreign', 'military', 'military_others_local', 'military_others_foreign'];

            foreach ($categories as $cat) {
                foreach ($request->trainings[$cat] ?? [] as $train) {
                    $trainingInsert[] = [
                        'personnel_id' => $personalInfo->personnel_id,
                        'serial_number' => $personalInfo->serial_number,
                        'category' => $cat,
                        'course' => $train['course'] ?? null,
                        'type' => $train['type'] ?? null,
                        'authority' => $train['authority'] ?? null,
                        'school' => $train['school'] ?? null,
                        'period' => $train['period'] ?? null,
                        'remarks' => $train['remarks'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            \Log::info("Prepared training records: " . count($trainingInsert));

            if (!empty($trainingInsert)) {
                DB::table('reinlist_trainings')->insert($trainingInsert);
                \Log::info("Inserted training records successfully");
            }

            \Log::info("Step 4 SUCCESS | personnel_id: {$personalInfo->personnel_id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Step 4 saved successfully!'
            ]);
        } catch (\Exception $e) {
            \Log::error("Step 4 ERROR | personnel_id: {$personalInfo->personnel_id} | " . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save Step 4.'
            ], 500);
        }
    }
    public function saveStep5(Request $request)
    {
        try {

            $userId = $request->user()->id;

            \Log::info("Step 4 Save Request START | user_id: {$userId}");
            \Log::debug("Step 4 Payload", $request->all());

            // ================= GET SERIAL =================
            $serialNumber = DB::table('users')->where('id', $userId)->value('serial_number');

            Log::info('Step 5 Save Started', [
                'applicant_id' => $serial ?? null
            ]);

            $serial = $serialNumber;

            DB::beginTransaction();

            /*
        |--------------------------------------------------------------------------
        | 1. CLEAR OLD DATA (IMPORTANT)
        |--------------------------------------------------------------------------
        */
            DB::table('reinlist_appointments')
                ->where('serial_number', $serial)
                ->delete();

            /*
        |--------------------------------------------------------------------------
        | 2. INSERT APPOINTMENTS
        |--------------------------------------------------------------------------
        */
            if ($request->has('appointments')) {

                foreach ($request->appointments as $a) {

                    DB::table('reinlist_appointments')->insert([
                        'serial_number' => $serial,
                        'applicant_id' => $serial,
                        'description' => $a['description'] ?? null,
                        'date' => $a['date'] ?? null,
                        'authority' => $a['authority'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            /*
        |--------------------------------------------------------------------------
        | 3. DUTIES (RECOMMENDED SEPARATE TABLE)
        |--------------------------------------------------------------------------
        */
            DB::table('reinlist_duties')
                ->where('applicant_id', $serial)
                ->delete();

            $dutyTypes = [
                'shipboard',
                'shipboard_collateral',
                'shorebased',
                'shorebased_collateral',
                'special',
                'govt',
                'non_govt'
            ];

            foreach ($dutyTypes as $type) {

                if (!empty($request->duties[$type])) {

                    foreach ($request->duties[$type] as $d) {

                        DB::table('reinlist_duties')->insert([
                            'serial_number' => $serial,
                            'applicant_id' => $serial,
                            'type' => $type,

                            // handle different fields
                            'unit' => $d['unit'] ?? null,
                            'position' => $d['position'] ?? null,
                            'agency' => $d['agency'] ?? null,
                            'period' => $d['period'] ?? null,
                            'authority' => $d['authority'] ?? null,

                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Step 5 data saved successfully'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Step 5 Save Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save Step 5'
            ], 500);
        }
    }
    public function saveStep6(Request $request)
    {
        try {

            $validated = $request->validate([
                'cgAwardsDecorations' => 'nullable|array',
                'cgLetters' => 'nullable|array',
                'cgAppreciations' => 'nullable|array',   // NEW
                'cgPlaques' => 'nullable|array',         // NEW
                'militaryAwards' => 'nullable|array',
                'careers' => 'nullable|array',
                'otherLetters' => 'nullable|array',        // NEW
                'otherAppreciations' => 'nullable|array',   // NEW
                'otherPlaques' => 'nullable|array',         // NEW
                'civilianCommendations' => 'nullable|array',
            ]);

            $userId = $request->user()->id;

            \Log::info("Step 6 Save Request START | user_id: {$userId}");
            \Log::debug("Step 6 Payload", $request->all());

            // ================= GET SERIAL =================
            $serialNumber = DB::table('users')
                ->where('id', $userId)
                ->value('serial_number');

            // =================================================
            // DELETE OLD DATA
            // =================================================
            DB::table('reinlist_awards')->where('applicant_id', $userId)->delete();
            DB::table('reinlist_careers')->where('applicant_id', $userId)->delete();
            DB::table('reinlist_cg_awards_decorations')->where('applicant_id', $userId)->delete();
            DB::table('reinlist_cg_letters')->where('applicant_id', $userId)->delete();
            DB::table('reinlist_civilian_awards')->where('applicant_id', $userId)->delete();

            // NEW TABLES
            DB::table('reinlist_cg_appreciations')->where('applicant_id', $userId)->delete();
            DB::table('reinlist_cg_plaques')->where('applicant_id', $userId)->delete();
            DB::table('reinlist_other_letters')->where('applicant_id', $userId)->delete();
            DB::table('reinlist_other_appreciations')->where('applicant_id', $userId)->delete();
            DB::table('reinlist_other_plaques')->where('applicant_id', $userId)->delete();

            // =========================
            // MILITARY AWARDS
            // =========================
            if (!empty($validated['militaryAwards'])) {

                $awardsInsert = [];

                foreach ($validated['militaryAwards'] as $award) {

                    if (!empty($award['branch']) && !empty($award['type'])) {
                        $awardsInsert[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,
                            'category' => $award['category'] ?? null,
                            'type' => $award['type'] ?? null,
                            'authority' => $award['branch'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (!empty($awardsInsert)) {
                    DB::table('reinlist_awards')->insert($awardsInsert);
                }
            }

            // =========================
            // CAREERS
            // =========================
            if (!empty($validated['careers'])) {

                $careersInsert = [];

                foreach ($validated['careers'] as $career) {

                    if (
                        !empty($career['student']) ||
                        !empty($career['sea']) ||
                        !empty($career['staff']) ||
                        !empty($career['instructor']) ||
                        !empty($career['command']) ||
                        !empty($career['organization_affiliation']) ||
                        !empty($career['civil_service_eligibility']) ||
                        !empty($career['compulsory_retirement'])
                    ) {
                        $careersInsert[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,

                            'student' => $career['student'] ?? null,
                            'sea' => $career['sea'] ?? null,
                            'staff' => $career['staff'] ?? null,
                            'instructor' => $career['instructor'] ?? null,
                            'command' => $career['command'] ?? null,
                            'organization_affiliation' => $career['organization_affiliation'] ?? null,
                            'civil_service_eligibility' => $career['civil_service_eligibility'] ?? null,
                            'compulsory_retirement' => $career['compulsory_retirement'] ?? null,

                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if (!empty($validated['civilianCommendations'])) {

                    $civilianInsert = [];

                    foreach ($validated['civilianCommendations'] as $item) {

                        if (!empty($item['type']) || !empty($item['authority'])) {
                            $civilianInsert[] = [
                                'serial_number' => $serialNumber,
                                'applicant_id' => $userId,

                                'type' => $item['type'] ?? null,
                                'authority' => $item['authority'] ?? null,

                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    if (!empty($civilianInsert)) {
                        DB::table('reinlist_civilian_awards')->insert($civilianInsert);
                    }
                }

                if (!empty($careersInsert)) {
                    DB::table('reinlist_careers')->insert($careersInsert);
                }
            }

            // =========================
            // CG AWARDS / DECORATIONS
            // =========================
            if (!empty($validated['cgAwardsDecorations'])) {

                $cgAwards = [];

                foreach ($validated['cgAwardsDecorations'] as $item) {

                    if (!empty($item['type']) || !empty($item['authority'])) {
                        $cgAwards[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,
                            'type' => $item['type'] ?? null,
                            'authority' => $item['authority'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (!empty($cgAwards)) {
                    DB::table('reinlist_cg_awards_decorations')->insert($cgAwards);
                }
            }

            // =========================
            // CG LETTERS
            // =========================
            if (!empty($validated['cgLetters'])) {

                $cgLetters = [];

                foreach ($validated['cgLetters'] as $item) {

                    if (!empty($item['type']) || !empty($item['authority'])) {
                        $cgLetters[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,
                            'type' => $item['type'] ?? null,
                            'authority' => $item['authority'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (!empty($cgLetters)) {
                    DB::table('reinlist_cg_letters')->insert($cgLetters);
                }
            }

            // =========================
            // CG APPRECIATIONS (NEW)
            // =========================
            if (!empty($validated['cgAppreciations'])) {

                $cgAppreciations = [];

                \Log::info('CG Appreciations Payload Received', [
                    'count' => count($validated['cgAppreciations']),
                    'data' => $validated['cgAppreciations']
                ]);

                foreach ($validated['cgAppreciations'] as $index => $item) {

                    \Log::debug("CG Appreciations Item #{$index}", $item);

                    if (!empty($item['type']) || !empty($item['authority'])) {
                        $cgAppreciations[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,
                            'type' => $item['type'] ?? null,
                            'authority' => $item['authority'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                \Log::info('CG Appreciations Prepared for Insert', [
                    'count' => count($cgAppreciations),
                    'data' => $cgAppreciations
                ]);

                if (!empty($cgAppreciations)) {
                    DB::table('reinlist_cg_appreciations')->insert($cgAppreciations);

                    \Log::info('CG Appreciations Inserted Successfully');
                } else {
                    \Log::warning('CG Appreciations NOT inserted (empty array)');
                }
            }

            // =========================
            // CG PLAQUES (NEW)
            // =========================
            if (!empty($validated['cgPlaques'])) {

                $cgPlaques = [];

                \Log::info('CG Plaques Payload Received', [
                    'count' => count($validated['cgPlaques']),
                    'data' => $validated['cgPlaques']
                ]);

                foreach ($validated['cgPlaques'] as $index => $item) {

                    \Log::debug("CG Plaques Item #{$index}", $item);

                    if (!empty($item['type']) || !empty($item['authority'])) {
                        $cgPlaques[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,
                            'type' => $item['type'] ?? null,
                            'authority' => $item['authority'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                \Log::info('CG Plaques Prepared for Insert', [
                    'count' => count($cgPlaques),
                    'data' => $cgPlaques
                ]);

                if (!empty($cgPlaques)) {
                    DB::table('reinlist_cg_plaques')->insert($cgPlaques);

                    \Log::info('CG Plaques Inserted Successfully');
                } else {
                    \Log::warning('CG Plaques NOT inserted (empty array)');
                }
            }

            // =========================
            // CG LETTERS (NEW)
            // =========================
            if (!empty($validated['otherLetters'])) {

                $cgPlaques = [];

                \Log::info('Other Letters Payload Received', [
                    'count' => count($validated['otherLetters']),
                    'data' => $validated['otherLetters']
                ]);

                foreach ($validated['otherLetters'] as $index => $item) {

                    \Log::debug("Other Letters Item #{$index}", $item);

                    if (!empty($item['type']) || !empty($item['authority'])) {
                        $cgPlaques[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,
                            'type' => $item['type'] ?? null,
                            'authority' => $item['authority'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                \Log::info('Other Letters Prepared for Insert', [
                    'count' => count($cgPlaques),
                    'data' => $cgPlaques
                ]);

                if (!empty($cgPlaques)) {
                    DB::table('reinlist_other_letters')->insert($cgPlaques);

                    \Log::info('Other Letters Inserted Successfully');
                } else {
                    \Log::warning('Other Letters NOT inserted (empty array)');
                }
            }
            // =========================
            // OTHER APPRECIATIONS (NEW)
            // =========================
            if (!empty($validated['otherAppreciations'])) {

                $cgAppreciations = [];

                \Log::info('Other Appreciations Payload Received', [
                    'count' => count($validated['otherAppreciations']),
                    'data' => $validated['otherAppreciations']
                ]);

                foreach ($validated['otherAppreciations'] as $index => $item) {

                    \Log::debug("Other Appreciations Item #{$index}", $item);

                    if (!empty($item['type']) || !empty($item['authority'])) {
                        $cgAppreciations[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,
                            'type' => $item['type'] ?? null,
                            'authority' => $item['authority'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                \Log::info('Other Appreciations Prepared for Insert', [
                    'count' => count($cgAppreciations),
                    'data' => $cgAppreciations
                ]);

                if (!empty($cgAppreciations)) {
                    DB::table('reinlist_other_appreciations')->insert($cgAppreciations);

                    \Log::info('Other Appreciations Inserted Successfully');
                } else {
                    \Log::warning('Other Appreciations NOT inserted (empty array)');
                }
            }
            // =========================
            // OTHER PLAQUES (NEW)
            // =========================
            if (!empty($validated['otherPlaques'])) {

                $cgOtherPlaques = [];

                \Log::info('Other Plaques Payload Received', [
                    'count' => count($validated['otherPlaques']),
                    'data' => $validated['otherPlaques']
                ]);

                foreach ($validated['otherPlaques'] as $index => $item) {

                    \Log::debug("Other Plaques Item #{$index}", $item);

                    if (!empty($item['type']) || !empty($item['authority'])) {
                        $cgOtherPlaques[] = [
                            'serial_number' => $serialNumber,
                            'applicant_id' => $userId,
                            'type' => $item['type'] ?? null,
                            'authority' => $item['authority'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                \Log::info('Other Plaques Prepared for Insert', [
                    'count' => count($cgOtherPlaques),
                    'data' => $cgOtherPlaques
                ]);

                if (!empty($cgOtherPlaques)) {
                    DB::table('reinlist_other_plaques')->insert($cgOtherPlaques);

                    \Log::info('Other Plaques Inserted Successfully');
                } else {
                    \Log::warning('Other Plaques NOT inserted (empty array)');
                }
            }
            // =========================
            // SESSION BACKUP
            // =========================
            session(['step6' => $validated]);

            return response()->json([
                'status' => 'success',
                'message' => 'Step 6 data saved successfully.'
            ]);
        } catch (\Exception $e) {

            \Log::error('Step 6 Save Error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save Step 6.'
            ], 500);
        }
    }
}
