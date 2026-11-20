<?php

namespace App\Http\Controllers;

use App\Models\UnitUser;
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
        $data = $request->validate([
            'action' => ['required', Rule::in(['opening', 'grant', 'adjustment'])],
            'pool' => ['required_if:action,adjustment', 'nullable', Rule::in(array_keys(LeaveCredits::POOLS))],
            'days' => ['required', 'numeric', 'between:-365,365'],
            'effective_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . LeaveCalendar::today()->copy()->addDays(31)->toDateString()],
            'note' => ['nullable', 'string', 'max:500', Rule::requiredIf(in_array($request->input('action'), ['grant', 'adjustment'], true))],
        ], [
            'note.required' => 'Say what the credit is for, e.g. the deployment or the reason for the adjustment.',
        ]);

        $days = round((float) $data['days'], 2);
        [$pool, $kind] = match ($data['action']) {
            'opening' => ['annual', 'opening'],
            'grant' => ['rnr', 'grant'],
            default => [$data['pool'], 'adjustment'],
        };
        if ($kind === 'opening' && $days < 0) {
            return back()->withInput()->withErrors(['days' => 'An opening balance cannot be negative.']);
        }
        if ($kind === 'grant' && $days <= 0) {
            return back()->withInput()->withErrors(['days' => 'Add at least half a day of RNR.']);
        }
        if ($kind === 'adjustment' && abs($days) < 0.01) {
            return back()->withInput()->withErrors(['days' => 'An adjustment needs a number of days other than 0.']);
        }

        $by = ['type' => 'unit', 'id' => (int) $request->session()->get('unit_user_id'),
            'name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email'))];
        $entryId = LeaveCredits::post((int) $m->id, (string) $m->serial_number, $pool, $kind, $days, $data['effective_date'],
            $data['note'] ?? null, null, $by);

        Log::channel('audit')->info('leave_credit.' . $kind, [
            'entry_id' => $entryId, 'member_user_id' => $m->id, 'serial_number' => $m->serial_number, 'pool' => $pool,
            'days' => $days, 'effective_date' => $data['effective_date'], 'unit_user_id' => $by['id'], 'ip' => $request->ip(),
        ]);

        $what = match ($kind) {
            'opening' => 'Opening balance of ' . LeaveCredits::fmt($days) . ' days set',
            'grant' => LeaveCredits::fmt($days) . ' RNR days added',
            default => 'Adjustment of ' . ($days > 0 ? '+' : '') . LeaveCredits::fmt($days) . ' days to ' . strtolower(LeaveCredits::label($pool)) . ' recorded',
        };
        return redirect()->route('unit.leave.credits.show', $m->id)->with('success', $what . ' for ' . $m->name . '.');
    }
}
