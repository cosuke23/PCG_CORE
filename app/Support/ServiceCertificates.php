<?php

namespace App\Support;

use App\Models\Personnel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Service certificates on request (owner request 2026-11-20). Members request at
 * /certificates; admins with certificates.issue issue or decline within their
 * OrgScope at /admin/certificates; issued ones print with a QR code to the public
 * check /verify/certificate/{code} (name, rank, kind, dates; no serial or birth date).
 */
class ServiceCertificates
{
    public const KINDS = [
        'service' => 'Certificate of service',
        'assignment' => 'Certificate of present assignment',
    ];

    public const STATUSES = ['pending' => 'Waiting for HR', 'issued' => 'Issued', 'declined' => 'Declined', 'revoked' => 'Revoked'];

    public static function ready(): bool
    {
        return Schema::hasTable('service_certificates');
    }

    /** What the certificate states, frozen when it is issued. */
    public static function snapshot(object $user): array
    {
        $p = Schema::hasTable('personnel') ? Personnel::query()->where(fn ($q) => $q->where('user_id', $user->id)
            ->orWhere(DB::raw('UPPER(TRIM(serial_number))'), Personnel::normalizeSerial($user->serial_number)))
            ->with(['rank', 'currentAssignment.orgUnit'])->first() : null;
        $assign = $p?->currentAssignment;
        return [
            'name' => $p?->full_name ?: (string) $user->name,
            'rank' => $p?->rank?->code ?: (string) ($user->rank ?? ''),
            'serial_number' => (string) $user->serial_number,
            'unit' => $assign?->orgUnit?->name ?: (string) ($user->mother_unit ?? ''),
            'unit_code' => $assign?->orgUnit?->code ?: (string) ($user->mother_unit ?? ''),
            'position' => (string) ($assign?->position ?? ''),
            'assigned_since' => $assign?->start_date?->toDateString(),
            'entered_service' => $p?->date_entered_service ? Carbon::parse($p->date_entered_service)->toDateString() : ($user->date_of_appointment ?? null),
            'status' => $p?->service_status ?: 'active',
        ];
    }

    public static function newCode(): string
    {
        do {
            $code = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
            $code = strtr($code, ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'V']);   // no look-alike characters
        } while (DB::table('service_certificates')->where('code', $code)->exists());
        return $code;
    }

    public static function issue(object $cert, object $user, array $by, ?string $note): void
    {
        DB::table('service_certificates')->where('id', $cert->id)->update([
            'status' => 'issued', 'code' => self::newCode(), 'snapshot' => json_encode(self::snapshot($user)),
            'decision_note' => $note, 'decided_by_name' => $by['name'] ?? null, 'decided_by_id' => $by['id'] ?? null,
            'decided_at' => now(), 'updated_at' => now(),
        ]);
        MemberNotifications::send((int) $cert->user_id, 'certificate.issued', 'Your ' . strtolower(self::KINDS[$cert->kind]) . ' is ready',
            'Print it from the Certificates page.', '/certificates');
    }

    public static function decline(object $cert, array $by, string $note): void
    {
        DB::table('service_certificates')->where('id', $cert->id)->update([
            'status' => 'declined', 'decision_note' => $note, 'decided_by_name' => $by['name'] ?? null, 'decided_by_id' => $by['id'] ?? null,
            'decided_at' => now(), 'updated_at' => now(),
        ]);
        MemberNotifications::send((int) $cert->user_id, 'certificate.declined', 'Your ' . strtolower(self::KINDS[$cert->kind]) . ' request was declined', $note, '/certificates');
    }

    public static function printed(object $cert, string $who): void
    {
        Log::channel('audit')->info('service_certificate.printed', ['certificate_id' => $cert->id, 'user_id' => $cert->user_id, 'by' => $who]);
    }
}
