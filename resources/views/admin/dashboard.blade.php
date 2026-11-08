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
    $ago = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->timezone('Asia/Manila')->diffForHumans() : '';
    $adData = [
        'courses' => ['labels' => $courseCounts->keys()->values(), 'data' => $courseCounts->values()],
        'trend' => [
            'labels' => array_column($crm['months'], 'label'),
            'registered' => array_column($crm['months'], 'registered'),
            'approved' => array_column($crm['months'], 'approved'),
        ],
        'filesUrl' => \App\Support\Uploads::baseUrl(),
        'noPhotoUrl' => asset('images/no-photo.svg'),
        'csrf' => csrf_token(),
        'flash' => session('swal_success'),
    ];
    $byStatus = $usersWithCourse->groupBy(fn ($u) => $statusKey($u->status));
    $board = [
        'pending' => ['title' => 'In review', 'icon' => 'fa-hourglass-half', 'rows' => ($byStatus['pending'] ?? collect())->sortBy('created_at')->values()],
        'approved' => ['title' => 'Approved', 'icon' => 'fa-circle-check', 'rows' => ($byStatus['approved'] ?? collect())->sortByDesc(fn ($u) => $u->updated_at ?? $u->created_at)->values()],
        'rejected' => ['title' => 'Rejected', 'icon' => 'fa-circle-xmark', 'rows' => ($byStatus['rejected'] ?? collect())->sortByDesc(fn ($u) => $u->updated_at ?? $u->created_at)->values()],
    ];
    $topUnits = $unitCounts->sortDesc()->take(6);
    $topMax = max(1, (int) $topUnits->max());
    $stages = [
        ['key' => '', 'label' => 'Registered', 'n' => $totalUsers, 'cls' => 'accent'],
        ['key' => 'pending', 'label' => 'In review', 'n' => $pendingCount, 'cls' => 'warn'],
        ['key' => 'approved', 'label' => 'Approved', 'n' => $approvedUsers, 'cls' => 'ok'],
        ['key' => 'rejected', 'label' => 'Rejected', 'n' => $rejectedUsers, 'cls' => 'danger'],
    ];
@endphp

@section('content')
    <div class="pcg-page ad">
        <div class="ad-orb ad-orb--a" aria-hidden="true"></div>
        <div class="ad-orb ad-orb--b" aria-hidden="true"></div>

        <header class="ad-head">
            <div>
                <p class="ad-eyebrow">CRM · Self-registrations</p>
                <h2>Registration CRM</h2>
                <p class="ad-lede">Every member who registered through the sign-up page, from first sign-up to decision. Work the review queue, follow the trend and see who changed what.</p>
            </div>
            @if ($pendingCount > 0)
                <button type="button" class="pcg-btn pcg-btn--primary ad-head__cta" data-ad-filter="pending">
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i> Review {{ $pendingCount }} pending
                </button>
            @endif
        </header>

        {{-- KPI strip --}}
        <section class="ad-kpis" aria-label="Key figures">
            <button type="button" class="ad-kpi ad-glass" data-ad-filter="" aria-pressed="true">
                <span class="ad-kpi__icon"><i class="fa-solid fa-address-book" aria-hidden="true"></i></span>
                <span class="ad-kpi__label">Contacts</span>
                <span class="ad-kpi__value">{{ number_format($totalUsers) }}</span>
                <span class="ad-kpi__hint">{{ number_format($crm['nonOfficers']) }} non-officer · show all</span>
            </button>
            <div class="ad-kpi ad-glass ad-kpi--static">
                <span class="ad-kpi__icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span>
                <span class="ad-kpi__label">New this month</span>
                <span class="ad-kpi__value">{{ number_format($crm['thisMonth']) }}</span>
                <span class="ad-kpi__hint">
                    @if ($crm['change'] === null)
                        {{ $crm['lastMonth'] }} last month
                    @elseif ($crm['change'] === 0)
                        <span class="ad-delta ad-delta--flat">no change</span> vs last month
                    @else
                        <span class="ad-delta ad-delta--{{ $crm['change'] >= 0 ? 'up' : 'down' }}">
                            <i class="fa-solid fa-arrow-{{ $crm['change'] >= 0 ? 'up' : 'down' }}" aria-hidden="true"></i>
                            {{ abs($crm['change']) }}%
                        </span> vs {{ $crm['lastMonth'] }} last month
                    @endif
                </span>
            </div>
            <button type="button" class="ad-kpi ad-glass ad-kpi--warn" data-ad-filter="pending" aria-pressed="false">
                <span class="ad-kpi__icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
                <span class="ad-kpi__label">Pending approval</span>
                <span class="ad-kpi__value">{{ number_format($pendingCount) }}</span>
                <span class="ad-kpi__hint">
                    @if ($crm['oldestPendingDays'] !== null)
                        Oldest waiting {{ $crm['oldestPendingDays'] }} {{ $crm['oldestPendingDays'] === 1 ? 'day' : 'days' }}
                    @else
                        Nothing waiting
                    @endif
                </span>
            </button>
            <button type="button" class="ad-kpi ad-glass ad-kpi--ok" data-ad-filter="approved" aria-pressed="false">
                <span class="ad-kpi__icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></span>
                <span class="ad-kpi__label">Approval rate</span>
                <span class="ad-kpi__value">{{ $approvalRate !== null ? $approvalRate . '%' : '—' }}</span>
                <span class="ad-kpi__bar"><span style="--w: {{ $approvalRate ?? 0 }}%"></span></span>
                <span class="ad-kpi__hint">{{ $approvedUsers }} approved · {{ $rejectedUsers }} rejected</span>
            </button>
        </section>

        <div class="ad-grid">
            {{-- Pipeline --}}
            <section class="ad-panel ad-glass" aria-labelledby="ad-pipe-title">
                <div class="ad-panel__head">
                    <h3 id="ad-pipe-title"><i class="fa-solid fa-filter" aria-hidden="true"></i> Registration pipeline</h3>
                </div>
                <ol class="ad-pipe">
                    @foreach ($stages as $st)
                        <li>
                            <button type="button" class="ad-pipe__stage ad-pipe__stage--{{ $st['cls'] }}" data-ad-filter="{{ $st['key'] }}">
                                <span class="ad-pipe__top">
                                    <span class="ad-pipe__label">{{ $st['label'] }}</span>
                                    <span class="ad-pipe__n">{{ number_format($st['n']) }} <small>{{ $pct($st['n']) }}%</small></span>
                                </span>
                                <span class="ad-pipe__bar"><span style="--w: {{ max($pct($st['n']), $st['n'] > 0 ? 3 : 0) }}%"></span></span>
                            </button>
                        </li>
                    @endforeach
                </ol>
                <p class="ad-faint ad-pipe__note">Share of all registrations at each stage. Select a stage to list its members.</p>
            </section>

            {{-- Trend --}}
            <section class="ad-panel ad-glass" aria-labelledby="ad-trend-title">
                <div class="ad-panel__head">
                    <h3 id="ad-trend-title"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Registrations, last 12 months</h3>
                    <span class="ad-legend"><span class="ad-legend__a"></span>Registered <span class="ad-legend__b"></span>Now approved</span>
                </div>
                <div class="ad-chart ad-chart--trend">
                    <canvas id="trendChart" role="img" aria-label="Registrations per month for the last 12 months"></canvas>
                </div>
            </section>
        </div>

        {{-- Board --}}
        <section class="ad-board" aria-label="Registrations by status">
            @foreach ($board as $key => $col)
                <div class="ad-panel ad-glass ad-col ad-col--{{ $key }}">
                    <div class="ad-panel__head">
                        <h3><i class="fa-solid {{ $col['icon'] }}" aria-hidden="true"></i> {{ $col['title'] }}</h3>
                        <span class="ad-count ad-count--{{ $key }}">{{ $col['rows']->count() }}</span>
                    </div>
                    @if ($col['rows']->isEmpty())
                        <div class="ad-empty"><i class="fa-regular fa-circle-check" aria-hidden="true"></i><p>{{ $key === 'pending' ? 'All caught up.' : 'None yet.' }}</p></div>
                    @else
                        <ul class="ad-cards">
                            @foreach ($col['rows']->take(5) as $u)
                                <li class="ad-card">
                                    <img src="{{ $photo($u) }}" alt="" class="ad-avatar ad-avatar--sm" loading="lazy">
                                    <div class="ad-card__who">
                                        <strong>{{ $u->name }}</strong>
                                        <span>{{ $u->rank ? $u->rank . ' · ' : '' }}{{ $u->unit_assignment ?: ($u->mother_unit ?: 'No unit') }}</span>
                                        <span class="ad-faint">Registered {{ $ago($u->created_at) }}</span>
                                    </div>
                                    @if ($key === 'pending')
                                        <button type="button" class="pcg-btn pcg-btn--primary pcg-btn--sm" data-ad-review="{{ $u->id }}" aria-label="Review {{ $u->name }}">Review</button>
                                    @else
                                        <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" data-ad-view="{{ $u->id }}" aria-label="View {{ $u->name }}">View</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($col['rows']->count() > 5)
                            <button type="button" class="ad-link" data-ad-filter="{{ $key }}">All {{ $col['rows']->count() }} in the table <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
                        @endif
                    @endif
                </div>
            @endforeach
        </section>

        <div class="ad-grid ad-grid--3">
            {{-- Activity --}}
            <section class="ad-panel ad-glass" aria-labelledby="ad-act-title">
                <div class="ad-panel__head">
                    <h3 id="ad-act-title"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Recent activity</h3>
                </div>
                @if ($crm['activity']->isEmpty())
                    <div class="ad-empty"><i class="fa-regular fa-clock" aria-hidden="true"></i><p>No activity yet.</p></div>
                @else
                    <ol class="ad-feed">
                        @foreach ($crm['activity'] as $a)
                            <li class="ad-feed__item ad-feed__item--{{ $a['type'] }}">
                                <span class="ad-feed__dot" aria-hidden="true"><i class="fa-solid {{ ['registered' => 'fa-user-plus', 'approved' => 'fa-check', 'rejected' => 'fa-xmark'][$a['type']] }}"></i></span>
                                <div>
                                    <p>
                                        <strong>{{ $a['user']->name }}</strong>
                                        @if ($a['type'] === 'registered')
                                            registered
                                        @else
                                            was {{ $a['type'] }}@if ($a['by']) <span class="ad-faint">by {{ $a['by'] }}</span>@endif
                                        @endif
                                    </p>
                                    <time class="ad-faint" datetime="{{ $a['at']->toIso8601String() }}">{{ $a['at']->diffForHumans() }} · {{ $a['at']->format('M j, h:i A') }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Course chart --}}
            <section class="ad-panel ad-glass" aria-labelledby="ad-course-title">
                <div class="ad-panel__head">
                    <h3 id="ad-course-title"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> By course</h3>
                </div>
                <div class="ad-chart ad-chart--donut">
                    <canvas id="courseChart" role="img" aria-label="Personnel by course"></canvas>
                </div>
            </section>

            {{-- Top units --}}
            <section class="ad-panel ad-glass" aria-labelledby="ad-unit-title">
                <div class="ad-panel__head">
                    <h3 id="ad-unit-title"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> Top unit assignments</h3>
                    <span class="ad-faint">{{ $unitCounts->count() }} {{ $unitCounts->count() === 1 ? 'unit' : 'units' }}</span>
                </div>
                <ol class="ad-rank">
                    @foreach ($topUnits as $unit => $n)
                        <li>
                            <span class="ad-rank__top"><span class="ad-rank__name">{{ $unit }}</span><strong>{{ $n }}</strong></span>
                            <span class="ad-rank__bar"><span style="--w: {{ round($n / $topMax * 100) }}%"></span></span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- Personnel table --}}
        <section class="ad-panel ad-glass ad-table" aria-labelledby="ad-table-title">
            <div class="ad-panel__head">
                <div>
                    <h3 id="ad-table-title"><i class="fa-solid fa-id-card" aria-hidden="true"></i> Contacts</h3>
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
