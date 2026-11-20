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
        $members = DB::table('users')->whereNotNull('serial_number')->where('serial_number', '<>', '')
            ->select('id', 'name', 'rank', 'serial_number', 'mother_unit')->orderBy('name');
        if (!$this->isM1($request)) {
            $members->whereRaw('LOWER(TRIM(mother_unit)) = ?', [mb_strtolower(trim((string) $request->session()->get('unit_name', '')))]);
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
}
