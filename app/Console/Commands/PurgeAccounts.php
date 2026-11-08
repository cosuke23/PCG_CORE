<?php

namespace App\Console\Commands;

use App\Models\Personnel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Clears test accounts before real use: deletes every member account (and the
 * records tied to its serial number), every admin account and every unit/M1
 * checker account, EXCEPT the ones named with --keep-admin / --keep-user and
 * member accounts registered on or after --keep-since (Asia/Manila date).
 *
 * Dry run by default: lists what would go. With --apply it asks for a typed
 * confirmation and deletes inside one transaction. TAKE A BACKUP FIRST
 * (deploy/backup.sh); the backup is the only way back. Audit logs, org units,
 * courses and OSAERT records are never touched; encrypted files stay on disk,
 * unlinked. Records whose serial number belongs to no deleted account, or to a
 * kept account, are left alone.
 */
class PurgeAccounts extends Command
{
    protected $signature = 'pcg:purge-accounts
        {--keep-admin=* : Admin email to keep (at least one active super_admin)}
        {--keep-user=* : Member email to keep, with its records}
        {--keep-since= : Also keep members registered on or after this date (YYYY-MM-DD, Philippine date)}
        {--apply : Delete (without it nothing is changed)}';

    protected $description = 'Delete test accounts and their records, keeping the named ones (dry run unless --apply)';

    /** Tables handled by hand (accounts, the personnel master and its children). */
    private const SPECIAL = ['users', 'unit_users', 'admin_users', 'personnel', 'personnel_registrations',
        'admin_audit_logs', 'document_access_logs', 'registration_events', 'contact_messages'];

    public function handle()
    {
        $keepAdmins = array_map(fn ($e) => mb_strtolower(trim($e)), $this->option('keep-admin'));
        $keepUsers = array_map(fn ($e) => mb_strtolower(trim($e)), $this->option('keep-user'));
        $since = $this->option('keep-since');

        if (!$keepAdmins) {
            $this->error('Name the admin account to keep: --keep-admin=you@example.com');

            return self::FAILURE;
        }
        $superKept = DB::table('admin_users')->whereIn(DB::raw('LOWER(email)'), $keepAdmins)
            ->where('role', 'super_admin')->where('is_active', true)->count();
        if ($superKept === 0) {
            $this->error('None of the kept admin accounts is an active super_admin: you would lock yourself out. Nothing changed.');

            return self::FAILURE;
        }
        $sinceUtc = null;
        if ($since) {
            try {
                $sinceUtc = Carbon::createFromFormat('Y-m-d', $since, 'Asia/Manila')->startOfDay()->utc();
            } catch (\Throwable $e) {
                $this->error('--keep-since must be a date like 2026-11-08.');

                return self::FAILURE;
            }
        }

        // ---- who stays, who goes
        $users = DB::table('users')->get(['id', 'email', 'serial_number', 'status', 'created_at']);
        [$keptUsers, $goUsers] = $users->partition(function ($u) use ($keepUsers, $sinceUtc) {
            if (in_array(mb_strtolower(trim((string) $u->email)), $keepUsers, true)) {
                return true;
            }

            return $sinceUtc && $u->created_at && Carbon::parse($u->created_at)->gte($sinceUtc);
        });
        $keptSerials = $keptUsers->map(fn ($u) => Personnel::normalizeSerial($u->serial_number))->filter()->unique();
        $goSerials = $goUsers->map(fn ($u) => Personnel::normalizeSerial($u->serial_number))->filter()->unique()
            ->diff($keptSerials)->values();
        $goUserIds = $goUsers->pluck('id')->all();

        $admins = DB::table('admin_users')->get(['id', 'email', 'role']);
        $goAdmins = $admins->reject(fn ($a) => in_array(mb_strtolower($a->email), $keepAdmins, true));
        $unitCount = Schema::hasTable('unit_users') ? DB::table('unit_users')->count() : 0;

        $this->info($this->option('apply') ? 'APPLY' : 'DRY RUN (nothing is changed; add --apply to delete)');
        $this->line('');
        $this->line('Kept admin accounts:   ' . $admins->whereNotIn('id', $goAdmins->pluck('id'))->pluck('email')->implode(', '));
        $this->line('Kept member accounts:  ' . ($keptUsers->isEmpty() ? '(none)' : $keptUsers->map(fn ($u) => "{$u->email} [{$u->status}]")->implode(', ')));
        $this->line('');
        $this->table(['Will be deleted', 'Count'], [
            ['Member accounts', $goUsers->count()],
            ['  their serial numbers (records removed)', $goSerials->count()],
            ['Admin accounts', $goAdmins->count()],
            ['Unit and M1 checker accounts (all)', $unitCount],
        ]);

        // ---- record tables tied to a serial number / account
        $plan = $this->plan($goSerials->all(), $goUserIds);
        $this->table(['Records table', 'Rows to delete'], collect($plan)->map(fn ($p) => [$p['label'], $p['count']])->filter(fn ($r) => $r[1] > 0)->values()->all() ?: [['(none)', 0]]);

        if (!$this->option('apply')) {
            $this->line('Back up first (deploy/backup.sh), then run again with --apply.');

            return self::SUCCESS;
        }

        if ($this->ask('This cannot be undone without the backup. Type DELETE to continue') !== 'DELETE') {
            $this->warn('Not confirmed. Nothing changed.');

            return self::FAILURE;
        }

        $done = DB::transaction(function () use ($plan, $goUserIds, $goAdmins) {
            $n = [];
            foreach ($plan as $p) {
                $n[$p['label']] = ($p['run'])();
            }
            $n['users'] = $goUserIds ? DB::table('users')->whereIn('id', $goUserIds)->delete() : 0;
            $n['admin_users'] = $goAdmins->isEmpty() ? 0 : DB::table('admin_users')->whereIn('id', $goAdmins->pluck('id'))->delete();
            $n['unit_users'] = Schema::hasTable('unit_users') ? DB::table('unit_users')->delete() : 0;

            return $n;
        });

        Log::channel('audit')->info('pcg:purge-accounts applied', ['deleted' => $done]);
        $this->info('Done: ' . collect($done)->filter()->map(fn ($c, $t) => "$t $c")->implode(', '));

        return self::SUCCESS;
    }

    /** Ordered delete steps (children before parents), each with a row count. */
    private function plan(array $serials, array $userIds): array
    {
        $steps = [];
        $bySerial = fn ($q, $col = 'serial_number') => $q->whereIn(DB::raw("UPPER(TRIM($col))"), $serials ?: ['__none__']);
        $add = function (string $label, \Closure $query) use (&$steps) {
            $steps[] = ['label' => $label, 'count' => $query()->count(), 'run' => fn () => $query()->delete()];
        };

        // personnel master and everything hanging off it
        if (Schema::hasTable('personnel')) {
            $personnelIds = $bySerial(DB::table('personnel'))
                ->when($userIds, fn ($q) => $q->orWhereIn('user_id', $userIds))
                ->pluck('id')->all() ?: [0];
            $regIds = Schema::hasTable('personnel_registrations')
                ? $bySerial(DB::table('personnel_registrations'))->orWhereIn('personnel_id', $personnelIds)->pluck('id')->all() ?: [0]
                : [0];
            $docIds = Schema::hasTable('personnel_documents')
                ? DB::table('personnel_documents')->whereIn('personnel_id', $personnelIds)->pluck('id')->all() ?: [0]
                : [0];

            if (Schema::hasTable('document_access_logs')) {
                $add('document_access_logs', fn () => DB::table('document_access_logs')->whereIn('document_id', $docIds));
            }
            if (Schema::hasTable('personnel_documents')) {
                $steps[] = ['label' => 'personnel_documents (links)', 'count' => 0,
                    'run' => fn () => DB::table('personnel_documents')->whereIn('id', $docIds)->update(['supersedes_id' => null]) * 0];
                $add('personnel_documents', fn () => DB::table('personnel_documents')->whereIn('id', $docIds));
            }
            foreach (['promotions', 'personnel_trainings', 'personnel_assignments'] as $t) {
                if (Schema::hasTable($t)) {
                    $add($t, fn () => DB::table($t)->whereIn('personnel_id', $personnelIds));
                }
            }
            if (Schema::hasTable('registration_events')) {
                $add('registration_events', fn () => DB::table('registration_events')->whereIn('registration_id', $regIds));
            }
            if (Schema::hasTable('personnel_registrations')) {
                $add('personnel_registrations', fn () => DB::table('personnel_registrations')->whereIn('id', $regIds));
            }
            $add('personnel', fn () => DB::table('personnel')->whereIn('id', $personnelIds));
        }

        // every other table with a serial number (SOI sections, uploads, trainings, ...)
        foreach (Schema::getTables() as $t) {
            $name = $t['name'];
            if (in_array($name, self::SPECIAL, true) || !Schema::hasColumn($name, 'serial_number')) {
                continue;
            }
            $add($name, fn () => $bySerial(DB::table($name)));
        }
        foreach (['profiles' => 'user_id', 'course_students' => 'user_id'] as $t => $col) {
            if ($userIds && Schema::hasTable($t) && Schema::hasColumn($t, $col)) {
                $add("$t (by account)", fn () => DB::table($t)->whereIn($col, $userIds));
            }
        }

        return $steps;
    }
}
