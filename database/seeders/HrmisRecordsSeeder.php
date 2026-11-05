<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * HRMIS records: registrations in every state with their history,
 * 201 documents (real small PDF files with checksums, one restricted,
 * one expired, a superseded version), document access logs, promotions
 * and trainings. Needs PersonnelMasterSeeder (personnel rows) first.
 */
class HrmisRecordsSeeder extends DevOnlySeeder
{
    public function run(): void
    {
        $this->ensureSafe();
        if ($this->alreadySeeded('personnel_registrations', 'serial_number', 'DEMO-R%')) {
            $this->command?->line('  HRMIS records already present');
            return;
        }

        $personnel = DB::table('personnel')->orderBy('id')->get();
        if ($personnel->isEmpty()) {
            $this->command?->warn('  no personnel rows: run PersonnelMasterSeeder first');
            return;
        }

        $now = now();
        $admins = DB::table('admin_users')->pluck('id', 'email');
        $hr = $admins['hr@demo.test'] ?? null;
        $approver = $admins['approver@demo.test'] ?? null;
        $reviewer = $admins['reviewer@demo.test'] ?? null;
        $unitId = fn (string $code) => DB::table('org_units')->where('code', $code)->value('id')
            ?? DB::table('org_units')->orderBy('id')->value('id');
        $rankId = fn (string $code) => DB::table('ranks')->where('code', $code)->value('id');
        $counts = [];
        $add = function (string $table, array $row) use (&$counts) {
            $counts[$table] = ($counts[$table] ?? 0) + 1;

            return DB::table($table)->insertGetId($row);
        };

        DB::transaction(function () use ($personnel, $now, $hr, $approver, $reviewer, $unitId, $rankId, $add) {
            // ---------------- registrations, one per state, with history
            $states = [
                ['draft', 'Dela Cruz', 'Andres', 'MEPLEF', []],
                ['submitted', 'Villanueva', 'Bea', 'NOCOP', [['submitted', 'draft', 'submitted', null]]],
                ['returned', 'Lim', 'Carlo', 'MEPTDI', [['submitted', 'draft', 'submitted', null], ['returned', 'submitted', 'returned', 'Please attach the appointment order.']]],
                ['rejected', 'Ocampo', 'Dina', 'MSFIS', [['submitted', 'draft', 'submitted', null], ['rejected', 'submitted', 'rejected', 'Duplicate of an existing record.']]],
                ['approved', null, null, 'MEPCOM', [['submitted', 'draft', 'submitted', null], ['endorsed', 'submitted', 'submitted', 'Complete.'], ['approved', 'submitted', 'approved', null]]],
            ];
            foreach ($states as $i => [$status, $last, $first, $unit, $events]) {
                $linked = $status === 'approved' ? $personnel->first() : null;
                $regId = $add('personnel_registrations', [
                    'status' => $status,
                    'serial_number' => $linked->serial_number ?? sprintf('DEMO-R%02d', $i + 1),
                    'last_name' => $last ?? ($linked->last_name ?: 'Sample'),
                    'first_name' => $first ?? ($linked->first_name ?: 'Member'),
                    'email' => strtolower(($first ?? 'member') . '.' . str_replace(' ', '', $last ?? 'sample')) . '@demo.test',
                    'contact_number' => '0900000000' . $i,
                    'date_of_birth' => $now->copy()->subYears(24 + $i)->toDateString(),
                    'rank_id' => $rankId(['SN1', 'SN2', 'PO3', 'SN1', 'PO2'][$i]),
                    'date_entered_service' => $now->copy()->subYears(2 + $i)->toDateString(),
                    'org_unit_id' => $unitId($unit),
                    'position' => 'Sample position',
                    'assignment_start_date' => $now->copy()->subMonths(6)->toDateString(),
                    'order_reference' => 'SO-SAMPLE-' . (100 + $i),
                    'confirmed_not_duplicate' => $status !== 'draft',
                    'created_by' => $hr,
                    'submitted_by' => $status === 'draft' ? null : $hr,
                    'submitted_at' => $status === 'draft' ? null : $now->copy()->subDays(10),
                    'reviewed_by' => in_array($status, ['approved'], true) ? $reviewer : null,
                    'reviewed_at' => in_array($status, ['approved'], true) ? $now->copy()->subDays(7) : null,
                    'decided_by' => in_array($status, ['approved', 'rejected', 'returned'], true) ? $approver : null,
                    'decided_at' => in_array($status, ['approved', 'rejected', 'returned'], true) ? $now->copy()->subDays(5) : null,
                    'decision_remarks' => collect($events)->last()[3] ?? null,
                    'personnel_id' => $linked->id ?? null,
                    'created_at' => $now->copy()->subDays(12), 'updated_at' => $now,
                ]);
                $add('registration_events', ['registration_id' => $regId, 'action' => 'created', 'from_status' => null, 'to_status' => 'draft',
                    'admin_user_id' => $hr, 'admin_email' => 'hr@demo.test', 'created_at' => $now->copy()->subDays(12)]);
                foreach ($events as $n => [$action, $from, $to, $remarks]) {
                    $by = in_array($action, ['submitted'], true) ? [$hr, 'hr@demo.test'] : ($action === 'endorsed' ? [$reviewer, 'reviewer@demo.test'] : [$approver, 'approver@demo.test']);
                    $add('registration_events', ['registration_id' => $regId, 'action' => $action, 'from_status' => $from, 'to_status' => $to,
                        'remarks' => $remarks, 'admin_user_id' => $by[0], 'admin_email' => $by[1], 'created_at' => $now->copy()->subDays(10 - 2 * $n)]);
                }
            }

            // ---------------- 201 documents with real files
            $categories = DB::table('document_categories')->pluck('id', 'code');
            $root = config('pcg.document_root');
            $file = function (int $personnelId, string $label) use ($root, $now) {
                $pdf = "%PDF-1.4\n% Sample document: {$label}\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
                $relative = $personnelId . '/' . $now->format('Y') . '/' . Str::random(40) . '.pdf';
                File::ensureDirectoryExists(dirname($root . '/' . $relative), 0755);
                \App\Support\SecureFiles::storeBytes($pdf, $root . '/' . $relative, 'personnel-documents/' . $relative); // encrypted like real uploads

                return [$relative, strlen($pdf), hash('sha256', $pdf)];
            };
            foreach ($personnel->take(4) as $n => $p) {
                $docs = [
                    ['identification', 'Government ID (sample)', 'verified', null, $now->copy()->addYears(3)],
                    ['appointment', 'Appointment order (sample)', $n === 0 ? 'pending' : 'verified', null, null],
                    ['training', 'Training certificate (sample)', $n === 1 ? 'rejected' : 'verified', $n === 1 ? 'Unreadable scan, upload again.' : null, $n === 2 ? $now->copy()->subMonths(2) : $now->copy()->addYear()],
                    ['medical', 'Medical certificate (sample, restricted)', 'verified', null, $now->copy()->addMonths(10)],
                ];
                foreach ($docs as [$code, $title, $status, $remarks, $expires]) {
                    if (!isset($categories[$code])) {
                        continue;
                    }
                    [$path, $size, $sha] = $file($p->id, $title);
                    $docId = $add('personnel_documents', [
                        'personnel_id' => $p->id, 'category_id' => $categories[$code], 'title' => $title,
                        'stored_path' => $path, 'original_name' => Str::slug($title) . '.pdf', 'mime_type' => 'application/pdf',
                        'size_bytes' => $size, 'sha256' => $sha, 'version' => 1, 'is_current' => true, 'status' => $status,
                        'review_remarks' => $remarks, 'verified_by' => $status === 'pending' ? null : $reviewer,
                        'verified_at' => $status === 'pending' ? null : $now->copy()->subDays(3),
                        'issued_on' => $now->copy()->subYear()->toDateString(), 'expires_on' => $expires?->toDateString(),
                        'uploaded_by' => $hr, 'created_at' => $now->copy()->subDays(4), 'updated_at' => $now,
                    ]);
                    $add('document_access_logs', ['document_id' => $docId, 'action' => 'upload', 'admin_user_id' => $hr, 'admin_email' => 'hr@demo.test', 'ip' => '127.0.0.1', 'created_at' => $now->copy()->subDays(4)]);
                    if ($status !== 'pending') {
                        $add('document_access_logs', ['document_id' => $docId, 'action' => $status === 'rejected' ? 'reject' : 'verify', 'admin_user_id' => $reviewer, 'admin_email' => 'reviewer@demo.test', 'ip' => '127.0.0.1', 'created_at' => $now->copy()->subDays(3)]);
                    }
                }
            }
            // a superseded version: the first person's ID was replaced
            $first = $personnel->first();
            $old = DB::table('personnel_documents')->where('personnel_id', $first->id)->where('category_id', $categories['identification'] ?? 0)->first();
            if ($old) {
                [$path, $size, $sha] = $file($first->id, 'Government ID (sample, renewed)');
                DB::table('personnel_documents')->where('id', $old->id)->update(['is_current' => false, 'updated_at' => $now]);
                $add('personnel_documents', [
                    'personnel_id' => $first->id, 'category_id' => $old->category_id, 'title' => 'Government ID (sample, renewed)',
                    'stored_path' => $path, 'original_name' => 'government-id-renewed.pdf', 'mime_type' => 'application/pdf',
                    'size_bytes' => $size, 'sha256' => $sha, 'version' => 2, 'supersedes_id' => $old->id, 'is_current' => true,
                    'status' => 'pending', 'issued_on' => $now->toDateString(), 'expires_on' => $now->copy()->addYears(5)->toDateString(),
                    'uploaded_by' => $hr, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            // ---------------- promotions (keep rank history) and trainings
            foreach ($personnel->take(3) as $n => $p) {
                if (!$p->rank_id) {
                    continue;
                }
                $current = DB::table('ranks')->find($p->rank_id);
                $previous = DB::table('ranks')->where('classification', $current->classification)
                    ->where('seniority', '<', $current->seniority)->orderByDesc('seniority')->first();
                if ($previous) {
                    $add('promotions', [
                        'personnel_id' => $p->id, 'from_rank_id' => $previous->id, 'to_rank_id' => $current->id,
                        'effective_date' => $now->copy()->subMonths(6 + $n)->toDateString(), 'order_reference' => 'GO-SAMPLE-' . (200 + $n),
                        'remarks' => 'Sample promotion', 'created_by' => $hr, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            foreach ($personnel as $n => $p) {
                $add('personnel_trainings', [
                    'personnel_id' => $p->id, 'title' => 'Sample Oil Spill Response Course', 'kind' => 'course', 'provider' => 'MEPTDI',
                    'start_date' => $now->copy()->subMonths(8)->toDateString(), 'end_date' => $now->copy()->subMonths(8)->addDays(5)->toDateString(),
                    'certificate_number' => 'MEPTDI-SAMPLE-' . (300 + $n), 'created_by' => $hr, 'created_at' => $now, 'updated_at' => $now,
                ]);
                if ($n % 2 === 0) {
                    $add('personnel_trainings', [
                        'personnel_id' => $p->id, 'title' => 'Sample Basic Safety Training', 'kind' => 'qualification', 'provider' => 'PCG',
                        'start_date' => $now->copy()->subYears(2)->toDateString(), 'end_date' => $now->copy()->subYears(2)->addDays(3)->toDateString(),
                        'valid_until' => $n === 0 ? $now->copy()->subMonth()->toDateString() : $now->copy()->addYears(2)->toDateString(),
                        'remarks' => $n === 0 ? 'Expired: renew' : null, 'created_by' => $hr, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        });

        $this->command?->line('  HRMIS records: ' . collect($counts)->map(fn ($c, $t) => "$t $c")->implode(', '));
    }
}
