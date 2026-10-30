<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\Personnel;
use App\Models\PersonnelDocument;
use App\Services\OrgScope;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 201 file repository. Files live under config('pcg.document_root') (outside
 * public/) with random names and are only streamed by download() after the
 * scope and permission checks. Restricted categories (e.g. medical) need the
 * documents.restricted capability. Every upload, download and verification
 * is written to document_access_logs.
 */
class PersonnelDocumentController extends Controller
{
    private const MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct(private OrgScope $scope)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(PersonnelDocument::STATUSES))],
            'category' => 'nullable|integer',
            'expiry' => 'nullable|in:expired,soon',
        ]);

        $query = $this->visible()->with(['personnel.rank', 'category'])->where('is_current', true)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category_id', $c))
            ->when(($filters['expiry'] ?? null) === 'expired', fn ($q) => $q->whereDate('expires_on', '<', now()->toDateString()))
            ->when(($filters['expiry'] ?? null) === 'soon', fn ($q) => $q->whereBetween('expires_on', [now()->toDateString(), now()->addDays(60)->toDateString()]));

        return view('admin.documents.index', [
            'documents' => $query->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")->orderByDesc('id')
                ->paginate(25)->withQueryString(),
            'counts' => $this->visible()->where('is_current', true)->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status'),
            'categories' => $this->categories(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, Personnel $personnel)
    {
        abort_unless($this->scope->canSee($personnel), 404);

        $data = $request->validate([
            'category_id' => ['required', Rule::in($this->categories()->pluck('id')->all())],
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png|max:' . config('pcg.document_max_kb'),
            'issued_on' => 'nullable|date|before_or_equal:today',
            'expires_on' => 'nullable|date|after_or_equal:issued_on',
            'replaces_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $ext = self::MIMES[$mime] ?? null;
        if (!$ext) {
            return back()->withErrors(['file' => 'Only PDF, JPG and PNG files are accepted.']);
        }

        $previous = null;
        if (!empty($data['replaces_id'])) {
            $previous = PersonnelDocument::where('personnel_id', $personnel->id)->where('is_current', true)->find($data['replaces_id']);
            if (!$previous || (int) $previous->category_id !== (int) $data['category_id']) {
                return back()->withErrors(['replaces_id' => 'The document to replace must be a current document of the same category.']);
            }
        }

        $relative = $personnel->id . '/' . now()->format('Y') . '/' . Str::random(40) . '.' . $ext;
        $dir = dirname(config('pcg.document_root') . '/' . $relative);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return back()->withErrors(['file' => 'The document folder could not be created. Check PCG_DOCUMENT_ROOT permissions.']);
        }
        $file->move($dir, basename($relative));
        $path = config('pcg.document_root') . '/' . $relative;

        try {
            $doc = DB::transaction(function () use ($personnel, $data, $file, $mime, $relative, $path, $previous) {
                if ($previous) {
                    $previous->update(['is_current' => false]);
                }

                $doc = PersonnelDocument::create([
                    'personnel_id' => $personnel->id,
                    'category_id' => $data['category_id'],
                    'title' => $data['title'],
                    'stored_path' => $relative,
                    'original_name' => mb_substr(preg_replace('/[^\pL\pN ._()-]+/u', '_', $file->getClientOriginalName()), 0, 200),
                    'mime_type' => $mime,
                    'size_bytes' => filesize($path),
                    'sha256' => hash_file('sha256', $path),
                    'version' => $previous ? $previous->version + 1 : 1,
                    'supersedes_id' => $previous?->id,
                    'is_current' => true,
                    'status' => 'pending',
                    'issued_on' => $data['issued_on'] ?? null,
                    'expires_on' => $data['expires_on'] ?? null,
                    'uploaded_by' => session('admin_user_id'),
                ]);
                $this->log($doc, 'upload');

                return $doc;
            });
        } catch (\Throwable $e) {
            @unlink($path); // keep storage and database consistent
            throw $e;
        }

        return redirect()->route('admin.personnel.show', $personnel->id)
            ->with('success', "\"{$doc->title}\" uploaded" . ($previous ? " as version {$doc->version}; the previous version is kept" : '') . '. It is pending verification.');
    }

    public function download(int $document)
    {
        $doc = $this->visible()->whereKey($document)->firstOrFail();
        $path = realpath(config('pcg.document_root') . '/' . $doc->stored_path);
        $root = realpath(config('pcg.document_root'));

        if (!$path || !$root || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            abort(404);
        }

        $this->log($doc, 'download');
        $name = Str::slug($doc->personnel->serial_number . '-' . $doc->title) . '-v' . $doc->version . '.' . (self::MIMES[$doc->mime_type] ?? 'bin');

        return response()->download($path, $name, [
            'Content-Type' => $doc->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function verify(Request $request, int $document)
    {
        $doc = $this->visible()->whereKey($document)->firstOrFail();
        $data = $request->validate([
            'status' => 'required|in:verified,rejected',
            'review_remarks' => 'required_if:status,rejected|nullable|string|max:2000',
        ], ['review_remarks.required_if' => 'Say why the document is rejected.']);

        if (!session('admin_user_id')) {
            return back()->with('error', 'Sign in with your own admin account to verify documents.');
        }
        if ((int) $doc->uploaded_by === (int) session('admin_user_id')) {
            return back()->with('error', 'You uploaded this document, so another authorised admin must verify it.');
        }

        $doc->update($data + ['verified_by' => session('admin_user_id'), 'verified_at' => now()]);
        $this->log($doc, $data['status'] === 'verified' ? 'verify' : 'reject');

        return back()->with('success', "\"{$doc->title}\" marked " . PersonnelDocument::STATUSES[$data['status']] . '.');
    }

    /** Documents of personnel in scope, without restricted categories unless permitted. */
    private function visible()
    {
        $personnelIds = $this->scope->personnelQuery()->select('personnel.id');

        return PersonnelDocument::query()->whereIn('personnel_id', $personnelIds)
            ->when(!AdminPermissions::can('documents.restricted'), fn ($q) => $q->whereIn('category_id', DocumentCategory::where('is_restricted', false)->select('id')));
    }

    private function categories()
    {
        return DocumentCategory::where('is_active', true)->orderBy('sort_order')->get()
            ->filter(fn ($c) => AdminPermissions::can('documents.restricted') || !$c->is_restricted)->values();
    }

    private function log(PersonnelDocument $doc, string $action): void
    {
        DB::table('document_access_logs')->insert([
            'document_id' => $doc->id,
            'action' => $action,
            'admin_user_id' => session('admin_user_id'),
            'admin_email' => session('admin_email'),
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
