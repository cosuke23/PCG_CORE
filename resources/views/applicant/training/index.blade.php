@extends('layouts.personnel')

@section('title', $type === 'ACT' ? 'Administrative track' : 'Operational track')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/training-tracks.css') }}">
@endsection

@php
    $isAct = $type === 'ACT';
    $trackName = $isAct ? 'Administrative Career and Training Track' : 'Operational Career and Training Track';
    $years = ['1' => '1 year', '2' => '2 years', 'more_than_2' => 'More than 2 years'];
    $statusKey = fn ($s) => $s ? strtolower(trim($s)) : 'open';
    $statusLabel = ['pending' => 'Application pending', 'approved' => 'Approved', 'rejected' => 'Not approved', 'completed' => 'Completed', 'open' => 'Open for application'];
    // a package lists several courses separated by ";"
    $courses = fn ($text) => array_values(array_filter(array_map('trim', preg_split('/;\s*/', (string) $text))));
    $counts = $tracks->groupBy(fn ($t) => $statusKey($t->application_status))->map->count();
@endphp

@section('content')
    <div class="pcg-page tt">
        <header class="tt-hero">
            <div>
                <p class="tt-eyebrow">Training · {{ $type }}</p>
                <h1>{{ $trackName }}</h1>
                <p class="tt-lede">Courses set for your rank and years in grade. Open a course package to see every course in it, then apply. HR reviews each application.</p>
            </div>
            <a class="tt-switch" href="{{ route($isAct ? 'applicant.operational.training' : 'applicant.administrative.training', ['year_in_grade' => $yearFilter]) }}">
                <i class="fa-solid fa-right-left" aria-hidden="true"></i>
                {{ $isAct ? 'Operational track' : 'Administrative track' }}
            </a>
        </header>

        <section class="tt-profile" aria-label="Your track settings">
            <div class="tt-fact">
                <span class="tt-fact__label">Current rank</span>
                <strong class="tt-fact__value">{{ $rank ?: 'Not recorded' }}</strong>
            </div>
            <div class="tt-fact">
                <span class="tt-fact__label">Track rank</span>
                <strong class="tt-fact__value tt-fact__value--accent">{{ $equivalentRank ?? 'Not defined' }}</strong>
            </div>
            <div class="tt-fact tt-fact--wide">
                <span class="tt-fact__label" id="yig-label">Years in grade</span>
                <nav class="tt-years" aria-labelledby="yig-label">
                    @foreach ($years as $value => $label)
                        <a href="{{ request()->fullUrlWithQuery(['year_in_grade' => $value]) }}"
                            @if ((string) $yearFilter === (string) $value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
        </section>

        @if (!$equivalentRank)
            <section class="tt-empty tt-empty--warn" role="status">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div>
                    <h2>Your rank has no {{ $type }} track yet</h2>
                    <p>Your current rank ({{ $rank ?: 'not recorded' }}) is not linked to a training track rank. Ask HR to check your rank in your record.</p>
                </div>
            </section>
        @elseif ($tracks->isEmpty())
            <section class="tt-empty" role="status">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <div>
                    <h2>No courses for {{ $years[(string) $yearFilter] ?? 'this year' }} in grade</h2>
                    <p>There are no {{ $type }} courses set for {{ $equivalentRank }} at this year in grade. Try another year above.</p>
                </div>
            </section>
        @else
            <p class="tt-summary">
                <strong>{{ $tracks->count() }}</strong> {{ $tracks->count() === 1 ? 'course package' : 'course packages' }}
                @foreach (['pending' => 'pending', 'approved' => 'approved', 'completed' => 'completed'] as $k => $word)
                    @if (($counts[$k] ?? 0) > 0)
                        <span class="tt-dot tt-dot--{{ $k }}">{{ $counts[$k] }} {{ $word }}</span>
                    @endif
                @endforeach
            </p>

            <div class="tt-grid">
                @foreach ($tracks as $track)
                    @php
                        $key = $statusKey($track->application_status);
                        $list = $courses($track->course_training);
                        $unit = str_replace('?', '/', (string) $track->division_unit);
                        $unitCode = trim(explode('/', $unit)[0]);
                        $data = [
                            'id' => $track->id,
                            'type' => $type,
                            'rank' => $track->rank,
                            'courses' => $list,
                            'unit' => $unit,
                            'year' => $track->year_in_grade,
                            'provider' => $track->provider,
                            'remarks' => $track->remarks,
                            'status' => $key,
                            'statusLabel' => $statusLabel[$key] ?? ucfirst((string) $track->application_status),
                        ];
                    @endphp
                    <article class="tt-card tt-card--{{ $key }}" data-track='@json($data)' tabindex="0" role="button"
                        aria-label="{{ $unitCode ?: 'Course package' }}: {{ count($list) }} {{ count($list) === 1 ? 'course' : 'courses' }}, {{ $statusLabel[$key] ?? $track->application_status }}. Open details.">
                        <div class="tt-card__top">
                            <span class="tt-unit">{{ $unitCode ?: 'Course package' }}</span>
                            <span class="tt-badge tt-badge--{{ $key }}">{{ $statusLabel[$key] ?? $track->application_status }}</span>
                        </div>
                        <p class="tt-card__rank">{{ $track->rank }} · {{ count($list) }} {{ count($list) === 1 ? 'course' : 'courses' }}</p>
                        <ul class="tt-courses">
                            @foreach (array_slice($list, 0, 4) as $c)
                                <li>{{ $c }}</li>
                            @endforeach
                        </ul>
                        @if (count($list) > 4)
                            <p class="tt-more">+ {{ count($list) - 4 }} more</p>
                        @endif
                        <dl class="tt-meta">
                            <div><dt>Unit</dt><dd>{{ $unit ?: '—' }}</dd></div>
                            @if ($track->provider)
                                <div><dt>Provider</dt><dd>{{ $track->provider }}</dd></div>
                            @endif
                        </dl>
                        <span class="tt-card__cta">{{ $key === 'open' ? 'View and apply' : 'View details' }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                    </article>
                @endforeach
            </div>

            <dialog class="tt-dialog" id="tt-dialog" aria-labelledby="tt-d-title">
                <div class="tt-dialog__inner">
                    <div class="tt-dialog__head">
                        <div>
                            <p class="tt-eyebrow" id="tt-d-eyebrow"></p>
                            <h2 id="tt-d-title"></h2>
                        </div>
                        <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" id="tt-d-close" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <span class="tt-badge" id="tt-d-status"></span>
                    <h3 class="tt-dialog__sub">Courses in this package</h3>
                    <ol class="tt-dialog__courses" id="tt-d-courses"></ol>
                    <dl class="pcg-dl" id="tt-d-meta"></dl>
                    <p class="tt-dialog__note" id="tt-d-remarks" hidden></p>
                    <div class="tt-dialog__actions">
                        <button type="button" class="pcg-btn pcg-btn--secondary" id="tt-d-cancel">Close</button>
                        <form method="POST" action="{{ route('applicant.training.store') }}" id="tt-d-form" hidden>
                            @csrf
                            <input type="hidden" name="track_id" id="tt-d-id">
                            <input type="hidden" name="type" id="tt-d-type">
                            <button type="submit" class="pcg-btn pcg-btn--primary">Apply for this training</button>
                        </form>
                    </div>
                </div>
            </dialog>
        @endif
    </div>
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/training-tracks.js') }}"></script>
@endsection
