<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Services\OrgScope;
use App\Support\AdminAudit;
use App\Models\User;
use App\Support\SoiLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Submitted SOIs (owner decisions 2026-11-09): the Adjutant and the HR admin of
 * the member's command/unit (OrgScope, like the Adjutant module) and the super
 * admin see which members submitted their SOI and can reopen one, with a
 * reason, so the member can correct it and submit again. Audited.
 *
 * They can also edit the six SOI steps themselves (edit/save): the member's own
 * form in the admin layout, saved by the member's step savers (TempRegistrationController)
 * with the member as the request user. Editing never reopens or locks the SOI,
 * and every saved step is audited (soi.edited).
 */
class SoiSubmissionController extends Controller
{
    public function __construct(private OrgScope $scope)
    {
    }

    /** Approved members this admin may see: everyone org-wide, else those assigned in the admin's unit tree. */
    private function members()
    {
        $q = DB::table('users')->whereRaw('LOWER(TRIM(status)) = ?', ['approved']);
        if (!$this->scope->isOrgWide()) {
            $serials = $this->scope->personnelQuery()->pluck('serial_number')
                ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
            $q->whereIn(DB::raw('UPPER(TRIM(serial_number))'), $serials ?: ['__none__']);
        }

        return $q;
    }

    public function index(Request $request)
    {
        abort_unless(SoiLock::enabled(), 409, 'Run the database update (deploy with -Migrate) first.');
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'show' => 'nullable|in:submitted,open,all',
        ]);
        $q = trim($data['q'] ?? '');
        $show = $data['show'] ?? 'submitted';

        $base = $this->members();
        $counts = [
            'submitted' => (clone $base)->whereNotNull('soi_submitted_at')->count(),
            'open' => (clone $base)->whereNull('soi_submitted_at')->count(),
        ];
        $members = $base
            ->when($show === 'submitted', fn ($w) => $w->whereNotNull('soi_submitted_at'))
            ->when($show === 'open', fn ($w) => $w->whereNull('soi_submitted_at'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%' . $q . '%')->orWhere('serial_number', 'like', '%' . $q . '%')))
            ->orderByDesc('soi_submitted_at')->orderBy('name')
            ->paginate(25, ['id', 'name', 'rank', 'serial_number', 'mother_unit', 'soi_submitted_at'])
            ->withQueryString();

        return view('admin.soi.index', compact('members', 'counts', 'show', 'q'));
    }

    public function reopen(Request $request, int $user)
    {
        abort_unless(SoiLock::enabled(), 409, 'Run the database update (deploy with -Migrate) first.');
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $m = $this->members()->where('id', $user)->first();
        abort_unless($m, 404); // unknown, not approved, or outside this admin's command/unit

        if (empty($m->soi_submitted_at)) {
            return back()->with('info', 'The SOI of ' . $m->name . ' is already open for editing.');
        }
        DB::table('users')->where('id', $m->id)->update(['soi_submitted_at' => null]);
        AdminAudit::record('soi.reopened', 'users:' . $m->id, ['reason' => $data['reason'], 'submitted_at' => (string) $m->soi_submitted_at]);

        return back()->with('success', 'Reopened the SOI of ' . $m->name . '. They can correct it and must submit it again.')
            ->with('reopened', $m->id);
    }

    public function edit(int $user)
    {
        $m = $this->members()->where('id', $user)->first();
        abort_unless($m, 404); // unknown, not approved, or outside this admin's command/unit
        AdminAudit::record('soi.edit_opened', 'users:' . $m->id, []);

        return app(DashboardController::class)->soiPage(User::findOrFail($m->id), true)->with([
            'soiLayout' => 'layouts.admin',
            'adminEdit' => [
                'saveBase' => url('/admin/soi/' . $m->id . '/step'),
                'back' => route('admin.soi.index', ['show' => 'all', 'q' => $m->serial_number]),
            ],
        ]);
    }

    public function save(Request $request, int $user, int $step)
    {
        abort_unless($step >= 1 && $step <= 6, 404);
        $m = $this->members()->where('id', $user)->first();
        abort_unless($m, 404);
        $member = User::findOrFail($m->id);

        // the step savers take the id and serial from $request->user(): here, the member
        $request->setUserResolver(fn () => $member);
        $response = app(TempRegistrationController::class)->{'saveStep' . $step}($request);

        if ($response->getStatusCode() < 300) {
            AdminAudit::record('soi.edited', 'users:' . $m->id, ['step' => $step]);
        }

        return $response;
    }
}
