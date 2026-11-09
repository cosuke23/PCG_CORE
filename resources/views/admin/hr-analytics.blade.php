@extends('layouts.admin')

@section('title', 'HR analytics')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/hr-analytics.css') }}">
@endsection

@php
    $plural = fn (string $word, $n) => \Illuminate\Support\Str::plural($word, (int) $n);
    $typeLabel = fn ($t) => match (strtolower((string) $t)) {
        'officer' => 'Officer',
        'non_officer' => 'Non-officer',
        '' => 'Not recorded',
        default => ucfirst(str_replace('_', ' ', (string) $t)),
    };

    $statusRows = collect($statusChart)->map(fn ($r) => [
        'label' => trim((string) $r->status) !== '' ? ucfirst(strtolower(trim((string) $r->status))) : 'Not recorded',
        'key' => strtolower(trim((string) $r->status)),
        'total' => (int) $r->total,
    ])->groupBy('label')->map(fn ($g) => ['label' => $g->first()['label'], 'key' => $g->first()['key'], 'total' => $g->sum('total')])->values();

    $typeRows = collect($registrationChart)->map(fn ($r) => ['label' => $typeLabel($r->registration_type), 'total' => (int) $r->total]);
    $officers = (int) ($typeRows->firstWhere('label', 'Officer')['total'] ?? 0);
    $nonOfficers = (int) ($typeRows->firstWhere('label', 'Non-officer')['total'] ?? 0);

    $topUnit = collect($unitChart)->first();
    $topRank = collect($rankChart)->first();
    $topCourse = collect($academicChart)->first();
    $peakYear = collect($yearlyChart)->sortByDesc('total')->first();

    $chartData = [
        'status' => ['labels' => $statusRows->pluck('label'), 'keys' => $statusRows->pluck('key'), 'data' => $statusRows->pluck('total')],
        'type' => ['labels' => $typeRows->pluck('label'), 'data' => $typeRows->pluck('total')],
        'unit' => ['labels' => collect($unitChart)->pluck('unit'), 'data' => collect($unitChart)->pluck('total')],
        'rank' => ['labels' => collect($rankChart)->pluck('rank'), 'data' => collect($rankChart)->pluck('total')],
        'course' => ['labels' => collect($academicChart)->pluck('course'), 'data' => collect($academicChart)->pluck('total')],
        'yearly' => ['labels' => collect($yearlyChart)->map(fn ($r) => 'FY ' . $r->year), 'data' => collect($yearlyChart)->pluck('total')],
    ];
    $unitHeight = max(240, min(640, count($chartData['unit']['labels']) * 28 + 40));
@endphp

@section('content')
    <div class="pcg-page hra-page">
        <div class="pcg-page__header">
            <div>
                @if ($totalPersonnel === 0)
                    <h2>No personnel registered in FY {{ $year }}</h2>
                    <p>Pick another fiscal year to see its registrations.</p>
                @else
                    <h2>{{ number_format($totalPersonnel) }} personnel registered in FY {{ $year }}@if ($pendingPersonnel > 0), {{ number_format($pendingPersonnel) }} waiting for approval @endif</h2>
                    <p>Counted from registrations created between 1 January and 31 December {{ $year }}.</p>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.hr-analytics') }}" class="hra-year">
                <label class="pcg-label" for="year">Fiscal year</label>
                <select class="pcg-input" name="year" id="year" data-on-change="pcg.submit-form">
                    @forelse ($availableYears as $availableYear)
                        <option value="{{ $availableYear }}" @selected($year == $availableYear)>FY {{ $availableYear }}</option>
                    @empty
                        <option value="{{ $year }}">FY {{ $year }}</option>
                    @endforelse
                </select>
            </form>
        </div>

        <div class="pcg-stats">
            <div class="pcg-stat">
                <span class="pcg-stat__label">Approved</span>
                <span class="pcg-stat__value">{{ number_format($approvedPersonnel) }}</span>
                <span class="pcg-stat__hint">active personnel</span>
            </div>
            <div class="pcg-stat {{ $pendingPersonnel > 0 ? 'pcg-stat--warning' : '' }}">
                <span class="pcg-stat__label">Pending</span>
                <span class="pcg-stat__value">{{ number_format($pendingPersonnel) }}</span>
                <span class="pcg-stat__hint">awaiting approval</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">Rejected</span>
                <span class="pcg-stat__value">{{ number_format($rejectedPersonnel) }}</span>
                <span class="pcg-stat__hint">registration not accepted</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">Units</span>
                <span class="pcg-stat__value">{{ number_format($unitsRepresented) }}</span>
                <span class="pcg-stat__hint">with at least one registration</span>
            </div>
        </div>

        <div class="hra-grid">
            <section class="pcg-card" aria-labelledby="hra-status-h">
                <div class="pcg-card__header">
                    <h3 id="hra-status-h">
                        @if ($totalPersonnel > 0)
                            {{ round($approvedPersonnel / $totalPersonnel * 100) }}% of FY {{ $year }} registrations approved
                        @else
                            Registration status
                        @endif
                    </h3>
                </div>
                <div class="pcg-card__body">
                    @if ($statusRows->isEmpty())
                        <p class="pcg-empty">No registrations in FY {{ $year }}.</p>
                    @else
                        <div class="hra-chart"><canvas id="statusChart" role="img" aria-label="Registrations by status: {{ $statusRows->map(fn ($r) => $r['label'] . ' ' . $r['total'])->implode(', ') }}"></canvas></div>
                    @endif
                </div>
            </section>

            <section class="pcg-card" aria-labelledby="hra-type-h">
                <div class="pcg-card__header">
                    <h3 id="hra-type-h">
                        @if ($typeRows->isNotEmpty())
                            {{ number_format($officers) }} {{ $plural('officer', $officers) }}, {{ number_format($nonOfficers) }} {{ $plural('non-officer', $nonOfficers) }}
                        @else
                            Officers and non-officers
                        @endif
                    </h3>
                </div>
                <div class="pcg-card__body">
                    @if ($typeRows->isEmpty())
                        <p class="pcg-empty">No registrations in FY {{ $year }}.</p>
                    @else
                        <div class="hra-chart"><canvas id="registrationChart" role="img" aria-label="Registrations by type: {{ $typeRows->map(fn ($r) => $r['label'] . ' ' . $r['total'])->implode(', ') }}"></canvas></div>
                    @endif
                </div>
            </section>

            <section class="pcg-card hra-wide" aria-labelledby="hra-unit-h">
                <div class="pcg-card__header">
                    <h3 id="hra-unit-h">
                        @if ($topUnit)
                            {{ $topUnit->unit }} has the most personnel ({{ number_format($topUnit->total) }}) of {{ number_format(count($chartData['unit']['labels'])) }} {{ $plural('unit', count($chartData['unit']['labels'])) }}
                        @else
                            Personnel by unit
                        @endif
                    </h3>
                </div>
                <div class="pcg-card__body">
                    @if (! $topUnit)
                        <p class="pcg-empty">No unit recorded on FY {{ $year }} registrations.</p>
                    @else
                        <div class="hra-chart" data-hra-height="{{ (int) $unitHeight }}"><canvas id="unitChart" role="img" aria-label="Personnel by unit"></canvas></div>
                    @endif
                </div>
            </section>

            <section class="pcg-card" aria-labelledby="hra-rank-h">
                <div class="pcg-card__header">
                    <h3 id="hra-rank-h">
                        @if ($topRank)
                            Most common rank: {{ $topRank->rank }} ({{ number_format($topRank->total) }})
                        @else
                            Personnel by rank
                        @endif
                    </h3>
                </div>
                <div class="pcg-card__body">
                    @if (! $topRank)
                        <p class="pcg-empty">No rank recorded on FY {{ $year }} registrations.</p>
                    @else
                        <div class="hra-chart"><canvas id="rankChart" role="img" aria-label="Personnel by rank"></canvas></div>
                    @endif
                </div>
            </section>

            <section class="pcg-card" aria-labelledby="hra-course-h">
                <div class="pcg-card__header">
                    <h3 id="hra-course-h">
                        @if ($topCourse)
                            Most recorded course: {{ $topCourse->course }} ({{ number_format($topCourse->total) }})
                        @else
                            Courses on record
                        @endif
                    </h3>
                    @if ($topCourse)
                        <p class="hra-card-note">Top {{ count($chartData['course']['labels']) }} academic courses in the SOI of FY {{ $year }} registrants.</p>
                    @endif
                </div>
                <div class="pcg-card__body">
                    @if (! $topCourse)
                        <p class="pcg-empty">No academic courses recorded for FY {{ $year }} registrants.</p>
                    @else
                        <div class="hra-chart"><canvas id="academicChart" role="img" aria-label="Top academic courses"></canvas></div>
                    @endif
                </div>
            </section>

            <section class="pcg-card hra-wide" aria-labelledby="hra-year-h">
                <div class="pcg-card__header">
                    <h3 id="hra-year-h">
                        @if ($peakYear)
                            Registrations per year since 2023, highest in FY {{ $peakYear->year }} ({{ number_format($peakYear->total) }})
                        @else
                            Registrations per year
                        @endif
                    </h3>
                </div>
                <div class="pcg-card__body">
                    @if (! $peakYear)
                        <p class="pcg-empty">No registrations recorded since 2023.</p>
                    @else
                        <div class="hra-chart"><canvas id="yearlyChart" role="img" aria-label="Registrations per fiscal year"></canvas></div>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection

@section('scripts')
    <script type="application/json" id="hra-data" nonce="{{ $cspNonce ?? '' }}">@json($chartData)</script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/chartjs/chart-4.4.4.umd.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/hr-analytics.js') }}"></script>
@endsection
