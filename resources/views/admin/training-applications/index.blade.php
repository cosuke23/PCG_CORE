@extends('layouts.admin')

@section('title', 'Training applications')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/training-applications.css') }}">
@endsection

@php
    $statusLink = fn (string $status) => route('admin.training-applications.index', array_merge(request()->except('page'), ['status' => $status]));
    $filtered = request()->filled('search') || request()->filled('type') || request()->filled('status');
    $decided = $approvedApplications + $completedApplications;
    $statusBadge = [
        'pending' => 'pcg-badge--warning',
        'approved' => 'pcg-badge--success',
        'rejected' => 'pcg-badge--danger',
        'completed' => 'pcg-badge--info',
    ];
    $chartData = [
        'status' => [
            'labels' => ['Pending', 'Approved', 'Rejected', 'Completed'],
            'data' => [$pendingApplications, $approvedApplications, $rejectedApplications, $completedApplications],
        ],
        'track' => [
            'labels' => ['ACT', 'OCT'],
            'data' => [$actApplications, $octApplications],
        ],
    ];
@endphp

@section('content')
    <div class="pcg-page ta-page">
        <div class="pcg-page__header">
            <div>
                @if ($totalApplications === 0)
                    <h2>No training applications yet</h2>
                    <p>Applications appear here when members apply for an ACT or OCT course.</p>
                @else
                    <h2>{{ number_format($totalApplications) }} training {{ \Illuminate\Support\Str::plural('application', $totalApplications) }}, {{ number_format($pendingApplications) }} waiting for a decision</h2>
                    <p>{{ number_format($actApplications) }} administrative (ACT) and {{ number_format($octApplications) }} operational (OCT).</p>
                @endif
            </div>
            @if ($pendingApplications > 0 && request('status') !== 'Pending')
                <a class="pcg-btn pcg-btn--cta" href="{{ $statusLink('Pending') }}">
                    <i class="fa-solid fa-list-check" aria-hidden="true"></i> Review {{ number_format($pendingApplications) }} pending
                </a>
            @endif
        </div>

        <div class="pcg-stats">
            <a class="pcg-stat pcg-stat--link {{ $pendingApplications > 0 ? 'pcg-stat--warning' : '' }}" href="{{ $statusLink('Pending') }}">
                <span class="pcg-stat__label">Pending</span>
                <span class="pcg-stat__value">{{ number_format($pendingApplications) }}</span>
                <span class="pcg-stat__hint">waiting for a decision</span>
            </a>
            <a class="pcg-stat pcg-stat--link" href="{{ $statusLink('Approved') }}">
                <span class="pcg-stat__label">Approved</span>
                <span class="pcg-stat__value">{{ number_format($approvedApplications) }}</span>
                <span class="pcg-stat__hint">cleared to attend</span>
            </a>
            <a class="pcg-stat pcg-stat--link" href="{{ $statusLink('Completed') }}">
                <span class="pcg-stat__label">Completed</span>
                <span class="pcg-stat__value">{{ number_format($completedApplications) }}</span>
                <span class="pcg-stat__hint">course finished</span>
            </a>
            <a class="pcg-stat pcg-stat--link" href="{{ $statusLink('Rejected') }}">
                <span class="pcg-stat__label">Rejected</span>
                <span class="pcg-stat__value">{{ number_format($rejectedApplications) }}</span>
                <span class="pcg-stat__hint">not approved</span>
            </a>
        </div>

        @if ($totalApplications > 0)
            <div class="ta-charts">
                <section class="pcg-card" aria-labelledby="status-chart-heading">
                    <div class="pcg-card__header">
                        <h3 id="status-chart-heading">{{ number_format($decided) }} approved or completed, {{ number_format($rejectedApplications) }} rejected</h3>
                    </div>
                    <div class="pcg-card__body">
                        <div class="ta-chart">
                            <canvas id="statusChart" role="img" aria-label="Applications by status: {{ $pendingApplications }} pending, {{ $approvedApplications }} approved, {{ $rejectedApplications }} rejected, {{ $completedApplications }} completed"></canvas>
                        </div>
                    </div>
                </section>

                <section class="pcg-card" aria-labelledby="track-chart-heading">
                    <div class="pcg-card__header">
                        <h3 id="track-chart-heading">
                            @if ($actApplications === $octApplications)
                                ACT and OCT even at {{ number_format($actApplications) }} each
                            @else
                                {{ $actApplications > $octApplications ? 'ACT' : 'OCT' }} leads, {{ number_format(max($actApplications, $octApplications)) }} to {{ number_format(min($actApplications, $octApplications)) }}
                            @endif
                        </h3>
                        <p class="ta-card-note">ACT: Administrative Career / Training Track · OCT: Operational Career / Training Track</p>
                    </div>
                    <div class="pcg-card__body">
                        <div class="ta-chart ta-chart--short">
                            <canvas id="trackChart" role="img" aria-label="Applications by track: {{ $actApplications }} ACT, {{ $octApplications }} OCT"></canvas>
                        </div>
                    </div>
                </section>
            </div>
        @endif

        <section class="pcg-card pcg-mt">
            <div class="pcg-card__body">
                <form method="GET" action="{{ route('admin.training-applications.index') }}" class="pcg-filters" role="search">
                    <div class="pcg-field">
                        <label class="pcg-label" for="ta-search">Search</label>
                        <input class="pcg-input" id="ta-search" type="text" name="search" value="{{ request('search') }}" placeholder="Serial number, course, rank, unit, provider">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="ta-type">Track</label>
                        <select class="pcg-input" id="ta-type" name="type">
                            <option value="">ACT and OCT</option>
                            <option value="ACT" @selected(request('type') === 'ACT')>ACT</option>
                            <option value="OCT" @selected(request('type') === 'OCT')>OCT</option>
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="ta-status">Status</label>
                        <select class="pcg-input" id="ta-status" name="status">
                            <option value="">Any status</option>
                            @foreach (['Pending', 'Approved', 'Rejected', 'Completed'] as $s)
                                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-filters__actions">
                        <button type="submit" class="pcg-btn pcg-btn--secondary">Filter</button>
                        <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.training-applications.index') }}">Reset</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="pcg-card pcg-mt" aria-labelledby="list-heading">
            <div class="pcg-card__header">
                <h3 id="list-heading">
                    @if ($filtered)
                        {{ number_format($applications->total()) }} matching {{ \Illuminate\Support\Str::plural('application', $applications->total()) }}
                    @else
                        All {{ number_format($applications->total()) }} {{ \Illuminate\Support\Str::plural('application', $applications->total()) }}, newest first
                    @endif
                </h3>
            </div>

            @if ($applications->isEmpty())
                <p class="pcg-empty">
                    @if ($filtered)
                        No applications match these filters. <a href="{{ route('admin.training-applications.index') }}">Show all applications</a>
                    @else
                        No applications yet. They appear here when members apply for an ACT or OCT course.
                    @endif
                </p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">Serial number</th>
                                <th scope="col">Track</th>
                                <th scope="col">Course / training</th>
                                <th scope="col">Rank</th>
                                <th scope="col">Division / unit</th>
                                <th scope="col">Provider</th>
                                <th scope="col">Status</th>
                                <th scope="col">Applied</th>
                                <th scope="col" class="ta-col-action">Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                @php
                                    $appliedOn = $application->applied_at ?? $application->created_at;
                                    $statusKey = strtolower((string) $application->status);
                                @endphp
                                <tr>
                                    <td><span class="pcg-mono">{{ $application->serial_number }}</span></td>
                                    <td>{{ $application->training_type }}</td>
                                    <td>
                                        <strong>{{ $application->course_training }}</strong>
                                        @if ($application->year_in_grade)
                                            <span class="pcg-table__muted">{{ $application->year_in_grade }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $application->rank ?: '—' }}</td>
                                    <td>{{ $application->division_unit ?: '—' }}</td>
                                    <td>{{ $application->provider ?: '—' }}</td>
                                    <td><span class="pcg-badge {{ $statusBadge[$statusKey] ?? '' }}">{{ $application->status }}</span></td>
                                    <td class="ta-nowrap">
                                        @if ($appliedOn)
                                            <time datetime="{{ $appliedOn->toDateString() }}">{{ $appliedOn->format('d M Y') }}</time>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="ta-col-action">
                                        <form method="POST" action="{{ route('admin.training-applications.status', $application) }}" class="ta-decision">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="pcg-input ta-decision__select" data-on-change="pcg.submit-form" aria-label="Status for {{ $application->serial_number }}, {{ $application->course_training }}">
                                                @foreach (['Pending', 'Approved', 'Rejected', 'Completed'] as $s)
                                                    <option value="{{ $s }}" @selected($application->status === $s)>{{ $s }}</option>
                                                @endforeach
                                            </select>
                                            <span class="ta-decision__saving" role="status" hidden>Saving…</span>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($applications->hasPages())
                    <div class="pcg-card__body">{{ $applications->links('admin.partials.pagination') }}</div>
                @endif
            @endif
        </section>
    </div>
@endsection

@section('scripts')
    @if ($totalApplications > 0)
        <script type="application/json" id="ta-data" nonce="{{ $cspNonce ?? '' }}">@json($chartData)</script>
        <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/chartjs/chart-4.4.4.umd.min.js') }}"></script>
    @endif
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/training-applications.js') }}"></script>
@endsection
