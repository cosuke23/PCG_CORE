<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Services\OrgScope;
use App\Support\AdminPermissions;
use App\Support\LeaveActions;
use App\Support\LeaveCalendar;
use App\Support\LeaveCredits;
use App\Support\LeaveTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Leave in the admin console (owner request 2026-11-20): every request and the
 * leave calendar (leave.view), deciding requests (leave.approve) and managing
 * credits (leave.credits), for the members in the admin's command/unit tree
 * (OrgScope; organisation-wide admins see everyone and can filter by unit).
 * The rules are the unit checkers' (App\Support\LeaveActions).
 */
class AdminLeaveController extends Controller
{
    private const STATUSES = ['pending' => 'Waiting', 'approved' => 'Approved', 'declined' => 'Declined', 'cancelled' => 'Cancelled', 'all' => 'All'];

    public function __construct(private OrgScope $scope)
    {
    }

    /** Normalised serials of the members this admin may see; null = everyone. */
    private function serials(): ?array
    {
        if ($this->scope->isOrgWide()) {
            return null;
        }
        return $this->scope->personnelQuery()->pluck('serial_number')
            ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
    }

    private function inScope(?string $serial): bool
    {
        $serials = $this->serials();
        return $serials === null || in_array(Personnel::normalizeSerial($serial), $serials, true);
    }

    private function by(Request $request): array
    {
        return ['type' => 'admin', 'id' => $request->session()->get('admin_user_id'),
            'name' => (string) ($request->session()->get('admin_name') ?: $request->session()->get('admin_email'))];
    }

    /** Units members of this scope belong to (users.mother_unit), for the filter. */
    private function units(?array $serials)
    {
        return DB::table('users')->whereNotNull('mother_unit')->where('mother_unit', '<>', '')
            ->when($serials !== null, fn ($q) => $q->whereIn(DB::raw('UPPER(TRIM(serial_number))'), $serials ?: ['__none__']))
            ->distinct()->orderBy('mother_unit')->pluck('mother_unit');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => 'nullable|in:' . implode(',', array_keys(self::STATUSES)),
            'unit' => 'nullable|string|max:150',
            'q' => 'nullable|string|max:100',
            'month' => 'nullable|string|max:7',
        ]);
        $status = $data['status'] ?? 'pending';
        $unit = trim($data['unit'] ?? '');
        $q = trim($data['q'] ?? '');
        $serials = $this->serials();
        $month = LeaveCalendar::month($data['month'] ?? null);
        [$from, $to] = LeaveCalendar::range($month);

        $base = DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
            ->when($serials !== null, fn ($w) => $w->whereIn(DB::raw('UPPER(TRIM(l.serial_number))'), $serials ?: ['__none__']))
            ->when($unit !== '', fn ($w) => $w->whereRaw('LOWER(TRIM(u.mother_unit)) = ?', [mb_strtolower($unit)]));

        $counts = (clone $base)->selectRaw('LOWER(l.status) as s, COUNT(*) as n')->groupBy(DB::raw('LOWER(l.status)'))->pluck('n', 's');
        $today = LeaveCalendar::today()->toDateString();
        $awayToday = (clone $base)->whereRaw('LOWER(l.status) = ?', ['approved'])
            ->where('l.start_date', '<=', $today)->where('l.end_date', '>=', $today)->count();

        $list = (clone $base)
            ->when($status !== 'all', fn ($w) => $w->whereRaw('LOWER(l.status) = ?', [$status]))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('u.name', 'like', '%' . $q . '%')->orWhere('l.serial_number', 'like', '%' . $q . '%')))
            ->select('l.*', 'u.name', 'u.rank', 'u.mother_unit')
            ->orderBy($status === 'pending' ? 'l.start_date' : 'l.updated_at', $status === 'pending' ? 'asc' : 'desc')->orderBy('l.id')
            ->paginate(25)->withQueryString();
        if ($status === 'pending') {
            $list->getCollection()->transform(function ($r) {
                $pool = LeaveCredits::poolFor($r->leave_type);
                $r->credit = $pool ? LeaveCredits::balances((int) $r->user_id)[$pool] : null;
                $r->shortfall = $pool ? LeaveCredits::shortfall((int) $r->user_id, $r->leave_type, (float) $r->days, (int) $r->id) : null;
                return $r;
            });
        }

        $rows = LeaveCalendar::approved($unit !== '' ? $unit : null, $from, $to, $serials);
        $byDay = LeaveCalendar::byDay($rows, $from, $to, null, true);
        $query = array_filter(['unit' => $unit, 'status' => $status === 'pending' ? null : $status, 'q' => $q]);

        return view('admin.leave.index', [
            'status' => $status, 'statuses' => self::STATUSES, 'counts' => $counts, 'awayToday' => $awayToday,
            'list' => $list, 'unit' => $unit, 'units' => $this->units($serials), 'q' => $q,
            'month' => $month, 'weeks' => LeaveCalendar::grid($month), 'byDay' => $byDay, 'legend' => LeaveCalendar::legend($byDay),
            'prev' => $month->copy()->subMonth()->format('Y-m'), 'next' => $month->copy()->addMonth()->format('Y-m'),
            'query' => $query, 'canApprove' => AdminPermissions::can('leave.approve'), 'canCredits' => AdminPermissions::can('leave.credits'),
            'scopeName' => $this->scope->isOrgWide() ? 'all units' : (optional($this->scope->scopeUnit())->name ?? 'your command'),
        ]);
    }

    public function review(Request $request, int $id)
    {
        $data = $request->validate(LeaveActions::reviewRules(), LeaveActions::reviewMessages());
        $leave = LeaveActions::request($id);
        if (!$leave) {
            abort(404);
        }
        if (!$this->inScope($leave->serial_number)) {
            abort(403);
        }
        [$ok, $message] = LeaveActions::decide($leave, $data['decision'], $data['note'] ?? null, $this->by($request), $request->ip());
        return back()->with($ok ? 'success' : 'error', $message);
    }

    public function credits(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'unit' => 'nullable|string|max:150']);
        $q = trim($data['q'] ?? '');
        $unit = trim($data['unit'] ?? '');
        $serials = $this->serials();
        $members = DB::table('users')->whereNotNull('serial_number')->where('serial_number', '<>', '')
            ->when($serials !== null, fn ($w) => $w->whereIn(DB::raw('UPPER(TRIM(serial_number))'), $serials ?: ['__none__']))
            ->when($unit !== '', fn ($w) => $w->whereRaw('LOWER(TRIM(mother_unit)) = ?', [mb_strtolower($unit)]))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%' . $q . '%')->orWhere('serial_number', 'like', '%' . $q . '%')))
            ->select('id', 'name', 'rank', 'serial_number', 'mother_unit')->orderBy('name')
            ->paginate(40)->withQueryString();
        $members->getCollection()->transform(function ($m) {
            $m->credits = LeaveCredits::balances((int) $m->id);
            return $m;
        });

        return view('admin.leave.credits', [
            'members' => $members, 'q' => $q, 'unit' => $unit, 'units' => $this->units($serials),
            'pools' => LeaveCredits::POOLS,
            'notSet' => $members->getCollection()->filter(fn ($m) => !$m->credits['annual']['set'])->count(),
        ]);
    }

    private function member(int $userId): object
    {
        $m = DB::table('users')->where('id', $userId)->first(['id', 'name', 'rank', 'serial_number', 'mother_unit']);
        if (!$m || trim((string) $m->serial_number) === '') {
            abort(404);
        }
        if (!$this->inScope($m->serial_number)) {
            abort(403);
        }
        return $m;
    }

    public function creditsShow(Request $request, int $user)
    {
        $m = $this->member($user);
        return view('admin.leave.credits-member', [
            'm' => $m,
            'credits' => LeaveCredits::balances((int) $m->id),
            'histories' => collect(array_keys(LeaveCredits::POOLS))->mapWithKeys(fn ($p) => [$p => LeaveCredits::history((int) $m->id, $p)])->all(),
            'pools' => LeaveCredits::POOLS,
            'today' => LeaveCalendar::today()->toDateString(),
            'canEdit' => AdminPermissions::can('leave.credits'),
            'recent' => DB::table('leave_requests')->where('user_id', $m->id)->orderByDesc('start_date')->limit(10)->get(),
        ]);
    }

    public function creditsStore(Request $request, int $user)
    {
        $m = $this->member($user);
        $data = $request->validate(LeaveActions::creditRules($request->input('action')), LeaveActions::creditMessages());
        [$ok, $result] = LeaveActions::credit($m, $data, $this->by($request), $request->ip());
        if (!$ok) {
            return back()->withInput()->withErrors($result);
        }
        return redirect()->route('admin.leave.credits.show', $m->id)->with('success', $result);
    }
}
