<?php

namespace App\Http\Controllers;

use App\Models\UnitUser;
use App\Support\DutyRoster;
use App\Support\MemberNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/** Duty roster (App\Support\DutyRoster): unit checkers (own unit; M1 any unit) and members (their own duties). */
class DutyRosterController extends Controller
{
    private function isM1(Request $request): bool
    {
        return $request->session()->get('unit_role') === UnitUser::ROLE_M1;
    }

    /** The unit this checker is working on. */
    private function unit(Request $request): string
    {
        $own = trim((string) $request->session()->get('unit_name', ''));
        return $this->isM1($request) ? trim((string) $request->input('unit', $request->query('unit', $own))) : $own;
    }

    public function index(Request $request)
    {
        $unit = $this->unit($request);
        $monday = DutyRoster::weekStart($request->query('week'));
        $members = DB::table('users')->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])
            ->whereRaw('LOWER(TRIM(mother_unit)) = ?', [mb_strtolower($unit)])->orderBy('name')->get(['id', 'name', 'rank']);
        return view('unit.roster', [
            'unit' => $unit, 'monday' => $monday, 'week' => DutyRoster::week($unit, $monday), 'members' => $members,
            'isM1' => $this->isM1($request), 'ready' => DutyRoster::ready(),
            'units' => $this->isM1($request) ? DB::table('users')->whereNotNull('mother_unit')->where('mother_unit', '<>', '')->distinct()->orderBy('mother_unit')->pluck('mother_unit') : collect(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(DutyRoster::ready(), 409, 'Run the database update first.');
        $data = $request->validate([
            'user_id' => 'required|integer',
            'duty_date' => 'required|date_format:Y-m-d|after_or_equal:' . now('Asia/Manila')->subDays(7)->toDateString() . '|before_or_equal:' . now('Asia/Manila')->addDays(120)->toDateString(),
            'shift' => ['required', Rule::in(array_keys(DutyRoster::SHIFTS))],
            'post' => 'required|string|max:100',
            'note' => 'nullable|string|max:200',
        ]);
        $unit = $this->unit($request);
        $m = DB::table('users')->where('id', $data['user_id'])->whereRaw('LOWER(TRIM(status)) = ?', ['approved'])->first(['id', 'name', 'rank', 'serial_number', 'mother_unit']);
        abort_unless($m, 404);
        abort_unless(strcasecmp(trim((string) $m->mother_unit), $unit) === 0, 403, 'This member is not in the unit.');
        if ($why = DutyRoster::awayReason($m, $data['duty_date'])) {
            return back()->withInput()->withErrors(['user_id' => "{$m->name} is {$why} on " . Carbon::parse($data['duty_date'])->format('M j') . '.']);
        }
        $clash = DB::table('duty_assignments')->where('user_id', $m->id)->where('duty_date', $data['duty_date'])
            ->where(fn ($q) => $q->where('shift', $data['shift'])->orWhere('shift', '24h')->orWhere(fn ($w) => $w->whereRaw('? = ?', [$data['shift'], '24h'])))->exists();
        if ($clash) {
            return back()->withInput()->withErrors(['shift' => "{$m->name} already has a duty in that shift on that day."]);
        }
        DB::table('duty_assignments')->insert(['unit_name' => trim((string) $m->mother_unit), 'duty_date' => $data['duty_date'], 'shift' => $data['shift'],
            'post' => trim($data['post']), 'user_id' => $m->id, 'serial_number' => $m->serial_number, 'note' => $data['note'] ?? null,
            'created_by_name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email')),
            'created_by_id' => (int) $request->session()->get('unit_user_id'), 'created_at' => now(), 'updated_at' => now()]);
        $when = Carbon::parse($data['duty_date'])->format('D M j') . ', ' . DutyRoster::SHIFTS[$data['shift']];
        MemberNotifications::send((int) $m->id, 'duty.assigned', 'Duty: ' . trim($data['post']), $when, '/my-duties');
        Log::channel('audit')->info('duty.assigned', ['member_user_id' => $m->id, 'serial_number' => $m->serial_number, 'date' => $data['duty_date'], 'shift' => $data['shift']]);
        return redirect()->route('unit.roster', ['week' => DutyRoster::weekStart($data['duty_date'])->toDateString(), 'unit' => $this->isM1($request) ? $unit : null])
            ->with('success', "{$m->name} rostered: " . trim($data['post']) . ", $when.");
    }

    public function destroy(Request $request, int $id)
    {
        $d = DutyRoster::ready() ? DB::table('duty_assignments')->find($id) : null;
        abort_unless($d, 404);
        abort_unless($this->isM1($request) || strcasecmp(trim($d->unit_name), $this->unit($request)) === 0, 403);
        DB::table('duty_assignments')->where('id', $id)->delete();
        MemberNotifications::send((int) $d->user_id, 'duty.removed', 'Duty removed: ' . $d->post, Carbon::parse($d->duty_date)->format('D M j') . ', ' . (DutyRoster::SHIFTS[$d->shift] ?? $d->shift), '/my-duties');
        Log::channel('audit')->info('duty.removed', ['member_user_id' => $d->user_id, 'date' => $d->duty_date, 'shift' => $d->shift]);
        return back()->with('success', 'Duty removed; the member was notified.');
    }

    public function mine(Request $request)
    {
        return view('applicant.duties', ['list' => DutyRoster::upcomingFor((int) $request->user()->id)]);
    }
}
