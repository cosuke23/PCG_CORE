<?php

namespace App\Http\Controllers;

use App\Models\UnitUser;
use App\Support\LeaveActions;
use App\Support\LeaveCalendar;
use App\Support\LeaveCredits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Leave credits for unit checkers (owner request 2026-11-20): balances of the
 * unit's members, each member's history, and the three ways a checker changes
 * credits: the opening balance of ordinary leave credits, RNR days earned after
 * duty, and adjustments with a reason. Same scope as leave approval: own unit
 * by users.mother_unit, M1 all units. Every change is audited.
 */
class UnitLeaveCreditController extends Controller
{
    private const IMPORT_VIEW = 'unit.leave-credits-import';
    private const IMPORT_ROUTE = 'unit.leave.credits.import';
    private const CREDITS_ROUTE = 'unit.leave.credits';

    /** May this checker manage the member's credits? Own unit by mother_unit; M1 every unit. */
    private function canManage(Request $request, object $m): bool
    {
        return $this->isM1($request)
            || strcasecmp(trim((string) $m->mother_unit), trim((string) $request->session()->get('unit_name', ''))) === 0;
    }

    private function by(Request $request): array
    {
        return ['type' => 'unit', 'id' => (int) $request->session()->get('unit_user_id'),
            'name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email'))];
    }

    private function manageableMembers(Request $request)
    {
        $unit = $this->isM1($request) ? trim((string) $request->query('unit', '')) : trim((string) $request->session()->get('unit_name', ''));
        return DB::table('users')->whereNotNull('serial_number')->where('serial_number', '<>', '')
            ->when($unit !== '', fn ($q) => $q->whereRaw('LOWER(TRIM(mother_unit)) = ?', [mb_strtolower($unit)]))
            ->orderBy('mother_unit')->orderBy('name')->get(['id', 'name', 'rank', 'serial_number', 'mother_unit']);
    }

    private function isM1(Request $request): bool
    {
        return $request->session()->get('unit_role') === UnitUser::ROLE_M1;
    }

    /** The member, if this checker may manage their credits. */
    private function member(Request $request, int $userId): object
    {
        $u = DB::table('users')->where('id', $userId)->first(['id', 'name', 'rank', 'serial_number', 'mother_unit']);
        if (!$u || trim((string) $u->serial_number) === '') {
            abort(404);
        }
        $own = strcasecmp(trim((string) $u->mother_unit), trim((string) $request->session()->get('unit_name', ''))) === 0;
        if (!$this->isM1($request) && !$own) {
            abort(403);
        }
        return $u;
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $unitFilter = $this->isM1($request) ? trim((string) $request->query('unit', '')) : '';
        $members = DB::table('users')->whereNotNull('serial_number')->where('serial_number', '<>', '')
            ->select('id', 'name', 'rank', 'serial_number', 'mother_unit')->orderBy('name');
        if (!$this->isM1($request)) {
            $members->whereRaw('LOWER(TRIM(mother_unit)) = ?', [mb_strtolower(trim((string) $request->session()->get('unit_name', '')))]);
        }
        if ($unitFilter !== '') {
            $members->whereRaw('LOWER(TRIM(mother_unit)) = ?', [mb_strtolower($unitFilter)]);
        }
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $members->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('serial_number', 'like', $like));
        }
        $members = $members->paginate(40)->withQueryString();
        $members->getCollection()->transform(function ($m) {
            $m->credits = LeaveCredits::balances((int) $m->id);
            return $m;
        });
        $notSet = $members->getCollection()->filter(fn ($m) => !$m->credits['annual']['set'])->count();

        return view('unit.leave-credits', [
            'members' => $members, 'q' => $q, 'isM1' => $this->isM1($request), 'notSet' => $notSet,
            'unitFilter' => $unitFilter,
            'units' => $this->isM1($request)
                ? DB::table('users')->whereNotNull('mother_unit')->where('mother_unit', '<>', '')->whereNotNull('serial_number')->where('serial_number', '<>', '')
                    ->distinct()->orderBy('mother_unit')->pluck('mother_unit')
                : collect(),
            'pools' => LeaveCredits::POOLS,
        ]);
    }

    public function show(Request $request, int $user)
    {
        $m = $this->member($request, $user);
        return view('unit.leave-credits-member', [
            'm' => $m,
            'credits' => LeaveCredits::balances((int) $m->id),
            'histories' => collect(array_keys(LeaveCredits::POOLS))->mapWithKeys(fn ($p) => [$p => LeaveCredits::history((int) $m->id, $p)])->all(),
            'pools' => LeaveCredits::POOLS,
            'today' => LeaveCalendar::today()->toDateString(),
        ]);
    }

    public function store(Request $request, int $user)
    {
        $m = $this->member($request, $user);
        $data = $request->validate(LeaveActions::creditRules($request->input('action')), LeaveActions::creditMessages());
        $by = ['type' => 'unit', 'id' => (int) $request->session()->get('unit_user_id'),
            'name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email'))];
        [$ok, $result] = LeaveActions::credit($m, $data, $by, $request->ip());
        if (!$ok) {
            return back()->withInput()->withErrors($result);
        }
        return redirect()->route('unit.leave.credits.show', $m->id)->with('success', $result);
    }

    // ---------------------------------------------------------------- import opening balances (CSV)

    public function importForm(Request $request)
    {
        return view(self::IMPORT_VIEW, $this->importLinks() + ['result' => null]);
    }

    public function importPreview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:1024'], ['file.mimes' => 'Upload a CSV file (save the sheet as CSV first).']);
        $result = \App\Support\LeaveCreditImport::check((string) file_get_contents($request->file('file')->getRealPath()),
            fn ($m) => $this->canManage($request, $m));
        $token = bin2hex(random_bytes(16));
        $request->session()->put('leave_import', ['token' => $token, 'rows' => $result['rows']]);
        return view(self::IMPORT_VIEW, $this->importLinks() + ['result' => $result, 'token' => $token]);
    }

    public function importConfirm(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|size:32']);
        $saved = $request->session()->pull('leave_import');
        if (!$saved || !hash_equals((string) $saved['token'], $data['token'])) {
            return redirect()->route(self::IMPORT_ROUTE)->with('error', 'That preview has expired. Upload the file again.');
        }
        // the scope is checked again for every row at import time
        $rows = array_values(array_filter($saved['rows'], function ($r) use ($request) {
            if ($r['error'] !== null || empty($r['user_id'])) {
                return false;
            }
            $m = DB::table('users')->where('id', $r['user_id'])->first(['id', 'mother_unit', 'serial_number']);
            return $m && $this->canManage($request, $m);
        }));
        $n = \App\Support\LeaveCreditImport::apply($rows, $this->by($request), $request->ip());
        return redirect()->route(self::CREDITS_ROUTE)->with('success', $n . ' opening ' . ($n === 1 ? 'balance' : 'balances') . ' imported.');
    }

    public function importTemplate(Request $request)
    {
        $csv = \App\Support\LeaveCreditImport::template($this->manageableMembers($request));
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="leave-credits-' . \App\Support\LeaveCalendar::today()->toDateString() . '.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function importLinks(): array
    {
        return [
            'uploadAction' => route(self::IMPORT_ROUTE),
            'confirmAction' => route(self::IMPORT_ROUTE . '.confirm'),
            'templateUrl' => route(self::IMPORT_ROUTE . '.template'),
            'backUrl' => route(self::CREDITS_ROUTE),
        ];
    }
}
