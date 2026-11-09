{{--
    Document review queue + review dialog, shared by the own-unit page (unit.dashboard)
    and the M1 page (unit.dashboard_m1). Expects $documents (personnel_data rows with
    applicant_name, serial_number, optionally mother_unit) and $allUnits (bool).
    The dialog posts to /unit/documents/review exactly as before:
    serial_number, decision (approved|rejected), docs[<column>|location][checked|comment].
--}}
@php
    $fields = [
        'soi' => 'Statement of Information (SOI)',
        'ofr' => 'OFR / NOPER',
        'nbi_clearance' => 'NBI clearance',
        'ombudsman_clearance' => 'Ombudsman clearance',
        'sandiganbayan_clearance' => 'Sandiganbayan clearance',
        'medical_clearance' => 'Medical clearance',
        'pft_clearance' => 'PFT clearance',
        'photo_2x2' => '2×2 photo',
        'whole_body_picture' => 'Whole-body photo',
        'residential_sketch' => 'Residential sketch',
    ];
    $fileUrl = function ($path) {
        $path = preg_replace('#^(/+|storage/)+#', '', (string) $path);
        if (!str_contains($path, '/')) {
            $path = 'documents/' . $path; // very old rows kept the bare file name
        }
        return \App\Support\Uploads::url('documents_toupload/' . $path);
    };
    $statusOf = fn ($s) => match (strtolower(trim((string) $s))) { 'approved' => 'approved', 'rejected' => 'returned', default => 'waiting' };
    $rows = collect($documents)->map(function ($d) use ($fields, $fileUrl, $statusOf) {
        $items = [];
        foreach ($fields as $f => $label) {
            $path = $d->$f ?? null;
            $items[] = [
                'field' => $f,
                'label' => $label,
                'url' => $path ? $fileUrl($path) : null,
                'kind' => $path ? (preg_match('/\.pdf$/i', $path) ? 'pdf' : (preg_match('/\.(jpe?g|png|gif|webp)$/i', $path) ? 'image' : 'file')) : null,
                'expires' => !empty($d->{$f . '_expiration'}) ? \Carbon\Carbon::parse($d->{$f . '_expiration'})->format('d M Y') : null,
            ];
        }
        $lat = is_numeric($d->latitude ?? null) ? (float) $d->latitude : null;
        $lng = is_numeric($d->longitude ?? null) ? (float) $d->longitude : null;
        return [
            'name' => $d->applicant_name ?? 'Name not given',
            'serial' => $d->serial_number,
            'unit' => $d->mother_unit ?? null,
            'status' => $statusOf($d->status ?? null),
            'uploaded' => $d->created_at ? \Carbon\Carbon::parse($d->created_at)->timezone('Asia/Manila')->format('d M Y') : null,
            'count' => collect($items)->whereNotNull('url')->count(),
            'items' => $items,
            'pin' => $lat !== null && $lng !== null ? ['lat' => $lat, 'lng' => $lng] : null,
        ];
    })->values();
    $counts = ['all' => $rows->count(), 'waiting' => $rows->where('status', 'waiting')->count(),
        'returned' => $rows->where('status', 'returned')->count(), 'approved' => $rows->where('status', 'approved')->count()];
    $statusText = ['waiting' => 'Waiting', 'returned' => 'Returned', 'approved' => 'Approved'];
    $reviewed = session('reviewed');
@endphp

<div class="pcg-page uc">
    <header class="uc-head">
        <div>
            <h2 class="uc-title">
                @if ($counts['waiting'] === 0)
                    No records are waiting for review
                @else
                    {{ $counts['waiting'] }} {{ $counts['waiting'] === 1 ? 'record is' : 'records are' }} waiting for your review
                @endif
            </h2>
            <p class="uc-lede">{{ $allUnits ? 'Required documents from every unit.' : 'Required documents of members whose mother unit is ' . session('unit_name') . '.' }}
                Open a record to see each document, then approve it or return the items that need to be uploaded again.</p>
        </div>
    </header>

    <div class="uc-tools">
        <div class="uc-filter" role="group" aria-label="Show records">
            @foreach (['waiting' => 'Waiting', 'returned' => 'Returned', 'approved' => 'Approved', 'all' => 'All'] as $key => $label)
                <button type="button" class="uc-filter__btn" data-uc-filter="{{ $key }}" aria-pressed="{{ $key === ($counts['waiting'] ? 'waiting' : 'all') ? 'true' : 'false' }}">
                    {{ $label }} <span class="pcg-num">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </div>
        <label class="uc-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <span class="pcg-visually-hidden">Search by name or serial number</span>
            <input type="search" id="uc-search" placeholder="Search name or serial number" autocomplete="off">
        </label>
    </div>

    <section class="uc-queue" aria-label="Records">
        <table class="uc-table">
            <thead>
                <tr>
                    <th scope="col">Member</th>
                    @if ($allUnits)<th scope="col">Unit</th>@endif
                    <th scope="col">Documents</th>
                    <th scope="col">Uploaded</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="pcg-visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $r)
                    <tr data-uc-row="{{ $i }}" data-status="{{ $r['status'] }}" data-search="{{ strtolower($r['name'] . ' ' . $r['serial']) }}"
                        @class(['pcg-flash-row' => $reviewed && $reviewed === $r['serial']])>
                        <td>
                            <strong class="uc-name">{{ $r['name'] }}</strong>
                            <span class="uc-serial pcg-mono">{{ $r['serial'] }}</span>
                        </td>
                        @if ($allUnits)<td>{{ $r['unit'] ?? '—' }}</td>@endif
                        <td>
                            <span class="uc-meter" aria-hidden="true"><span style="width: {{ $r['count'] * 10 }}%"></span></span>
                            <span class="pcg-num">{{ $r['count'] }} of 10</span>
                        </td>
                        <td><time class="pcg-num">{{ $r['uploaded'] ?? '—' }}</time></td>
                        <td><span class="uc-status uc-status--{{ $r['status'] }}">{{ $statusText[$r['status']] }}</span></td>
                        <td class="uc-action">
                            <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" data-uc-open="{{ $i }}">
                                Review <span class="pcg-visually-hidden">{{ $r['name'] }}</span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="uc-empty" id="uc-empty" @if ($rows->isNotEmpty()) hidden @endif>
            {{ $rows->isEmpty() ? 'No member has uploaded required documents yet.' : 'No record matches.' }}
        </p>
    </section>
</div>

{{-- review dialog --}}
<dialog class="uc-dialog" id="uc-dialog" aria-labelledby="uc-d-name">
    <form method="POST" action="/unit/documents/review" id="uc-form" class="uc-dialog__form">
        @csrf
        <input type="hidden" name="serial_number" id="uc-d-serial-input">

        <header class="uc-dialog__head">
            <div>
                <h2 id="uc-d-name"></h2>
                <p class="uc-dialog__sub"><span class="pcg-mono" id="uc-d-serial"></span><span id="uc-d-unit"></span><span class="uc-status" id="uc-d-status"></span></p>
            </div>
            <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm" data-uc-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="uc-dialog__body">
            <aside class="uc-items" aria-label="Documents">
                <ul id="uc-items"></ul>
            </aside>
            <section class="uc-viewer" aria-live="polite">
                <header class="uc-viewer__head">
                    <h3 id="uc-v-title"></h3>
                    <a id="uc-v-open" class="uc-viewer__open" target="_blank" rel="noopener" hidden>
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open in a new tab</a>
                </header>
                <div class="uc-viewer__frame" id="uc-v-frame"></div>
                <div class="uc-return" id="uc-return"></div>
            </section>
        </div>

        <footer class="uc-dialog__foot">
            <p id="uc-summary" class="uc-summary">Tick “Return” on any item that must be uploaded again.</p>
            <div class="uc-dialog__actions">
                <button type="submit" name="decision" value="rejected" class="pcg-btn pcg-btn--danger" id="uc-return-btn" disabled>
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> <span>Return items</span>
                </button>
                <button type="submit" name="decision" value="approved" class="pcg-btn pcg-btn--cta" id="uc-approve-btn">
                    <i class="fa-solid fa-check" aria-hidden="true"></i> Approve record
                </button>
            </div>
        </footer>
    </form>
</dialog>

<script type="application/json" id="uc-data" nonce="{{ $cspNonce ?? '' }}">@json($rows)</script>
