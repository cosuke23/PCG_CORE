<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Services\OrgScope;
use App\Support\AdminAudit;
use App\Support\ServiceCertificates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Service certificates (App\Support\ServiceCertificates): member request and print,
 * admin issue/decline/revoke within OrgScope (certificates.issue), public check.
 */
class ServiceCertificateController extends Controller
{
    // ------------------------------------------------------------ member

    public function index(Request $request)
    {
        $user = $request->user();
        $list = ServiceCertificates::ready()
            ? DB::table('service_certificates')->where('user_id', $user->id)->orderByDesc('id')->get() : collect();
        return view('applicant.certificates', ['list' => $list, 'ready' => ServiceCertificates::ready()]);
    }

    public function store(Request $request)
    {
        abort_unless(ServiceCertificates::ready(), 409, 'Not available yet (database update pending).');
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ServiceCertificates::KINDS))],
            'purpose' => 'required|string|max:200',
        ]);
        $user = $request->user();
        if (DB::table('service_certificates')->where('user_id', $user->id)->where('status', 'pending')->count() >= 3) {
            return back()->with('error', 'You already have 3 requests waiting for HR. Wait for them to be decided.');
        }
        $id = DB::table('service_certificates')->insertGetId(['user_id' => $user->id, 'serial_number' => $user->serial_number,
            'kind' => $data['kind'], 'purpose' => trim($data['purpose']), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        Log::channel('audit')->info('service_certificate.requested', ['certificate_id' => $id, 'user_id' => $user->id, 'kind' => $data['kind']]);
        return back()->with('success', 'Request sent to HR. You will get a notification when it is ready to print.');
    }

    public function memberPrint(Request $request, int $id)
    {
        $cert = $this->issued($id);
        abort_unless((int) $cert->user_id === (int) $request->user()->id, 404);
        ServiceCertificates::printed($cert, 'member');
        return $this->printView($cert, route('certificates'));
    }

    // ------------------------------------------------------------ admin

    private function serials(OrgScope $scope): ?array
    {
        return $scope->isOrgWide() ? null : $scope->personnelQuery()->pluck('serial_number')
            ->map(fn ($s) => Personnel::normalizeSerial($s))->filter()->unique()->values()->all();
    }

    private function adminRow(OrgScope $scope, int $id): object
    {
        $c = ServiceCertificates::ready() ? DB::table('service_certificates')->find($id) : null;
        abort_unless($c, 404);
        $serials = $this->serials($scope);
        abort_unless($serials === null || in_array(Personnel::normalizeSerial($c->serial_number), $serials, true), 403);
        return $c;
    }

    public function adminIndex(Request $request, OrgScope $scope)
    {
        $status = (string) $request->query('status', 'pending');
        $serials = $this->serials($scope);
        $q = ServiceCertificates::ready() ? DB::table('service_certificates as c')->join('users as u', 'u.id', '=', 'c.user_id')
            ->when($serials !== null, fn ($w) => $w->whereIn(DB::raw('UPPER(TRIM(c.serial_number))'), $serials ?: ['__none__'])) : null;
        $counts = $q ? (clone $q)->selectRaw('c.status, COUNT(*) as n')->groupBy('c.status')->pluck('n', 'status') : collect();
        $list = $q ? (clone $q)->when($status !== 'all', fn ($w) => $w->where('c.status', $status))
            ->orderBy($status === 'pending' ? 'c.created_at' : 'c.decided_at', $status === 'pending' ? 'asc' : 'desc')
            ->limit(200)->get(['c.*', 'u.name', 'u.rank', 'u.mother_unit']) : collect();
        return view('admin.certificates', [
            'list' => $list, 'counts' => $counts, 'status' => $status, 'ready' => ServiceCertificates::ready(),
            'canIssue' => \App\Support\AdminPermissions::can('certificates.issue'),
            'scopeName' => $scope->isOrgWide() ? 'all units' : (optional($scope->scopeUnit())->name ?? 'your command'),
        ]);
    }

    public function decide(Request $request, OrgScope $scope, int $id)
    {
        $c = $this->adminRow($scope, $id);
        $data = $request->validate([
            'decision' => 'required|in:issue,decline,revoke',
            'note' => 'nullable|string|max:500|required_if:decision,decline|required_if:decision,revoke',
        ], ['note.required_if' => 'Say why, so the member knows.']);
        $by = ['id' => $request->session()->get('admin_user_id'), 'name' => (string) ($request->session()->get('admin_name') ?: $request->session()->get('admin_email'))];
        $user = DB::table('users')->find($c->user_id);
        if ($data['decision'] === 'issue') {
            abort_unless($c->status === 'pending' && $user, 422, 'Only a waiting request can be issued.');
            ServiceCertificates::issue($c, $user, $by, $data['note'] ?? null);
        } elseif ($data['decision'] === 'decline') {
            abort_unless($c->status === 'pending', 422, 'Only a waiting request can be declined.');
            ServiceCertificates::decline($c, $by, $data['note']);
        } else {
            abort_unless($c->status === 'issued', 422, 'Only an issued certificate can be revoked.');
            DB::table('service_certificates')->where('id', $c->id)->update(['status' => 'revoked', 'decision_note' => $data['note'], 'updated_at' => now()]);
        }
        AdminAudit::record('service_certificate.' . $data['decision'], 'certificate:' . $c->id, ['serial_number' => $c->serial_number, 'kind' => $c->kind]);
        return back()->with('success', ['issue' => 'Certificate issued. The member can print it now.', 'decline' => 'Request declined; the member was told why.', 'revoke' => 'Certificate revoked; the QR check now shows it as revoked.'][$data['decision']]);
    }

    public function adminPrint(Request $request, OrgScope $scope, int $id)
    {
        $c = $this->adminRow($scope, $id);
        abort_unless($c->status === 'issued', 404);
        ServiceCertificates::printed($c, 'admin');
        return $this->printView($c, route('admin.certificates', ['status' => 'issued']));
    }

    // ------------------------------------------------------------ public check

    public function verify(Request $request, ?string $code = null)
    {
        $code = strtoupper(trim((string) ($code ?? $request->query('code', ''))));
        $cert = null;
        if ($code !== '' && preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code) && ServiceCertificates::ready()) {
            $cert = DB::table('service_certificates')->where('code', $code)->whereIn('status', ['issued', 'revoked'])->first();
        }
        return view('pages.verify-certificate', ['code' => $code, 'cert' => $cert, 'snap' => $cert ? (json_decode((string) $cert->snapshot, true) ?: []) : []]);
    }

    // ------------------------------------------------------------ shared

    private function issued(int $id): object
    {
        $c = ServiceCertificates::ready() ? DB::table('service_certificates')->find($id) : null;
        abort_unless($c && $c->status === 'issued', 404);
        return $c;
    }

    private function printView(object $c, string $back)
    {
        return view('certificates.print', ['c' => $c, 'snap' => json_decode((string) $c->snapshot, true) ?: [], 'back' => $back,
            'verifyUrl' => url('/verify/certificate/' . $c->code)]);
    }
}
