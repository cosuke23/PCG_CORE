<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Services\OrgScope;
use App\Support\AdminAudit;
use App\Support\CgAwardTypes;
use App\Support\TableColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Adjutant module: records the Coast Guard parts of a member's SOI that members
 * no longer enter themselves (owner decision 2026-11-08):
 *   1.a Coast Guard Awards and Decorations -> reinlist_cg_awards_decorations
 *   3.a Trainings / Seminars, Coast Guard   -> reinlist_trainings (category coastguard)
 *
 * Members are approved registrations (users). An Adjutant limited to a
 * command/unit (admin_users.org_unit_id) sees only members whose serial number
 * has a current assignment there in the personnel master (OrgScope);
 * organisation-wide Adjutants see every approved member. Every change is
 * written to the admin audit log; only rows added through this module
 * (added_by set) can be removed here.
 */
class AdjutantController extends Controller
{
    public const TRAINING_TYPES = ['Advance Training', 'Strategic Training', 'PCG Admiral Staff Course'];

    public function __construct(private OrgScope $scope)
    {
    }

    /** Approved members the signed-in Adjutant may record for. */
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

    private function member(int $id): object
    {
        $m = $this->members()->where('id', $id)->first();
        abort_unless($m, 404); // unknown, not approved, or outside this Adjutant's command/unit

        return $m;
    }

    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $q = trim($data['q'] ?? '');

        $members = $this->members()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%' . $q . '%')->orWhere('serial_number', 'like', '%' . $q . '%')))
            ->orderBy('name')
            ->paginate(25, ['id', 'name', 'rank', 'serial_number', 'mother_unit', 'unit_assignment'])
            ->withQueryString();

        $serials = collect($members->items())->pluck('serial_number')->filter()->all();
        $count = fn ($table, $extra = null) => DB::table($table)->whereIn('serial_number', $serials ?: ['__none__'])
            ->when($extra, $extra)->select('serial_number', DB::raw('COUNT(*) as n'))->groupBy('serial_number')->pluck('n', 'serial_number');

        return view('admin.adjutant.index', [
            'members' => $members,
            'q' => $q,
            'awardCounts' => $count('reinlist_cg_awards_decorations'),
            'trainingCounts' => $count('reinlist_trainings', fn ($w) => $w->where('category', 'coastguard')),
            'scopeUnit' => $this->scope->scopeUnit(),
        ]);
    }

    public function show(int $user)
    {
        $m = $this->member($user);

        return view('admin.adjutant.show', [
            'member' => $m,
            'awards' => DB::table('reinlist_cg_awards_decorations')->where('serial_number', $m->serial_number)->orderByDesc('id')->get(),
            'trainings' => DB::table('reinlist_trainings')->where('serial_number', $m->serial_number)->where('category', 'coastguard')->orderByDesc('id')->get(),
            'trainingTypes' => self::TRAINING_TYPES,
            'trackAdder' => Schema::hasColumn('reinlist_trainings', 'added_by'),
        ]);
    }

    public function storeAward(Request $request, int $user)
    {
        $m = $this->member($user);
        $data = $request->validate([
            'type' => ['required', 'string', 'max:255', Rule::in(CgAwardTypes::all())],
            'authority' => 'required|string|max:255',
        ]);

        $id = DB::table('reinlist_cg_awards_decorations')->insertGetId(TableColumns::fit('reinlist_cg_awards_decorations', [
            'serial_number' => $m->serial_number,
            'applicant_id' => $m->id,
            'type' => trim($data['type']),
            'authority' => trim($data['authority']),
            'added_by' => session('admin_user_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ], true));
        AdminAudit::record('adjutant.award.added', 'reinlist_cg_awards_decorations:' . $id, ['member' => $m->id]);

        return redirect()->route('admin.adjutant.show', $m->id)->with('success', 'Award added to the SOI of ' . $m->name . '.');
    }

    public function storeTraining(Request $request, int $user)
    {
        $m = $this->member($user);
        $data = $request->validate([
            'type' => 'required|string|in:' . implode(',', self::TRAINING_TYPES),
            'course' => 'required|string|max:255',
            'authority' => 'nullable|string|max:255',
            'school' => 'nullable|string|max:255',
            'period' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $personnelId = DB::table('reinlist_personal_info')->where('serial_number', $m->serial_number)->value('personnel_id');
        $id = DB::table('reinlist_trainings')->insertGetId(TableColumns::fit('reinlist_trainings', [
            'personnel_id' => $personnelId,
            'serial_number' => $m->serial_number,
            'category' => 'coastguard',
            'type' => $data['type'],
            'course' => trim($data['course']),
            'authority' => $data['authority'] ?? null,
            'school' => $data['school'] ?? null,
            'period' => $data['period'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'added_by' => session('admin_user_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ], true));
        AdminAudit::record('adjutant.training.added', 'reinlist_trainings:' . $id, ['member' => $m->id]);

        return redirect()->route('admin.adjutant.show', $m->id)->with('success', 'Schooling added to the SOI of ' . $m->name . '.');
    }

    public function destroyAward(int $user, int $id)
    {
        return $this->destroy('reinlist_cg_awards_decorations', 'adjutant.award.removed', $user, $id, fn ($q) => $q);
    }

    public function destroyTraining(int $user, int $id)
    {
        return $this->destroy('reinlist_trainings', 'adjutant.training.removed', $user, $id, fn ($q) => $q->where('category', 'coastguard'));
    }

    /** Removes one row recorded through this module (added_by set) for this member. */
    private function destroy(string $table, string $action, int $user, int $id, \Closure $extra)
    {
        $m = $this->member($user);
        abort_unless(Schema::hasColumn($table, 'added_by'), 404);
        $row = $extra(DB::table($table)->where('id', $id)->where('serial_number', $m->serial_number)->whereNotNull('added_by'))->first();
        abort_unless($row, 404); // not this member's, or entered by the member before the module

        DB::table($table)->where('id', $id)->delete();
        AdminAudit::record($action, $table . ':' . $id, ['member' => $m->id]);

        return redirect()->route('admin.adjutant.show', $m->id)->with('success', 'Entry removed from the SOI of ' . $m->name . '.');
    }
}
