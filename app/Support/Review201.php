<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 201 file (personnel_201_files): what the member may change, by review state
 * (owner decisions 2026-11-09, same rules as the required documents, see
 * App\Support\DocumentReview):
 *
 *  - Waiting for the checker (status Pending, or no status on older rows):
 *    nothing can be uploaded; the Upload button is locked.
 *  - After the review (approved or rejected/returned): sections with a file stay
 *    locked; only the sections the checker returned (the review clears them)
 *    and sections never filled open again. A new upload goes back to Pending.
 *
 * Reviewers: own-unit checkers for members of their unit, M1 checkers for all.
 * Returned sections are rejected_documents rows with doc_type "201: <label>".
 */
class Review201
{
    /** column => label, in the A–K order members see */
    public const SECTIONS = [
        'soi' => 'Latest SOI', 'appointment' => 'Appointment', 'leave' => 'Leave balance', 'awards' => 'Awards',
        'disciplinary' => 'Disciplinary action', 'performance' => 'Performance record', 'legal' => 'Legal documents',
        'academic' => 'Academic records', 'financial' => 'Financial record', 'retirement' => 'Retirement record',
        'body_picture' => 'Whole-body picture',
    ];

    public static function docType(string $field): string
    {
        return '201: ' . self::SECTIONS[$field];
    }

    /** The member's 201 record: owned by the account, or an older row with the serial and no owner. */
    public static function rowFor(object $user): ?object
    {
        return DB::table('personnel_201_files')->where('personnel_id', $user->id)->orderByDesc('id')->first()
            ?? DB::table('personnel_201_files')->where('serial_number', $user->serial_number)
                ->whereNull('personnel_id')->orderByDesc('id')->first();
    }

    /**
     * Join condition between users (u) and personnel_201_files (p201): the
     * account that owns the 201 record (older rows without an owner: approved
     * accounts only), like UnitUser::recordOwner for the required documents.
     */
    public static function owner($query)
    {
        return $query->whereColumn('p201.personnel_id', 'u.id')
            ->orWhere(fn ($q) => $q->whereNull('p201.personnel_id')->whereRaw('LOWER(TRIM(u.status)) = ?', ['approved']));
    }

    /** 201 records a checker may review: own-unit checkers their unit's members, M1 all. */
    public static function queueFor(?string $unit, bool $allUnits)
    {
        $q = DB::table('personnel_201_files as p201')
            ->join('users as u', 'u.serial_number', '=', 'p201.serial_number')
            ->where(fn ($q) => self::owner($q));
        if (!$allUnits) {
            $q->where('u.mother_unit', $unit);
        }

        return $q;
    }

    public static function hasStatus(): bool
    {
        return Schema::hasColumn('personnel_201_files', 'status');
    }

    public static function awaitingReview(?object $row): bool
    {
        if (!$row || !self::hasStatus()) {
            return false; // before the migration: no review step, as before
        }
        $status = strtolower(trim((string) ($row->status ?? '')));

        return $status === '' || $status === 'pending';
    }

    /** @return list<string> sections the member may not upload right now */
    public static function lockedFields(?object $row): array
    {
        if (!$row || !self::hasStatus()) {
            return [];
        }
        if (self::awaitingReview($row)) {
            return array_keys(self::SECTIONS);
        }

        return array_values(array_filter(array_keys(self::SECTIONS), fn ($f) => !empty($row->$f ?? null)));
    }

    public static function canSave(?object $row): bool
    {
        return count(self::lockedFields($row)) < count(self::SECTIONS);
    }

    /** @return array<string, string> section => checker's comment, for open returns */
    public static function returned(string $serial): array
    {
        if (!Schema::hasTable('rejected_documents')) {
            return [];
        }
        $byType = [];
        foreach (array_keys(self::SECTIONS) as $f) {
            $byType[self::docType($f)] = $f;
        }
        $out = [];
        foreach (DB::table('rejected_documents')->where('serial_number', $serial)
            ->whereRaw('LOWER(status) = ?', ['rejected'])->whereIn('doc_type', array_keys($byType))->orderBy('id')->get() as $r) {
            $out[$byType[$r->doc_type]] = (string) ($r->comment ?? '');
        }

        return $out;
    }
}
