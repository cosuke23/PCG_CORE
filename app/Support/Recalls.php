<?php

namespace App\Support;

use App\Mail\RecallAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Emergency recall (owner request 2026-11-20). Sending creates one response row per
 * approved member of the unit (users.mother_unit; NULL = every unit), emails them,
 * and adds a portal notification (which the Android app turns into a phone alert on
 * its next check, at most 15 minutes later). Members answer at /recall/{id}.
 */
class Recalls
{
    public const ANSWERS = ['coming' => 'On my way', 'cannot' => 'Cannot come'];

    public static function ready(): bool
    {
        return Schema::hasTable('recalls') && Schema::hasTable('recall_responses');
    }

    public static function members(?string $unit): Collection
    {
        return DB::table('users')->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
            ->when($unit !== null, fn ($q) => $q->whereRaw('LOWER(TRIM(mother_unit)) = ?', [mb_strtolower(trim($unit))]))
            ->get(['id', 'name', 'rank', 'email', 'mother_unit']);
    }

    /** Send a recall; returns [recall id, recipients, emails sent]. */
    public static function send(?string $unit, string $title, string $message, ?string $reportTo, array $by): array
    {
        $members = self::members($unit);
        $id = DB::table('recalls')->insertGetId([
            'unit_name' => $unit, 'title' => $title, 'message' => $message, 'report_to' => $reportTo, 'recipients' => $members->count(),
            'sent_by_type' => $by['type'], 'sent_by_id' => $by['id'] ?? null, 'sent_by_name' => $by['name'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($members->chunk(500) as $chunk) {
            DB::table('recall_responses')->insert($chunk->map(fn ($m) => ['recall_id' => $id, 'user_id' => $m->id, 'created_at' => now(), 'updated_at' => now()])->all());
        }
        $mailed = 0;
        foreach ($members as $m) {
            MemberNotifications::send((int) $m->id, 'recall', 'RECALL: ' . $title, mb_substr($message, 0, 300), '/recall/' . $id);
            if (filter_var($m->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($m->email)->send(new RecallAlert((string) $m->name, $title, $message, $reportTo, (string) ($by['name'] ?? ''), url('/recall/' . $id), (string) config('app.name', 'MEPCOM HRMIS')));
                    $mailed++;
                } catch (\Throwable $e) {
                    Log::warning('Recall email failed', ['recall_id' => $id, 'user_id' => $m->id, 'reason' => class_basename($e)]);
                }
            }
        }
        Log::channel('audit')->info('recall.sent', ['recall_id' => $id, 'unit' => $unit ?? 'all units', 'recipients' => $members->count(), 'emailed' => $mailed]);
        return [$id, $members->count(), $mailed];
    }

    /** Recall with counts; null when missing. */
    public static function find(int $id): ?object
    {
        if (!self::ready()) {
            return null;
        }
        $r = DB::table('recalls')->find($id);
        if ($r) {
            $c = DB::table('recall_responses')->where('recall_id', $id)->selectRaw("COALESCE(answer, 'none') as a, COUNT(*) as n")->groupBy(DB::raw("COALESCE(answer, 'none')"))->pluck('n', 'a');
            $r->coming = (int) ($c['coming'] ?? 0);
            $r->cannot = (int) ($c['cannot'] ?? 0);
            $r->waiting = (int) ($c['none'] ?? 0);
        }
        return $r;
    }

    public static function responses(int $id): Collection
    {
        return DB::table('recall_responses as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('r.recall_id', $id)
            ->orderByRaw("CASE WHEN r.answer IS NULL THEN 0 WHEN r.answer = 'cannot' THEN 1 ELSE 2 END")->orderBy('u.name')
            ->get(['r.*', 'u.name', 'u.rank', 'u.mother_unit', 'u.serial_number']);
    }
}
