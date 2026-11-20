<?php

namespace Database\Seeders;

use App\Support\LeaveCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * leave_requests: approved, pending and declined leave of the demo members around
 * the current month, so both leave calendars (/leave, /unit/leave) show several
 * people on the same days.
 */
class LeaveSeeder extends DevOnlySeeder
{
    public function run(): void
    {
        $this->ensureSafe();
        if (!Schema::hasTable('leave_requests') || DB::table('leave_requests')->where('serial_number', 'like', 'DEMO-%')->exists()) {
            return;
        }

        $users = DB::table('users')->where('serial_number', 'like', 'DEMO-%')->orderBy('id')->get()->values();
        if ($users->isEmpty()) {
            return;
        }
        $base = LeaveCalendar::today()->startOfMonth();
        $now = now();
        $plan = [
            // [user index, type, start offset (days from the 1st), length, status, note]
            [0, 'rnr', 20, 5, 'approved', null],
            [1, 'ordinary', 21, 3, 'approved', null],
            [2, 'sick', 21, 2, 'approved', null],
            [3, 'emergency', 22, 1, 'approved', null],
            [4, 'paternity', 21, 7, 'approved', null],
            [5, 'rnr', 9, 5, 'approved', null],
            [6, 'rnr', 22, 5, 'approved', null],
            [0, 'pass', 34, 2, 'pending', null],
            [1, 'study', 40, 10, 'pending', null],
            [2, 'ordinary', 3, 4, 'declined', 'Ship deploys on these dates (sample)'],
        ];
        foreach ($plan as [$i, $type, $offset, $len, $status, $note]) {
            $u = $users[$i % $users->count()];
            $start = $base->copy()->addDays($offset);
            DB::table('leave_requests')->insert([
                'user_id' => $u->id, 'serial_number' => $u->serial_number, 'unit_name' => $u->mother_unit ?? null,
                'leave_type' => $type, 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addDays($len - 1)->toDateString(),
                'days' => $len, 'reason' => 'Sample leave', 'status' => $status,
                'reviewer_name' => $status === 'pending' ? null : 'Unit Checker (sample)',
                'reviewed_at' => $status === 'pending' ? null : $now, 'review_note' => $note,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
