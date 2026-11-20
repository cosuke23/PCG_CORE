<?php

namespace App\Http\Controllers;

use App\Models\UnitUser;
use App\Support\Recalls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Emergency recall (App\Support\Recalls). Unit checkers send to their unit, M1
 * checkers to one unit or every unit; members answer on /recall/{id}.
 */
class RecallController extends Controller
{
    // ------------------------------------------------------------ unit checkers

    private function isM1(Request $request): bool
    {
        return $request->session()->get('unit_role') === UnitUser::ROLE_M1;
    }

    /** Recall the checker may see: own unit's; M1 all. */
    private function recallFor(Request $request, int $id): object
    {
        $r = Recalls::find($id);
        abort_unless($r, 404);
        $own = trim((string) $request->session()->get('unit_name', ''));
        abort_unless($this->isM1($request) || ($r->unit_name !== null && strcasecmp($r->unit_name, $own) === 0), 403);
        return $r;
    }

    public function unitIndex(Request $request)
    {
        $m1 = $this->isM1($request);
        $own = trim((string) $request->session()->get('unit_name', ''));
        $list = Recalls::ready() ? DB::table('recalls')->when(!$m1, fn ($q) => $q->whereRaw('LOWER(TRIM(unit_name)) = ?', [mb_strtolower($own)]))
            ->orderByDesc('id')->limit(30)->get()->map(fn ($r) => Recalls::find((int) $r->id)) : collect();
        return view('unit.recall', [
            'list' => $list, 'isM1' => $m1, 'own' => $own, 'ready' => Recalls::ready(),
            'units' => $m1 ? DB::table('users')->whereNotNull('mother_unit')->where('mother_unit', '<>', '')->distinct()->orderBy('mother_unit')->pluck('mother_unit') : collect(),
            'memberCount' => Recalls::ready() && !$m1 ? Recalls::members($own)->count() : null,
        ]);
    }

    public function unitSend(Request $request)
    {
        abort_unless(Recalls::ready(), 409, 'Run the database update first.');
        $m1 = $this->isM1($request);
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
            'report_to' => 'nullable|string|max:200',
            'unit' => [$m1 ? 'required' : 'nullable', 'string', 'max:150'],
            'confirm' => 'accepted',
        ], ['confirm.accepted' => 'Tick the box to confirm you want to recall these members.']);
        $unit = $m1 ? ($data['unit'] === '__all__' ? null : trim($data['unit'])) : trim((string) $request->session()->get('unit_name', ''));
        $by = ['type' => 'unit', 'id' => (int) $request->session()->get('unit_user_id'),
            'name' => (string) ($request->session()->get('unit_full_name') ?: $request->session()->get('unit_email'))];
        [$id, $n, $mailed] = Recalls::send($unit, trim($data['title']), trim($data['message']), $data['report_to'] ?? null, $by);
        return redirect()->route('unit.recall.show', $id)->with('success', "Recall sent to {$n} " . ($n === 1 ? 'member' : 'members') . " ({$mailed} by email; all get a portal notification and phone alert).");
    }

    public function unitShow(Request $request, int $id)
    {
        $r = $this->recallFor($request, $id);
        return view('unit.recall-show', ['r' => $r, 'responses' => Recalls::responses($id)]);
    }

    public function unitClose(Request $request, int $id)
    {
        $r = $this->recallFor($request, $id);
        DB::table('recalls')->where('id', $r->id)->whereNull('closed_at')->update(['closed_at' => now(), 'updated_at' => now()]);
        Log::channel('audit')->info('recall.closed', ['recall_id' => $r->id]);
        return back()->with('success', 'Recall closed. Members can no longer answer it.');
    }

    // ------------------------------------------------------------ members

    private function myResponse(Request $request, int $id): array
    {
        $r = Recalls::find($id);
        abort_unless($r, 404);
        $resp = DB::table('recall_responses')->where('recall_id', $id)->where('user_id', $request->user()->id)->first();
        abort_unless($resp, 404);
        return [$r, $resp];
    }

    public function member(Request $request, int $id)
    {
        [$r, $resp] = $this->myResponse($request, $id);
        return view('applicant.recall', ['r' => $r, 'resp' => $resp]);
    }

    public function answer(Request $request, int $id)
    {
        [$r, $resp] = $this->myResponse($request, $id);
        if ($r->closed_at) {
            return back()->with('error', 'This recall is closed.');
        }
        $data = $request->validate(['answer' => ['required', Rule::in(array_keys(Recalls::ANSWERS))], 'note' => 'nullable|string|max:300|required_if:answer,cannot'],
            ['note.required_if' => 'Say briefly why you cannot come, so your unit can plan.']);
        DB::table('recall_responses')->where('id', $resp->id)->update(['answer' => $data['answer'], 'note' => $data['note'] ?? null, 'answered_at' => now(), 'updated_at' => now()]);
        Log::channel('audit')->info('recall.answered', ['recall_id' => $r->id, 'answer' => $data['answer']]);
        return back()->with('success', 'Your answer was sent to your unit: ' . Recalls::ANSWERS[$data['answer']] . '.');
    }
}
