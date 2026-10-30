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

        try {

            $serial   = $request->serial_number;
            $decision = $request->decision;
            $docs     = $request->docs ?? [];

            $docMap = [
                0 => ['SOI', 'soi'],
                1 => ['NBI Clearance', 'nbi_clearance'],
                2 => ['Ombudsman', 'ombudsman_clearance'],
                3 => ['Sandiganbayan', 'sandiganbayan_clearance'],
                4 => ['OFR', 'ofr'],
                5 => ['Medical', 'medical_clearance'],
                6 => ['PFT', 'pft_clearance'],
                7 => ['Whole Body', 'whole_body_picture'],
                8 => ['2x2 Photo', 'photo_2x2'],
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

                foreach ($docs as $index => $doc) {

                    if (!isset($docMap[$index])) continue;

                    [$label, $column] = $docMap[$index];

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
