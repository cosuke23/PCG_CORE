<?php

namespace App\Http\Controllers;

use App\Support\AdminAudit;
use App\Support\AdminPermissions;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-enlistment module: everyone who registered as a non-officer
 * (users.registration_type = non_officer), ranked by ETE (end of term of
 * enlistment). The default view lists those whose ETE falls within the next
 * 12 months; other views show ETE already passed, missing ETE, or everyone.
 *
 * Read-only: nothing here changes personnel data. Views and exports are
 * written to the admin audit log. Dates are Philippine dates (Asia/Manila).
 */
class ReenlistmentController extends Controller
{
    public const WINDOWS = [
        'due' => 'ETE within the coming months',
        'overdue' => 'ETE already passed',
        'no_ete' => 'No ETE recorded',
        'all' => 'All non-officers',
    ];

    public const STATUSES = [
        'active' => 'Approved + pending',
        'approved' => 'Approved only',
        'pending' => 'Pending only',
        'all' => 'All, including rejected',
    ];

    public function index(Request $request)
    {
        $f = $this->filters($request);
        $today = $this->today();

        $rows = $this->query($f, $today)->paginate(50)->withQueryString();
        $rows->getCollection()->transform(fn ($r) => $this->decorate($r, $today));

        AdminAudit::record('reenlistment.list', $f['window'], ['rows' => $rows->total()]);

        return view('admin.reenlistment.index', [
            'rows' => $rows,
            'filters' => $f,
            'counts' => $this->counts($f, $today),
            'units' => $this->base(array_merge($f, ['unit' => null, 'q' => null]), false)->whereNotNull('u.mother_unit')->where('u.mother_unit', '!=', '')
                ->distinct()->orderBy('u.mother_unit')->pluck('u.mother_unit'),
            'today' => $today,
            'canExport' => AdminPermissions::can('reenlistment.export'),
        ]);
    }

    public function export(Request $request)
    {
        $f = $this->filters($request);
        $today = $this->today();
        $rows = $this->query($f, $today)->limit(20000)->get()->map(fn ($r) => $this->decorate($r, $today));

        AdminAudit::record('reenlistment.csv', $f['window'], ['rows' => $rows->count()]);

        $name = 'mepcom-reenlistment-' . $f['window'] . '-' . now('Asia/Manila')->format('Ymd-His') . '.csv';
        $columns = ['Serial number', 'Rank', 'Name', 'Email', 'Command / unit', 'Unit assignment', 'Date of appointment', 'ETE', 'Days to ETE', 'Account status', 'Re-enlistment form'];

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents correctly
            fputcsv($out, $columns, ',', '"', '');
            foreach ($rows as $r) {
                $line = [$r->serial_number, $r->rank, $r->name, $r->email, $r->mother_unit, $r->unit_assignment,
                    $r->date_of_appointment, $r->ete, $r->days_left, $r->status, $r->form_submitted ? 'Submitted' : 'Not yet'];
                // neutralise spreadsheet formulas in exported text
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $line), ',', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function show(int $id)
    {
        $today = $this->today();
        $person = $this->base(['status' => 'all', 'q' => null, 'unit' => null], true)->where('u.id', $id)->first();
        abort_unless($person, 404);
        $person = $this->decorate($person, $today);

        $form = null;
        $personal = null;
        if (Schema::hasTable('reinlist_basic_info')) {
            $form = DB::table('reinlist_basic_info')->where('serial_number', $person->serial_number)->first();
        }
        if (Schema::hasTable('reinlist_personal_info')) {
            $personal = DB::table('reinlist_personal_info')->where('serial_number', $person->serial_number)
                ->first(['civil_status', 'contact_number', 'email', 'home_address', 'updated_at']);
        }
        $personnelId = Schema::hasTable('personnel')
            ? DB::table('personnel')->where('serial_number', $person->serial_number)->value('id') : null;

        AdminAudit::record('reenlistment.view', (string) $person->serial_number);

        return view('admin.reenlistment.show', compact('person', 'form', 'personal', 'personnelId', 'today'));
    }

    // ------------------------------------------------------------------ helpers

    private function today(): Carbon
    {
        return now('Asia/Manila')->startOfDay();
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'window' => 'nullable|in:' . implode(',', array_keys(self::WINDOWS)),
            'months' => 'nullable|in:3,6,12',
            'status' => 'nullable|in:' . implode(',', array_keys(self::STATUSES)),
            'unit' => 'nullable|string|max:255',
            'q' => 'nullable|string|max:100',
        ]);

        return [
            'window' => $data['window'] ?? 'due',
            'months' => (int) ($data['months'] ?? 12),
            'status' => $data['status'] ?? 'active',
            'unit' => $data['unit'] ?? null,
            'q' => isset($data['q']) ? trim($data['q']) : null,
        ];
    }

    /** Non-officer registrations with the status, unit and search filters. */
    private function base(array $f, bool $withForm = true): Builder
    {
        $q = DB::table('users as u')
            ->whereRaw("LOWER(TRIM(COALESCE(u.registration_type, ''))) = 'non_officer'");

        match ($f['status'] ?? 'active') {
            'approved' => $q->whereRaw("LOWER(COALESCE(u.status, '')) = 'approved'"),
            'pending' => $q->whereRaw("LOWER(COALESCE(u.status, '')) = 'pending'"),
            'all' => null,
            default => $q->whereRaw("LOWER(COALESCE(u.status, '')) <> 'rejected'"),
        };

        if (!empty($f['unit'])) {
            $q->where('u.mother_unit', $f['unit']);
        }
        if (!empty($f['q'])) {
            $like = '%' . $f['q'] . '%';
            $q->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.serial_number', 'like', $like)->orWhere('u.email', 'like', $like));
        }

        if ($withForm) {
            $q->select('u.id', 'u.name', 'u.email', 'u.rank', 'u.serial_number', 'u.mother_unit', 'u.unit_assignment',
                'u.date_of_appointment', 'u.ete', 'u.status', 'u.created_at');
            if (Schema::hasTable('reinlist_basic_info')) {
                $q->selectRaw('EXISTS (SELECT 1 FROM reinlist_basic_info r WHERE r.serial_number = u.serial_number) AS form_submitted');
            } else {
                $q->selectRaw('0 AS form_submitted');
            }
        }

        return $q;
    }

    private function withWindow(Builder $q, string $window, int $months, Carbon $today): Builder
    {
        $from = $today->toDateString();
        $to = $today->copy()->addMonthsNoOverflow($months)->toDateString();

        return match ($window) {
            'overdue' => $q->whereNotNull('u.ete')->where('u.ete', '<', $from),
            'no_ete' => $q->whereNull('u.ete'),
            'all' => $q,
            default => $q->whereNotNull('u.ete')->whereBetween('u.ete', [$from, $to]),
        };
    }

    private function query(array $f, Carbon $today): Builder
    {
        return $this->withWindow($this->base($f), $f['window'], $f['months'], $today)
            ->orderByRaw('CASE WHEN u.ete IS NULL THEN 1 ELSE 0 END')
            ->orderBy('u.ete')
            ->orderBy('u.name');
    }

    private function counts(array $f, Carbon $today): array
    {
        $count = fn (string $window, int $months = 12) => $this->withWindow($this->base($f, false), $window, $months, $today)->count();

        return [
            'due3' => $count('due', 3),
            'due6' => $count('due', 6),
            'due12' => $count('due', 12),
            'overdue' => $count('overdue'),
            'no_ete' => $count('no_ete'),
            'all' => $count('all'),
        ];
    }

    /** Adds days_left and a label/tone for the ETE badge. */
    private function decorate(object $r, Carbon $today): object
    {
        $r->days_left = null;
        $r->ete_label = 'No ETE';
        $r->ete_tone = '';
        if (!empty($r->ete)) {
            $ete = Carbon::parse($r->ete, 'Asia/Manila')->startOfDay();
            $r->days_left = (int) $today->diffInDays($ete, false);
            [$r->ete_label, $r->ete_tone] = match (true) {
                $r->days_left < 0 => ['Passed ' . abs($r->days_left) . ' days ago', 'danger'],
                $r->days_left === 0 => ['Today', 'danger'],
                $r->days_left <= 90 => [$r->days_left . ' days left', 'danger'],
                $r->days_left <= 180 => [$r->days_left . ' days left', 'warning'],
                $r->days_left <= 366 => [$r->days_left . ' days left', 'info'],
                default => [$r->days_left . ' days left', ''],
            };
        }
        $r->form_submitted = (bool) ($r->form_submitted ?? false);

        return $r;
    }
}
