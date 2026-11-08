@extends('layouts.admin')

@section('title', 'Self-registrations')

@section('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/admin-dashboard.css') }}">
@endsection

@php
    $pendingCount = $pendingUsers->count();
    $decided = $approvedUsers + $rejectedUsers;
    $approvalRate = $decided > 0 ? round($approvedUsers / $decided * 100) : null;
    $pct = fn ($n) => $totalUsers > 0 ? round($n / $totalUsers * 100, 1) : 0;
    $statusKey = fn ($s) => in_array(strtolower(trim((string) $s)), ['pending', 'approved', 'rejected'], true) ? strtolower(trim((string) $s)) : 'other';
    $photo = fn ($u) => $u->selfie_image ? \App\Support\Uploads::url($u->selfie_image) : asset('images/no-photo.svg');
    $adData = [
        'courses' => ['labels' => $courseCounts->keys()->values(), 'data' => $courseCounts->values()],
        'units' => ['labels' => $unitCounts->keys()->values(), 'data' => $unitCounts->values()],
        'filesUrl' => \App\Support\Uploads::baseUrl(),
        'noPhotoUrl' => asset('images/no-photo.svg'),
        'csrf' => csrf_token(),
        'flash' => session('swal_success'),
    ];
    $queue = $usersWithCourse->filter(fn ($u) => $statusKey($u->status) === 'pending')->sortBy('created_at')->values();
@endphp

@section('content')
    <div class="pcg-page ad">
        <div class="ad-orb ad-orb--a" aria-hidden="true"></div>
        <div class="ad-orb ad-orb--b" aria-hidden="true"></div>

        <header class="ad-head">
            <div>
                <p class="ad-eyebrow">Personnel · Self-registrations</p>
                <h2>Registration dashboard</h2>
                <p class="ad-lede">Every account registered through the sign-up page. Review pending registrations, then approve or reject them. Approved members can sign in.</p>
            </div>
            @if ($pendingCount > 0)
                <button type="button" class="pcg-btn pcg-btn--primary ad-head__cta" data-ad-filter="pending">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i> Review {{ $pendingCount }} pending
                </button>
            @endif
        </header>

        {{-- KPI cards: each one filters the table below --}}
        <section class="ad-kpis" aria-label="Registration totals">
            <button type="button" class="ad-kpi ad-glass" data-ad-filter="" aria-pressed="true">
                <span class="ad-kpi__icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                <span class="ad-kpi__label">Registered personnel</span>
                <span class="ad-kpi__value">{{ number_format($totalUsers) }}</span>
                <span class="ad-kpi__hint">All accounts · show all</span>
            </button>
            <button type="button" class="ad-kpi ad-glass ad-kpi--warn" data-ad-filter="pending" aria-pressed="false">
                <span class="ad-kpi__icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
                <span class="ad-kpi__label">Pending approval</span>
                <span class="ad-kpi__value">{{ number_format($pendingCount) }}</span>
                <span class="ad-kpi__bar"><span style="--w: {{ $pct($pendingCount) }}%"></span></span>
                <span class="ad-kpi__hint">{{ $pendingCount > 0 ? 'Waiting for a decision' : 'Nothing waiting' }}</span>
            </button>
            <button type="button" class="ad-kpi ad-glass ad-kpi--ok" data-ad-filter="approved" aria-pressed="false">
                <span class="ad-kpi__icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                <span class="ad-kpi__label">Approved</span>
                <span class="ad-kpi__value">{{ number_format($approvedUsers) }}</span>
                <span class="ad-kpi__bar"><span style="--w: {{ $pct($approvedUsers) }}%"></span></span>
                <span class="ad-kpi__hint">{{ $approvalRate !== null ? $approvalRate . '% of decisions approved' : 'Can sign in' }}</span>
            </button>
            <button type="button" class="ad-kpi ad-glass ad-kpi--danger" data-ad-filter="rejected" aria-pressed="false">
                <span class="ad-kpi__icon"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></span>
                <span class="ad-kpi__label">Rejected</span>
                <span class="ad-kpi__value">{{ number_format($rejectedUsers) }}</span>
                <span class="ad-kpi__bar"><span style="--w: {{ $pct($rejectedUsers) }}%"></span></span>
                <span class="ad-kpi__hint">Cannot sign in</span>
            </button>
        </section>

        <div class="ad-grid">
            {{-- Pending queue --}}
            <section class="ad-panel ad-glass ad-queue" aria-labelledby="ad-queue-title">
                <div class="ad-panel__head">
                    <h3 id="ad-queue-title"><i class="fa-solid fa-inbox" aria-hidden="true"></i> Pending queue</h3>
                    <span class="ad-count">{{ $pendingCount }}</span>
                </div>
                @if ($queue->isEmpty())
                    <div class="ad-empty">
                        <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                        <p>All caught up. New registrations appear here.</p>
                    </div>
                @else
                    <ul class="ad-queue__list">
                        @foreach ($queue->take(6) as $u)
                            <li class="ad-queue__item">
                                <img src="{{ $photo($u) }}" alt="" class="ad-avatar" loading="lazy">
                                <div class="ad-queue__who">
                                    <strong>{{ $u->name }}</strong>
                                    <span>{{ $u->rank ? $u->rank . ' · ' : '' }}{{ $u->unit_assignment ?: ($u->mother_unit ?: 'No unit') }}</span>
                                    @if ($u->created_at)
                                        <span class="ad-faint">Registered {{ \Illuminate\Support\Carbon::parse($u->created_at)->timezone('Asia/Manila')->diffForHumans() }}</span>
                                    @endif
                                </div>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" data-ad-review="{{ $u->id }}"
                                    aria-label="Review {{ $u->name }}">Review</button>
                            </li>
                        @endforeach
                    </ul>
                    @if ($queue->count() > 6)
                        <button type="button" class="ad-link" data-ad-filter="pending">See all {{ $queue->count() }} pending in the table <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
                    @endif
                @endif
            </section>

            {{-- Course chart --}}
            <section class="ad-panel ad-glass" aria-labelledby="ad-course-title">
                <div class="ad-panel__head">
                    <h3 id="ad-course-title"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Personnel by course</h3>
                </div>
                <div class="ad-chart ad-chart--donut">
                    <canvas id="courseChart" role="img" aria-label="Personnel by course"></canvas>
                </div>
            </section>

            {{-- Unit chart --}}
            <section class="ad-panel ad-glass ad-panel--wide" aria-labelledby="ad-unit-title">
                <div class="ad-panel__head">
                    <h3 id="ad-unit-title"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Personnel by unit assignment</h3>
                    <span class="ad-faint">{{ $unitCounts->count() }} {{ $unitCounts->count() === 1 ? 'unit' : 'units' }}</span>
                </div>
                <div class="ad-chart ad-chart--bar" style="--rows: {{ min(max($unitCounts->count(), 3), 14) }}">
                    <canvas id="unitChart" role="img" aria-label="Personnel by unit assignment"></canvas>
                </div>
            </section>
        </div>

        {{-- Personnel table --}}
        <section class="ad-panel ad-glass ad-table" aria-labelledby="ad-table-title">
            <div class="ad-panel__head">
                <div>
                    <h3 id="ad-table-title"><i class="fa-solid fa-id-card" aria-hidden="true"></i> PCG personnel</h3>
                    <p class="ad-faint">Select <b>Review</b> (or double-click a row) to approve or reject a pending registration, or <b>View</b> for details.</p>
                </div>
                <div class="ad-segment" role="group" aria-label="Filter by status">
                    <button type="button" data-ad-filter="" aria-pressed="true">All</button>
                    <button type="button" data-ad-filter="pending" aria-pressed="false">Pending</button>
                    <button type="button" data-ad-filter="approved" aria-pressed="false">Approved</button>
                    <button type="button" data-ad-filter="rejected" aria-pressed="false">Rejected</button>
                </div>
            </div>

            <table id="personnelTable" class="ad-dt">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Unit Assignment</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($usersWithCourse as $user)
                        @php($key = $statusKey($user->status))
                        <tr data-status="{{ $user->status }}" data-status-key="{{ $key }}" data-user='@json($user)' data-id="{{ $user->id }}">
                            <td><img src="{{ $photo($user) }}" alt="" class="ad-avatar ad-avatar--sm" loading="lazy"></td>
                            <td>
                                <span class="ad-name">{{ $user->name }}</span>
                                @if ($user->serial_number)
                                    <span class="ad-sub">{{ $user->serial_number }}</span>
                                @endif
                            </td>
                            <td>{{ $user->course ?? 'N/A' }}</td>
                            <td>{{ $user->unit_assignment ?? 'N/A' }}</td>
                            <td class="ad-email">{{ $user->email }}</td>
                            <td data-search="{{ $key }}"><span class="ad-pill ad-pill--{{ $key }}">{{ $user->status }}</span></td>
                            <td class="ad-actions">
                                @if ($key === 'pending')
                                    <button type="button" class="pcg-btn pcg-btn--primary pcg-btn--sm" data-ad-review="{{ $user->id }}" aria-label="Review {{ $user->name }}">Review</button>
                                @else
                                    <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" data-ad-view="{{ $user->id }}" aria-label="View {{ $user->name }}">View</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>

    {{-- Details --}}
    <dialog id="userModal" class="ad-dialog" aria-labelledby="modalName">
        <div class="ad-dialog__inner">
            <button type="button" class="closeModal ad-dialog__close" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            <img id="modalPhoto" class="ad-avatar ad-avatar--lg" alt="">
            <h2 id="modalName"></h2>
            <p id="modalEmail" class="ad-faint"></p>
            <span id="modalStatus" class="ad-pill"></span>
            <dl class="ad-dl">
                <div><dt>Serial</dt><dd id="modalSerial"></dd></div>
                <div><dt>Rank</dt><dd id="modalRank"></dd></div>
                <div><dt>Course</dt><dd id="modalCourse"></dd></div>
                <div><dt>Unit</dt><dd id="modalUnit"></dd></div>
                <div><dt>Mother unit</dt><dd id="modalMother"></dd></div>
                <div><dt>Registered</dt><dd id="modalCreated"></dd></div>
            </dl>
        </div>
    </dialog>

    <script type="application/json" id="ad-data" nonce="{{ $cspNonce ?? '' }}">@json($adData)</script>
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/chartjs/chart-4.4.4.umd.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/admin-dashboard.js') }}"></script>
@endsection
