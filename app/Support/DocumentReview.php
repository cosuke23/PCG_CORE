<?php

namespace App\Support;

/**
 * Required documents (personnel_data): which ones the member may replace.
 *
 * Once a file is uploaded it waits for the unit checker. While the record is
 * waiting (status Pending, or no status yet on older rows), every document that
 * already has a file is locked: the member cannot replace it or change its
 * "valid until" date. The lock ends when the checker acts: a rejected document
 * is cleared by the review (so it can be uploaded again), and after an approval
 * any document can be replaced, for example a renewed clearance, which sends
 * the record back for review. Empty boxes are never locked.
 */
class DocumentReview
{
    public const FILE_FIELDS = ['soi', 'ofr', 'nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance',
        'medical_clearance', 'pft_clearance', 'photo_2x2', 'whole_body_picture', 'residential_sketch'];

    public const EXPIRY_FIELDS = ['nbi_clearance', 'ombudsman_clearance', 'sandiganbayan_clearance'];

    /** True while the unit checker has not decided the record yet. */
    public static function awaitingReview(?object $row): bool
    {
        if (!$row) {
            return false;
        }
        $status = strtolower(trim((string) ($row->status ?? '')));

        return $status === '' || $status === 'pending';
    }

    /** @return list<string> file columns the member may not replace right now */
    public static function lockedFields(?object $row): array
    {
        if (!self::awaitingReview($row)) {
            return [];
        }

        return array_values(array_filter(self::FILE_FIELDS, fn ($f) => !empty($row->$f ?? null)));
    }
}
