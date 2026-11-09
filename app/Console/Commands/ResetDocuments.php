<?php

namespace App\Console\Commands;

use App\Support\DocumentReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Clears one member's required documents so they upload everything again
 * (owner request 2026-11-09, e.g. clearances accepted without a "valid until"
 * date). For the record(s) of the given serial number it empties the ten
 * document links, the three "valid until" dates and the residence pin, and sets
 * the status to "rejected" (returned): the member's boxes open, the checker sees
 * the record as Returned. Open "returned" notes for that serial are closed.
 *
 * Dry run by default; --apply asks for a typed confirmation and runs in one
 * transaction. The encrypted files stay on disk (unlinked), nothing else is
 * touched (201 files, SOI, account). Logged to the audit channel.
 * TAKE A BACKUP FIRST (deploy/backup.sh).
 */
class ResetDocuments extends Command
{
    protected $signature = 'pcg:reset-documents
        {serial : Serial number of the member}
        {--reason= : Why (kept in the audit log), e.g. "clearances without valid-until dates"}
        {--apply : Clear them (without it nothing is changed)}';

    protected $description = "Clear a member's required documents so they upload them again (dry run unless --apply)";

    public function handle()
    {
        $serial = trim((string) $this->argument('serial'));
        if ($serial === '' || !Schema::hasTable('personnel_data')) {
            $this->error('No serial number given, or the personnel_data table is missing.');
            return self::FAILURE;
        }

        $rows = DB::table('personnel_data')->where('serial_number', $serial)->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->warn("No required-documents record for serial number {$serial}. Nothing to clear.");
            return self::SUCCESS;
        }

        $member = DB::table('users')->where('serial_number', $serial)->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])->first(['id', 'name']);
        $this->info('Member: ' . ($member ? $member->name . " (account {$member->id})" : 'no approved account with this serial'));

        $columns = array_values(array_filter(array_merge(
            DocumentReview::FILE_FIELDS,
            array_map(fn ($f) => $f . '_expiration', DocumentReview::EXPIRY_FIELDS),
            ['latitude', 'longitude']
        ), fn ($c) => Schema::hasColumn('personnel_data', $c)));

        $table = [];
        foreach ($rows as $r) {
            $filled = array_values(array_filter($columns, fn ($c) => $r->$c !== null && $r->$c !== ''));
            $table[] = [$r->id, $r->personnel_id ?? '—', $r->status ?? '(none)', count(array_intersect($filled, DocumentReview::FILE_FIELDS)),
                implode(', ', array_diff($filled, DocumentReview::FILE_FIELDS)) ?: '—'];
        }
        $this->table(['record', 'account', 'status', 'documents linked', 'dates / pin set'], $table);
        $openNotes = Schema::hasTable('rejected_documents')
            ? DB::table('rejected_documents')->where('serial_number', $serial)->whereRaw('LOWER(status) = ?', ['rejected'])->count() : 0;
        $this->line("Open \"returned\" notes to close: {$openNotes}");
        $this->line('Would set: ' . implode(', ', $columns) . ' to empty, and status to "rejected" (returned).');
        $this->line('Files stay on disk, encrypted and unlinked. 201 files, the SOI and the account are not touched.');

        if (!$this->option('apply')) {
            $this->warn('Dry run: nothing changed. Run again with --apply (after a backup) to clear.');
            return self::SUCCESS;
        }
        if ($this->ask("Type RESET {$serial} to clear these documents") !== "RESET {$serial}") {
            $this->warn('Not confirmed. Nothing changed.');
            return self::FAILURE;
        }

        $ids = $rows->pluck('id')->all();
        DB::transaction(function () use ($ids, $columns, $serial) {
            DB::table('personnel_data')->whereIn('id', $ids)
                ->update(array_fill_keys($columns, null) + ['status' => 'rejected']
                    + (Schema::hasColumn('personnel_data', 'updated_at') ? ['updated_at' => now()] : []));
            if (Schema::hasTable('rejected_documents')) {
                DB::table('rejected_documents')->where('serial_number', $serial)->whereRaw('LOWER(status) = ?', ['rejected'])
                    ->update(['status' => 'cleared'] + (Schema::hasColumn('rejected_documents', 'updated_at') ? ['updated_at' => now()] : []));
            }
        });

        Log::channel('audit')->info('pcg:reset-documents applied', [
            'serial_number' => $serial,
            'records' => $ids,
            'reason' => $this->option('reason'),
        ]);
        $this->info('Cleared. The member can upload every document again; the record shows as Returned to the unit checker.');

        return self::SUCCESS;
    }
}
