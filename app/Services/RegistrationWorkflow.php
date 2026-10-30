<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\PersonnelRegistration;
use App\Models\RegistrationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registration workflow (owner decision D6: one approval, no self-approval):
 *
 *   draft --submit--> submitted --approve--> approved (personnel record created)
 *     ^                 |  |  \--reject---> rejected
 *     |                 |  \--endorse (optional review, stays submitted)
 *   returned <--return--/
 *
 * Every transition is written to registration_events. Nothing is approved
 * automatically; the approver must not be the person who created or
 * submitted the registration.
 */
class RegistrationWorkflow
{
    public function __construct(private PersonnelAssignments $assignments)
    {
    }

    public function record(PersonnelRegistration $reg, string $action, ?string $from, ?string $to, ?string $remarks = null): void
    {
        RegistrationEvent::create([
            'registration_id' => $reg->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'admin_user_id' => session('admin_user_id'),
            'admin_email' => session('admin_email'),
        ]);
    }

    /** Problems that block submission/approval: an existing record or another open registration. */
    public function duplicateErrors(PersonnelRegistration $reg): array
    {
        $serial = Personnel::normalizeSerial($reg->serial_number);
        $errors = [];

        if (Personnel::whereRaw('UPPER(TRIM(serial_number)) = ?', [$serial])->exists()) {
            $errors['serial_number'] = 'A personnel record with this serial number already exists.';
        } elseif (PersonnelRegistration::whereRaw('UPPER(TRIM(serial_number)) = ?', [$serial])
            ->whereIn('status', ['draft', 'submitted', 'returned'])->whereKeyNot($reg->id)->exists()) {
            $errors['serial_number'] = 'Another open registration already uses this serial number.';
        }

        if (!$reg->confirmed_not_duplicate) {
            $similar = Personnel::whereRaw('LOWER(last_name) = ? AND LOWER(first_name) = ?', [mb_strtolower($reg->last_name), mb_strtolower($reg->first_name)])
                ->when($reg->date_of_birth, fn ($q, $dob) => $q->whereDate('date_of_birth', $dob))
                ->count();
            if ($similar) {
                $errors['confirmed_not_duplicate'] = "{$similar} existing record(s) have the same name" . ($reg->date_of_birth ? ' and date of birth' : '')
                    . '. Check that this is a different person and tick the confirmation.';
            }
        }

        return $errors;
    }

    public function submit(PersonnelRegistration $reg): void
    {
        $this->expectStatus($reg, ['draft', 'returned']);
        if ($errors = $this->duplicateErrors($reg)) {
            throw ValidationException::withMessages($errors);
        }
        if (!$reg->rank_id || !$reg->assignment_start_date) {
            throw ValidationException::withMessages(['rank_id' => 'Rank and assignment effective date are required before submitting.']);
        }

        DB::transaction(function () use ($reg) {
            $from = $reg->status;
            $reg->forceFill(['status' => 'submitted', 'submitted_by' => session('admin_user_id'), 'submitted_at' => now()])->save();
            $this->record($reg, 'submitted', $from, 'submitted');
        });
    }

    public function endorse(PersonnelRegistration $reg, ?string $remarks): void
    {
        $this->expectStatus($reg, ['submitted']);
        $this->notOwn($reg, 'review');

        DB::transaction(function () use ($reg, $remarks) {
            $reg->forceFill(['reviewed_by' => session('admin_user_id'), 'reviewed_at' => now()])->save();
            $this->record($reg, 'endorsed', 'submitted', 'submitted', $remarks);
        });
    }

    public function returnForCorrection(PersonnelRegistration $reg, string $remarks): void
    {
        $this->expectStatus($reg, ['submitted']);

        DB::transaction(function () use ($reg, $remarks) {
            $reg->forceFill(['status' => 'returned', 'reviewed_by' => session('admin_user_id'), 'reviewed_at' => now()])->save();
            $this->record($reg, 'returned', 'submitted', 'returned', $remarks);
        });
    }

    public function approve(PersonnelRegistration $reg, ?string $remarks): Personnel
    {
        $this->expectStatus($reg, ['submitted']);
        $this->notOwn($reg, 'approve');

        return DB::transaction(function () use ($reg, $remarks) {
            $reg = PersonnelRegistration::whereKey($reg->id)->lockForUpdate()->first();
            $this->expectStatus($reg, ['submitted']);
            if ($errors = $this->duplicateErrors($reg)) {
                throw ValidationException::withMessages($errors);
            }

            $adminId = session('admin_user_id');
            $rank = $reg->rank;
            $person = Personnel::create([
                'serial_number' => Personnel::normalizeSerial($reg->serial_number),
                'last_name' => $reg->last_name,
                'first_name' => $reg->first_name,
                'middle_name' => $reg->middle_name,
                'suffix' => $reg->suffix,
                'full_name' => $reg->fullName(),
                'date_of_birth' => $reg->date_of_birth,
                'email' => $reg->email,
                'contact_number' => $reg->contact_number,
                'classification' => $rank?->classification,
                'rank_id' => $rank?->id,
                'rank_date' => $reg->rank_date,
                'date_entered_service' => $reg->date_entered_service,
                'service_status' => 'active',
                'user_id' => $this->matchingLogin($reg->serial_number),
                'source' => 'registration',
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);

            $this->assignments->assign($person, [
                'org_unit_id' => $reg->org_unit_id,
                'position' => $reg->position,
                'start_date' => $reg->assignment_start_date,
                'order_reference' => $reg->order_reference,
                'remarks' => "Registration #{$reg->id}",
            ], $adminId);

            $reg->forceFill([
                'status' => 'approved', 'decided_by' => $adminId, 'decided_at' => now(),
                'decision_remarks' => $remarks, 'personnel_id' => $person->id,
            ])->save();
            $this->record($reg, 'approved', 'submitted', 'approved', $remarks);

            return $person;
        });
    }

    public function reject(PersonnelRegistration $reg, string $remarks): void
    {
        $this->expectStatus($reg, ['submitted']);
        $this->notOwn($reg, 'reject');

        DB::transaction(function () use ($reg, $remarks) {
            $reg->forceFill(['status' => 'rejected', 'decided_by' => session('admin_user_id'), 'decided_at' => now(), 'decision_remarks' => $remarks])->save();
            $this->record($reg, 'rejected', 'submitted', 'rejected', $remarks);
        });
    }

    private function expectStatus(PersonnelRegistration $reg, array $allowed): void
    {
        if (!in_array($reg->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'This registration is ' . (PersonnelRegistration::STATUSES[$reg->status] ?? $reg->status) . ' and cannot be changed this way.']);
        }
    }

    /** No self-review/approval; decisions need a personal (database) admin account. */
    private function notOwn(PersonnelRegistration $reg, string $what): void
    {
        $me = session('admin_user_id');
        if (!$me) {
            throw ValidationException::withMessages(['status' => "Sign in with your own admin account to {$what} registrations (the setup account cannot)."]);
        }
        if (in_array((int) $me, array_filter([(int) $reg->created_by, (int) $reg->submitted_by]), true)) {
            throw ValidationException::withMessages(['status' => "You created or submitted this registration, so you cannot {$what} it. Another authorised admin must do it."]);
        }
    }

    private function matchingLogin(string $serial): ?int
    {
        try {
            $ids = DB::table('users')->whereRaw('UPPER(TRIM(serial_number)) = ?', [Personnel::normalizeSerial($serial)])->pluck('id');
        } catch (\Throwable $e) {
            return null; // no personnel login table on this installation
        }
        if ($ids->count() !== 1 || Personnel::where('user_id', $ids->first())->exists()) {
            return null;
        }

        return (int) $ids->first();
    }
}
