<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UnitDocumentController extends Controller
{
    public function review(Request $request)
    {
        Log::info('=== UNIT DOCUMENT REVIEW START ===', [
            'serial_number' => $request->serial_number,
            'decision' => $request->decision,
            'unit_user_id' => session('unit_user_id'),
        ]);

        $request->validate([
            'serial_number' => 'required|string',
            'decision'      => 'required|in:approved,rejected',
            'docs'          => 'nullable|array',
        ]);

        // Unit checkers may only decide on personnel of their own unit
        // (the unit dashboard lists users.mother_unit = their unit); M1
        // checkers review all units.
        if (session('unit_role') !== \App\Models\UnitUser::ROLE_M1) {
            // The account that owns the documents must be in this unit (not
            // just any account carrying that serial number).
            $inUnit = DB::table('personnel_data as pd')
                ->join('users as u', 'u.serial_number', '=', 'pd.serial_number')
                ->where('pd.serial_number', $request->serial_number)
                ->where('u.mother_unit', session('unit_name'))
                ->where(fn ($q) => \App\Models\UnitUser::recordOwner($q))
                ->exists();

            abort_unless($inUnit, 403, 'This applicant is not in your unit.');
        }

        try {

            $serial   = $request->serial_number;
            $decision = $request->decision;
            $docs     = $request->docs ?? [];

            // Keyed by personnel_data column; the review modals post
            // docs[<column>][checked|comment], so a label always matches its file.
            $docMap = [
                'soi'                     => 'SOI',
                'nbi_clearance'           => 'NBI Clearance',
                'ombudsman_clearance'     => 'Ombudsman',
                'sandiganbayan_clearance' => 'Sandiganbayan',
                'ofr'                     => 'OFR',
                'medical_clearance'       => 'Medical',
                'pft_clearance'           => 'PFT',
                'whole_body_picture'      => 'Whole Body',
                'photo_2x2'               => '2x2 Photo',
                'residential_sketch'      => 'Residential Sketch',
            ];

            Log::info('Decision Flow Started', [
                'serial_number' => $serial,
                'decision' => $decision,
            ]);

            /*
        =====================================================
        🔥 APPROVED FLOW
        =====================================================
        */
            if ($decision === 'approved') {

                Log::info('🟢 APPROVED FLOW START');

                DB::table('personnel_data')
                    ->where('serial_number', $serial)
                    ->update([
                        'status' => 'approved',
                        'updated_at' => now()
                    ]);

                Log::info('🟢 STATUS UPDATED TO APPROVED');
            }

            /*
        =====================================================
        🔥 REJECTED FLOW (SAVE REJECTED DOCS)
        =====================================================
        */
            if ($decision === 'rejected') {

                Log::warning('🔴 REJECTED FLOW START');

                foreach ($docs as $column => $doc) {

                    if (!is_string($column) || !isset($docMap[$column]) || !is_array($doc)) continue;

                    $label = $docMap[$column];

                    $isChecked = isset($doc['checked']);
                    $comment   = $doc['comment'] ?? null;

                    $filePath = DB::table('personnel_data')
                        ->where('serial_number', $serial)
                        ->value($column);

                    Log::info('Processing Rejected Doc', [
                        'label' => $label,
                        'checked' => $isChecked,
                        'comment' => $comment,
                        'file' => $filePath,
                    ]);

                    /*
                =====================================================
                ✔ IF CHECKED → REMOVE FILE
                =====================================================
                */
                    if ($isChecked) {

                        DB::table('personnel_data')
                            ->where('serial_number', $serial)
                            ->update([
                                $column => null,
                                'updated_at' => now()
                            ]);

                        Log::warning("🗑 FILE REMOVED: {$label}");
                    }

                    /*
                =====================================================
                ✔ SAVE REJECTED DOC RECORD
                =====================================================
                */
                    if ($isChecked) {

                        DB::table('rejected_documents')->insert([
                            'serial_number' => $serial,
                            'doc_type'      => $label,
                            'file_path'     => $filePath,
                            'comment'       => $comment,
                            'status'        => 'rejected',
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);

                        Log::warning("📌 SAVED TO REJECTED TABLE: {$label}");
                    }
                }

                DB::table('personnel_data')
                    ->where('serial_number', $serial)
                    ->update([
                        'status' => 'rejected',
                        'updated_at' => now()
                    ]);

                Log::warning('🔴 STATUS UPDATED TO REJECTED');
            }

            /*
        =====================================================
        ✔ FINAL LOG
        =====================================================
        */
            Log::info('=== REVIEW COMPLETED SUCCESSFULLY ===', [
                'serial_number' => $serial,
                'decision' => $decision
            ]);
            Log::channel('audit')->info('unit.document_review', [
                'unit_user_id' => session('unit_user_id'),
                'unit_role' => session('unit_role'),
                'serial_number' => $serial,
                'decision' => $decision,
                'rejected' => $decision === 'rejected'
                    ? array_keys(array_filter($docs, fn ($d) => is_array($d) && isset($d['checked'])))
                    : [],
                'ip' => request()->ip(),
            ]);

            return back()->with('success', 'Review completed.');
        } catch (\Exception $e) {

            Log::error('REVIEW FAILED', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Something went wrong.');
        }
    }
}
