<?php

namespace App\Console\Commands;

use App\Models\OrgUnit;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copies approved personnel registrations (users) into the personnel master.
 *
 * Dry run by default: prints what would happen and changes nothing. With
 * --apply it only INSERTS personnel rows (and, with --assign-to, an initial
 * assignment). It never updates or deletes users or existing personnel.
 * Registrations whose serial number is missing or shared by several accounts
 * are skipped and listed so HR can resolve them first.
 */
class PersonnelSync extends Command
{
    protected $signature = 'pcg:personnel-sync
        {--apply : Write the records (without it nothing is changed)}
        {--include-pending : Also copy registrations that are not approved yet}
        {--assign-to= : Org unit code for an initial assignment of the copied records, e.g. MEPCOM}
        {--start-date= : Start date of that initial assignment (default: today)}
        {--map-units= : Unit file (e.g. database/data/mepcom_units.json): place each person at the unit they chose when registering, if it exists; otherwise at --assign-to}';

    protected $description = 'Copy approved registrations into the personnel master (dry run unless --apply)';

    public function handle()
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'APPLY: personnel records will be inserted.' : 'DRY RUN: nothing will be changed. Add --apply to write.');

        $unit = null;
        if ($code = $this->option('assign-to')) {
            $unit = OrgUnit::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->where('is_active', true)->first();
            if (!$unit) {
                $this->error("No active org unit with code {$code}.");

                return self::FAILURE;
            }
        }
        $startDate = $this->option('start-date') ?: now()->toDateString();
        if (!strtotime($startDate)) {
            $this->error('Invalid --start-date.');

            return self::FAILURE;
        }

        $unitMap = $this->option('map-units') ? $this->loadUnitMap($this->option('map-units')) : null;
        if ($this->option('map-units') && !$unit) {
            $this->error('--map-units needs --assign-to (the fallback unit, e.g. MEPCOM).');

            return self::FAILURE;
        }

        $users = DB::table('users')->get(['id', 'name', 'email', 'rank', 'serial_number', 'registration_type', 'date_of_birth', 'date_of_appointment', 'status', 'mother_unit', 'sub_unit', 'unit_assignment']);
        $ranks = Rank::all()->keyBy(fn ($r) => strtoupper($r->code));

        $byStatus = $users->groupBy(fn ($u) => strtolower(trim((string) $u->status)) ?: '(empty)')->map->count();
        $candidates = $users->filter(fn ($u) => $this->option('include-pending') || strtolower(trim((string) $u->status)) === 'approved');

        $missing = $candidates->filter(fn ($u) => Personnel::normalizeSerial($u->serial_number) === '');
        $withSerial = $candidates->reject(fn ($u) => Personnel::normalizeSerial($u->serial_number) === '');
        // duplicates are judged across ALL accounts, not only the candidates
        $allBySerial = $users->groupBy(fn ($u) => Personnel::normalizeSerial($u->serial_number))->forget('');
        $duplicates = $allBySerial->filter(fn ($g) => $g->count() > 1);

        $existingSerials = Personnel::pluck('serial_number')->map(fn ($s) => Personnel::normalizeSerial($s))->flip();
        $linkedUsers = Personnel::whereNotNull('user_id')->pluck('user_id')->flip();

        $toCreate = collect();
        $already = 0;
        $unknownRanks = collect();
        $classMismatch = 0;
        foreach ($withSerial as $u) {
            $serial = Personnel::normalizeSerial($u->serial_number);
            if ($duplicates->has($serial)) {
                continue;
            }
            if ($existingSerials->has($serial) || $linkedUsers->has($u->id)) {
                $already++;
                continue;
            }
            $rank = $ranks->get(strtoupper(trim((string) $u->rank)));
            if (!$rank && trim((string) $u->rank) !== '') {
                $unknownRanks->push(trim($u->rank));
            }
            if ($rank && in_array($u->registration_type, ['officer', 'non_officer'], true) && $u->registration_type !== $rank->classification) {
                $classMismatch++;
            }
            $target = $unit ? ($unitMap ? ($this->registeredUnit($u, $unitMap) ?? $unit) : $unit) : null;
            $toCreate->push([$u, $serial, $rank, $target]);
        }

        $this->newLine();
        $this->line('Registrations by status: ' . $byStatus->map(fn ($n, $s) => "{$s}={$n}")->implode(', '));
        $this->table(['Check', 'Count'], [
            ['Registrations considered (' . ($this->option('include-pending') ? 'all statuses' : 'approved only') . ')', $candidates->count()],
            ['Missing serial number (skipped)', $missing->count()],
            ['Serial numbers shared by several accounts (skipped)', $duplicates->count() . ' serials / ' . $duplicates->flatten(1)->count() . ' accounts'],
            ['Already in the personnel master', $already],
            ['Rank not in the rank list (copied without rank)', $unknownRanks->count()],
            ['Registration type differs from rank (rank used)', $classMismatch],
            ['Would create', $toCreate->count()],
            ['Initial assignment', $unit ? "{$unit->code} from {$startDate}" : 'none (records stay unassigned)'],
            ['  placed at the unit chosen at registration', $unitMap ? $toCreate->filter(fn ($r) => $r[3] && $r[3]->id !== $unit->id)->count() : '-'],
        ]);

        if ($duplicates->isNotEmpty()) {
            $this->warn('Resolve these shared serial numbers first (account ids):');
            foreach ($duplicates as $serial => $group) {
                $this->line("  {$serial}: users " . $group->pluck('id')->implode(', '));
            }
        }
        if ($missing->isNotEmpty()) {
            $this->warn('Approved accounts without a serial number (ids): ' . $missing->pluck('id')->implode(', '));
        }
        if ($unknownRanks->isNotEmpty()) {
            $this->warn('Unrecognised rank values: ' . $unknownRanks->countBy()->map(fn ($n, $r) => "{$r} ({$n})")->implode(', '));
        }

        if (!$apply || $toCreate->isEmpty()) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($toCreate, $startDate) {
            foreach ($toCreate as [$u, $serial, $rank, $target]) {
                // a recognised rank decides; otherwise the registration type
                $classification = $rank?->classification
                    ?? (in_array($u->registration_type, ['officer', 'non_officer'], true) ? $u->registration_type : null);

                $person = Personnel::create([
                    'serial_number' => $serial,
                    'full_name' => trim(preg_replace('/\s+/', ' ', (string) $u->name)) ?: $serial,
                    'date_of_birth' => $u->date_of_birth,
                    'email' => $u->email ? strtolower($u->email) : null,
                    'classification' => $classification,
                    'rank_id' => $rank?->id,
                    'date_entered_service' => $u->date_of_appointment,
                    'service_status' => 'active',
                    'user_id' => $u->id,
                    'source' => 'legacy_users',
                ]);

                if ($target) {
                    PersonnelAssignment::create([
                        'personnel_id' => $person->id,
                        'org_unit_id' => $target->id,
                        'start_date' => $startDate,
                        'remarks' => 'Initial placement when copied from the registration' .
                            ($u->unit_assignment || $u->sub_unit ? ' (registered: ' . mb_substr(trim($u->sub_unit . ' / ' . $u->unit_assignment, ' /'), 0, 200) . ')' : ''),
                    ]);
                }
            }
        });

        $this->info("Created {$toCreate->count()} personnel records.");

        return self::SUCCESS;
    }

    /**
     * Lookup built from the reviewed unit file: unit code => [source text => org unit code].
     * Registration stores sub_unit = "<unit name> (<CODE>)" and
     * unit_assignment = "<sub level> - <entry>".
     */
    private function loadUnitMap(string $file): array
    {
        $path = is_file($file) ? $file : base_path($file);
        $data = json_decode((string) @file_get_contents($path), true);
        $map = [];
        $collect = function (array $nodes, string $unitCode) use (&$collect, &$map) {
            foreach ($nodes as $n) {
                if (!empty($n['source'])) {
                    $map[$unitCode][mb_strtolower(trim($n['source']))] = $n['code'];
                }
                // headquarters / group levels are chosen as the "sub level" in the form
                $map[$unitCode][mb_strtolower(trim($n['code']))] = $n['code'];
                $map[$unitCode][mb_strtolower(str_replace(['MEP Group ', 'Headquarters, '], ['MEPGroup ', 'H'], $n['name']))] = $n['code'];
                $collect($n['children'] ?? [], $unitCode);
            }
        };
        foreach ($data['units'] ?? [] as $unit) {
            $map[$unit['code']]['@unit'] = $unit['code'];
            $collect($unit['children'] ?? [], $unit['code']);
        }

        return $map;
    }

    private function registeredUnit(object $u, array $map): ?OrgUnit
    {
        if (!preg_match('/\(([^)]+)\)\s*$/', (string) $u->sub_unit, $m) || !isset($map[$m[1]])) {
            return null;
        }
        $lookup = $map[$m[1]];
        [$sub, $entry] = array_pad(array_map('trim', explode(' - ', (string) $u->unit_assignment, 2)), 2, '');

        $code = $lookup[mb_strtolower($entry)] ?? $lookup[mb_strtolower($sub)] ?? $lookup['@unit'];

        return OrgUnit::where('code', $code)->where('is_active', true)->first();
    }
}
