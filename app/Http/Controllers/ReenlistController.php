<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReenlistController extends Controller
{
    public function store(Request $request)
    {
        // Records are keyed by serial number; only allow writing your own.
        $request->merge([
            'basic' => array_merge((array) $request->input('basic', []), [
                'serial_number' => $request->user()->serial_number,
            ]),
        ]);

        Log::info('Reenlistment Store Started', ['serial_number' => $request->user()->serial_number]);

        DB::beginTransaction();

        try {
            // 1. Validate minimal required fields
            $request->validate([
                'basic.serial_number' => 'required|string|max:50',
                'basic.last_name' => 'required|string|max:100',
                'basic.first_name' => 'required|string|max:100',
            ]);

            $serial = $request->input('basic.serial_number');
            Log::info("Processing Serial Number: $serial");

            // ================= STEP 1: Basic Info =================
            DB::table('reinlist_basic_info')->updateOrInsert(
                ['serial_number' => $serial],
                [
                    'last_name'      => $request->input('basic.last_name'),
                    'first_name'     => $request->input('basic.first_name'),
                    'middle_name'    => $request->input('basic.middle_name'),
                    'extension_name' => $request->input('basic.extension_name'),
                    'rank'           => $request->input('basic.rank'),
                    'birthday'       => $request->input('basic.birthday'),
                    'birth_place'    => $request->input('basic.birth_place'),
                    'updated_at'     => now(),
                    'created_at'     => now(),
                ]
            );
            Log::info('Step 1: Basic Info Saved');

            // ================= STEP 2: Personal Info =================
            DB::table('reinlist_personal_info')->updateOrInsert(
                ['serial_number' => $serial],
                array_merge($request->input('personal', []), [
                    'serial_number' => $serial
                ])
            );
            Log::info('Step 2: Personal Info Saved');

            // ================= STEP 3: Dependents =================
            DB::table('reinlist_dependents')->where('serial_number', $serial)->delete();
            $dependents = $request->input('dependents', []);
            foreach ($dependents as $d) {
                DB::table('reinlist_dependents')->insert([
                    'serial_number' => $serial,
                    'name'          => $d['name'] ?? null,
                    'relationship'  => $d['relationship'] ?? null,
                    'birthdate'     => $d['bday'] ?? null,
                ]);
            }
            Log::info('Step 3: Dependents Processed', ['count' => count($dependents)]);

            // ================= STEP 4: Education & Training =================

            // Academic
            DB::table('reinlist_academic')->where('serial_number', $serial)->delete();
            foreach ($request->input('academic', []) as $a) {
                DB::table('reinlist_academic')->insert([
                    'serial_number' => $serial,
                    'course'        => $a['course'] ?? null,
                    'school'        => $a['school'] ?? null,
                    'period'        => $a['period'] ?? null,
                    'remarks'       => $a['remarks'] ?? null,
                ]);
            }

            // Vocational
            DB::table('reinlist_vocational')->where('serial_number', $serial)->delete();
            foreach ($request->input('vocational', []) as $v) {
                DB::table('reinlist_vocational')->insert([
                    'serial_number' => $serial,
                    'course'        => $v['course'] ?? null,
                    'authority'     => $v['authority'] ?? null,
                    'school'        => $v['school'] ?? null,
                    'period'        => $v['period'] ?? null,
                ]);
            }

            // Trainings
            DB::table('reinlist_trainings')->where('serial_number', $serial)->delete();
            foreach ($request->input('trainings', []) as $t) {
                DB::table('reinlist_trainings')->insert([
                    'serial_number' => $serial,
                    'course'        => $t['course'] ?? null,
                    'school'        => $t['school'] ?? null,
                    'period'        => $t['period'] ?? null,
                    'remarks'       => $t['remarks'] ?? null,
                ]);
            }
            Log::info('Step 4: Academic/Trainings Processed');

            // ================= STEP 5: Service History =================

            // Appointments
            DB::table('reinlist_appointments')->where('serial_number', $serial)->delete();
            foreach ($request->input('appointments', []) as $a) {
                DB::table('reinlist_appointments')->insert([
                    'serial_number' => $serial,
                    'description'   => $a['description'] ?? null,
                    'date'          => $a['date'] ?? null,
                    'authority'     => $a['authority'] ?? null,
                ]);
            }

            // Duties
            DB::table('reinlist_duties')->where('serial_number', $serial)->delete();
            foreach ($request->input('duties', []) as $d) {
                DB::table('reinlist_duties')->insert([
                    'serial_number' => $serial,
                    'unit'          => $d['unit'] ?? null,
                    'position'      => $d['position'] ?? null,
                    'period'        => $d['period'] ?? null,
                    'authority'     => $d['authority'] ?? null,
                ]);
            }
            Log::info('Step 5: Appointments/Duties Processed');

            // ================= STEP 6: Careers & Profile =================
            DB::table('reinlist_careers')->where('serial_number', $serial)->delete();
            foreach ($request->input('careers', []) as $c) {
                DB::table('reinlist_careers')->insert([
                    'serial_number' => $serial,
                    'student'       => $c['student'] ?? null,
                    'sea'           => $c['sea'] ?? null,
                ]);
            }

            DB::table('reinlist_profile')->updateOrInsert(
                ['serial_number' => $serial],
                [
                    'organization' => $request->input('organization'),
                    'eligibility'  => $request->input('eligibility'),
                    'retirement'   => $request->input('retirement'),
                    'updated_at'   => now(),
                ]
            );
            Log::info('Step 6: Career Profile Processed');

            // If we reached here, everything is fine
            DB::commit();
            Log::info('Transaction Committed Successfully for Serial: ' . $serial);

            return response()->json([
                'success' => true,
                'message' => 'Reenlistment form saved successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            // Log the exact error and line number
            Log::error('Reenlistment Save Failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving data',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
