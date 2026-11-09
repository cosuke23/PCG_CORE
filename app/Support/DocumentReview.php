<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Required documents (personnel_data): what the member may change, by review state
 * (owner decisions 2026-11-09):
 *
 *  - Waiting for the unit checker (status Pending, or no status on older rows,
 *    as on the checker's list): nothing can be changed. Every box, every date,
 *    the residence pin and the "Save and upload" button are locked.
 *  - After the checker's decision (approved or rejected/returned): a document
 *    that has a file stays locked; only what the checker returned opens again
 *    (the review clears that file, or the pin, so its box is empty). Boxes that
 *    were never filled are open, so missing documents can still be uploaded.
 *    A clearance whose "valid until" date has passed opens again for renewal.
 *  - Any new upload or pin sends the record back to Pending (locked again).
 */
class DocumentReview
{
    public const FILE_FIELDS = ['soi', 'ofr', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance',
        'medical_clearance', 'pft_clearance', 'photo_2x2', 'whole_body_picture', 'residential_sketch'];

    public const EXPIRY_FIELDS = ['nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance'];

    /** rejected_documents.doc_type written by the unit review, per column */
    public const DOC_TYPES = ['soi' => 'SOI', 'nbi_clearance' => 'NBI Clearance', 'ombudsman_clearance' => 'Ombudsman',
        'sandiganbayan_clearance' => 'Sandiganbayan', 'ofr' => 'OFR', 'medical_clearance' => 'Medical', 'pft_clearance' => 'PFT',
        'whole_body_picture' => 'Whole Body', 'photo_2x2' => '2x2 Photo', 'residential_sketch' => 'Residential Sketch',
        'location' => 'Residence Location'];

    /**
     * Items the checker returned and the member has not answered yet.
     * @return array<string, string> column (or "location") => checker's comment
     */
    public static function returned(string $serial): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('rejected_documents')) {
            return [];
        }
        $byType = array_flip(self::DOC_TYPES);
        $out = [];
        foreach (\Illuminate\Support\Facades\DB::table('rejected_documents')->where('serial_number', $serial)
            ->whereRaw('LOWER(status) = ?', ['rejected'])->orderBy('id')->get() as $r) {
            if (isset($byType[$r->doc_type])) {
                $out[$byType[$r->doc_type]] = (string) ($r->comment ?? '');
            }
        }

        return $out;
    }

    /**
     * The member's required-documents record: the row owned by the account, or an
     * older row with the account's serial number and no owner recorded. The page
     * and the save use this same row, so both judge the locks the same way.
     */
    public static function rowFor(object $user): ?object
    {
        return \Illuminate\Support\Facades\DB::table('personnel_data')->where('personnel_id', $user->id)->orderByDesc('id')->first()
            ?? \Illuminate\Support\Facades\DB::table('personnel_data')->where('serial_number', $user->serial_number)
                ->whereNull('personnel_id')->orderByDesc('id')->first();
    }

    /** True while the unit checker has not decided the record yet. */
    public static function awaitingReview(?object $row): bool
    {
        if (!$row) {
            return false;
        }
        $status = strtolower(trim((string) ($row->status ?? '')));

        return $status === '' || $status === 'pending';
    }

    /** A clearance past its "valid until" date (Asia/Manila): open for renewal after review. */
    public static function expired(?object $row, string $field): bool
    {
        if (!$row || !in_array($field, self::EXPIRY_FIELDS, true) || empty($row->{$field . '_expiration'})) {
            return false;
        }

        return Carbon::parse($row->{$field . '_expiration'}, 'Asia/Manila')->startOfDay()->lt(Carbon::now('Asia/Manila')->startOfDay());
    }

    /** @return list<string> file columns the member may not upload or re-date right now */
    public static function lockedFields(?object $row): array
    {
        if (!$row) {
            return [];
        }
        if (self::awaitingReview($row)) {
            return self::FILE_FIELDS;
        }

        return array_values(array_filter(self::FILE_FIELDS,
            fn ($f) => !empty($row->$f ?? null) && !self::expired($row, $f)));
    }

    /** The residence pin: locked while waiting, and once set (until the checker returns it). */
    public static function pinLocked(?object $row): bool
    {
        if (!$row) {
            return false;
        }

        return self::awaitingReview($row) || (is_numeric($row->latitude ?? null) && is_numeric($row->longitude ?? null));
    }

    /** Whether the page has anything the member may send. */
    public static function canSave(?object $row): bool
    {
        return count(self::lockedFields($row)) < count(self::FILE_FIELDS) || !self::pinLocked($row);
    }
}
