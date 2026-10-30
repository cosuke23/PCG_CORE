<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assign / transfer. A transfer ends the current assignment the day before
 * the new one starts; earlier rows are never changed or deleted, so a person
 * always has at most one current assignment and no overlapping periods.
 */
class PersonnelAssignments
{
    public function assign(Personnel $personnel, array $data, ?int $adminId): PersonnelAssignment
    {
        return DB::transaction(function () use ($personnel, $data, $adminId) {
            $start = Carbon::parse($data['start_date'])->startOfDay();

            $current = PersonnelAssignment::where('personnel_id', $personnel->id)
                ->whereNull('end_date')->lockForUpdate()->first();

            $latestEnd = PersonnelAssignment::where('personnel_id', $personnel->id)->max('end_date');

            if ($current && $start->lte($current->start_date)) {
                throw ValidationException::withMessages([
                    'start_date' => 'The new assignment must start after the current one (started ' . $current->start_date->format('d M Y') . ').',
                ]);
            }
            if (!$current && $latestEnd && $start->lte(Carbon::parse($latestEnd))) {
                throw ValidationException::withMessages([
                    'start_date' => 'The new assignment must start after the previous one ended (' . Carbon::parse($latestEnd)->format('d M Y') . ').',
                ]);
            }
            if ($current && (int) $current->org_unit_id === (int) $data['org_unit_id'] && ($current->position ?? '') === ($data['position'] ?? '')) {
                throw ValidationException::withMessages(['org_unit_id' => 'The person is already assigned there.']);
            }

            if ($current) {
                $current->update(['end_date' => $start->copy()->subDay(), 'ended_by' => $adminId]);
            }

            return PersonnelAssignment::create([
                'personnel_id' => $personnel->id,
                'org_unit_id' => $data['org_unit_id'],
                'position' => $data['position'] ?? null,
                'start_date' => $start,
                'order_reference' => $data['order_reference'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $adminId,
            ]);
        });
    }
}
