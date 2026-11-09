<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UnitDocumentController extends Controller
{
    /** rejected_documents.doc_type for a returned residence pin */
    public const LOCATION_LABEL = 'Residence Location';

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

        // The record being decided: the one owned by the approved account with
        // this serial. Own-unit checkers only for their unit (users.mother_unit);
        // M1 checkers for all units. Every update below targets this row only,
        // never every row that happens to carry the serial number.
        $owned = DB::table('personnel_data as pd')
            ->join('users as u', 'u.serial_number', '=', 'pd.serial_number')
            ->where('pd.serial_number', $request->serial_number)
            ->where(fn ($q) => \App\Models\UnitUser::recordOwner($q));
        if (session('unit_role') !== \App\Models\UnitUser::ROLE_M1) {
            $owned->where('u.mother_unit', session('unit_name'));
        }
        $rowId = $owned->orderByDesc('pd.id')->value('pd.id');

        abort_unless($rowId, 403, 'This applicant is not in your unit.');

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
                    ->where('id', $rowId)
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

                    // the residence pin (latitude/longitude) is reviewed like a document:
                    // returning it clears the pin so the member sets it again
                    if ($column === 'location' && is_array($doc) && isset($doc['checked'])) {
                        $pin = DB::table('personnel_data')->where('id', $rowId)->first(['latitude', 'longitude']);
                        DB::table('personnel_data')->where('id', $rowId)->update(['latitude' => null, 'longitude' => null, 'updated_at' => now()]);
                        DB::table('rejected_documents')->insert([
                            'serial_number' => $serial,
                            'doc_type'      => self::LOCATION_LABEL,
                            'file_path'     => null,
                            'comment'       => is_string($doc['comment'] ?? null) ? mb_substr($doc['comment'], 0, 2000) : null,
                            'status'        => 'rejected',
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                        Log::warning('Residence location returned', ['had_pin' => $pin && $pin->latitude !== null]);
                        continue;
                    }

                    if (!is_string($column) || !isset($docMap[$column]) || !is_array($doc)) continue;

                    $label = $docMap[$column];

                    $isChecked = isset($doc['checked']);
                    $comment   = $doc['comment'] ?? null;

                    $filePath = DB::table('personnel_data')
                        ->where('id', $rowId)
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
                            ->where('id', $rowId)
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
                    ->where('id', $rowId)
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

            $name = DB::table('personnel_data as pd')->join('users as u', 'u.serial_number', '=', 'pd.serial_number')
                ->where('pd.id', $rowId)->where(fn ($q) => \App\Models\UnitUser::recordOwner($q))->value('u.name') ?? $serial;
            $returned = $decision === 'rejected' ? count(array_filter($docs, fn ($d) => is_array($d) && isset($d['checked']))) : 0;

            return back()
                ->with('success', $decision === 'approved'
                    ? "Approved: the required documents of {$name}."
                    : ($returned
                        ? "Returned {$returned} " . ($returned === 1 ? 'item' : 'items') . " to {$name}. They will see your comments on their dashboard."
                        : "Marked the record of {$name} as returned."))
                ->with('reviewed', $serial);
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

    /**
     * 201 file decision (owner decision 2026-11-09): approve the record, or return
     * the ticked sections with a comment. Same rules as review(): own-unit
     * checkers only for their unit's members, M1 for all; one record (the
     * owner's) changes. Returned sections are cleared and noted in
     * rejected_documents as "201: <label>".
     */
    public function review201(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|max:100',
            'decision'      => 'required|in:approved,rejected',
            'docs'          => 'nullable|array',
        ]);
        abort_unless(\App\Support\Review201::hasStatus(), 409, 'Run the database update (deploy with -Migrate) first.');

        $isM1 = session('unit_role') === \App\Models\UnitUser::ROLE_M1;
        $row = \App\Support\Review201::queueFor(session('unit_name'), $isM1)
            ->where('p201.serial_number', $request->serial_number)
            ->orderByDesc('p201.id')
            ->first(['p201.*', 'u.name as applicant_name']);
        abort_unless($row, 403, 'This member is not in your unit.');

        $serial = $row->serial_number;
        $returned = [];
        DB::transaction(function () use ($request, $row, $serial, &$returned) {
            if ($request->decision === 'approved') {
                DB::table('personnel_201_files')->where('id', $row->id)->update(['status' => 'approved']);
                return;
            }
            $clear = [];
            foreach ((array) $request->input('docs', []) as $field => $doc) {
                if (!is_string($field) || !isset(\App\Support\Review201::SECTIONS[$field]) || !is_array($doc) || !isset($doc['checked'])) {
                    continue;
                }
                if (empty($row->$field)) {
                    continue; // nothing uploaded in that section
                }
                $clear[$field] = null;
                $returned[] = $field;
                DB::table('rejected_documents')->insert(\App\Support\TableColumns::fit('rejected_documents', [
                    'serial_number' => $serial,
                    'doc_type'      => \App\Support\Review201::docType($field),
                    'file_path'     => $row->$field,
                    'comment'       => is_string($doc['comment'] ?? null) ? mb_substr($doc['comment'], 0, 2000) : null,
                    'status'        => 'rejected',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]));
            }
            DB::table('personnel_201_files')->where('id', $row->id)->update($clear + ['status' => 'rejected']);
        });

        Log::channel('audit')->info('unit.201_review', [
            'unit_user_id' => session('unit_user_id'),
            'unit_role' => session('unit_role'),
            'serial_number' => $serial,
            'decision' => $request->decision,
            'returned' => $returned,
            'ip' => $request->ip(),
        ]);

        $name = $row->applicant_name ?: $serial;
        $n = count($returned);

        return back()
            ->with('success', $request->decision === 'approved'
                ? "Approved: the 201 file of {$name}."
                : ($n ? "Returned {$n} " . ($n === 1 ? 'section' : 'sections') . " of the 201 file to {$name}. They will see your comments on their 201 page."
                    : "Marked the 201 file of {$name} as returned."))
            ->with('reviewed', $serial);
    }
}
