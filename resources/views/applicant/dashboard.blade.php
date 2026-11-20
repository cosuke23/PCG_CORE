@extends('layouts.personnel')

@section('title', 'Dashboard')

@section('content')
    @php
        $soiSubmitted = \App\Support\SoiLock::locked($user);
        $s = strtolower((string) $documentStatus);
        $docsMissing = max(0, $documentsTotal - $documentsUploaded);

        if ($rejectedCount > 0) {
            $hero = [
                'title' => $rejectedCount . ' ' . \Illuminate\Support\Str::plural('document', $rejectedCount) . ' ' . ($rejectedCount === 1 ? 'needs' : 'need') . ' your attention',
                'text' => "Your unit's document checker returned them. Read the remarks below and upload a corrected copy.",
                'cta' => 'Fix my documents', 'href' => route('upload.documents'), 'icon' => 'file-arrow-up',
            ];
        } elseif (!$soiSubmitted) {
            $hero = [
                'title' => 'Your SOI is not submitted yet',
                'text' => 'Complete all six steps of your Statement of Information, then submit it on the last step.',
                'cta' => 'Continue my SOI', 'href' => route('profile'), 'icon' => 'id-card',
            ];
        } elseif ($docsMissing > 0) {
            $hero = [
                'title' => $docsMissing . ' required ' . \Illuminate\Support\Str::plural('document', $docsMissing) . ' still to upload',
                'text' => 'Your SOI is submitted. Upload the remaining clearances and records for your unit to review.',
                'cta' => 'Upload documents', 'href' => route('upload.documents'), 'icon' => 'file-arrow-up',
            ];
        } else {
            $hero = [
                'title' => 'Your records are up to date',
                'text' => 'Your SOI is submitted and every required document is in. Take PCG HRMIS with you on your phone.',
                'cta' => 'Get the Android app', 'href' => route('mobile.app'), 'icon' => 'mobile-screen-button',
            ];
        }

        if ($rejectedCount > 0) {
            $docState = ['danger', 'Action needed: ' . $rejectedCount . ' returned'];
        } elseif ($s === 'approved') {
            $docState = ['ok', 'Approved'];
        } elseif ($s === 'pending') {
            $docState = ['warn', 'Under review'];
        } else {
            $docState = ['', 'Not submitted'];
        }
    @endphp

    <div class="pcg-page">
        <section class="pcg-hero" aria-labelledby="hero-heading">
            <img class="pcg-hero__seal" src="{{ asset('images/brand/pcg-logo.webp') }}" alt="" width="170" height="170">
            <p class="pcg-hero__eyebrow">
                <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="" width="28" height="28">
                Welcome aboard, {{ $user->name }}
            </p>
            <h2 id="hero-heading">{{ $hero['title'] }}</h2>
            <p>{{ $hero['text'] }}</p>
            <span class="pcg-hero__meta">{{ $user->unit_assignment ?: $user->mother_unit }} · Serial <span class="pcg-mono">{{ $user->serial_number }}</span></span>
            <a class="pcg-btn pcg-btn--cta" href="{{ $hero['href'] }}">
                <i class="fa-solid fa-{{ $hero['icon'] }}" aria-hidden="true"></i> {{ $hero['cta'] }}
            </a>
        </section>

        <h3 class="pcg-section-title" id="records-heading">My records</h3>
        <ul class="pcg-rows" aria-labelledby="records-heading">
            <li class="pcg-row">
                <span class="pcg-row__icon pcg-row__icon--blue" aria-hidden="true"><i class="fa-solid fa-id-card"></i></span>
                <span class="pcg-row__name">SOI profile<small>Statement of Information, six steps</small></span>
                @if ($soiSubmitted)
                    <span class="pcg-row__status pcg-row__status--ok">Submitted</span>
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('profile') }}">Open</a>
                @else
                    <span class="pcg-row__status pcg-row__status--info">In progress</span>
                    <a class="pcg-btn pcg-btn--action" href="{{ route('profile') }}">Continue</a>
                @endif
            </li>
            <li class="pcg-row">
                <span class="pcg-row__icon pcg-row__icon--gold" aria-hidden="true"><i class="fa-solid fa-file-arrow-up"></i></span>
                <span class="pcg-row__name">Required documents<small>{{ $documentsUploaded }} of {{ $documentsTotal }} uploaded</small></span>
                <span class="pcg-row__status {{ $docState[0] ? 'pcg-row__status--' . $docState[0] : '' }}">{{ $docState[1] }}</span>
                @if ($rejectedCount > 0 || $docsMissing > 0)
                    <a class="pcg-btn pcg-btn--action" href="{{ route('upload.documents') }}">{{ $rejectedCount > 0 ? 'Fix documents' : 'Upload' }}</a>
                @else
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('upload.documents') }}">Open</a>
                @endif
            </li>
            <li class="pcg-row">
                <span class="pcg-row__icon pcg-row__icon--rose" aria-hidden="true"><i class="fa-solid fa-folder-open"></i></span>
                <span class="pcg-row__name">201 files<small>Appointment, leave, awards and other records</small></span>
                <span class="pcg-row__status">Reviewed by your unit</span>
                <a class="pcg-btn pcg-btn--secondary" href="{{ route('upload.201') }}">Open</a>
            </li>
            @if ($leave)
                @php
                    $fmtDays = fn ($c) => $c && $c['set'] ? \App\Support\LeaveCredits::fmt($c['available']) : null;
                    $creditLine = collect(['Ordinary' => $fmtDays($leave['annual']), 'RNR' => $fmtDays($leave['rnr'])])
                        ->filter(fn ($v) => $v !== null)->map(fn ($v, $k) => $k . ' ' . $v . ' days')->implode(' · ');
                @endphp
                <li class="pcg-row">
                    <span class="pcg-row__icon pcg-row__icon--violet" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span>
                    <span class="pcg-row__name">Leave<small>{{ $creditLine !== '' ? $creditLine . ' available' : 'Pick dates on the calendar and file leave' }}</small></span>
                    @if ($leave['onLeave'])
                        <span class="pcg-row__status pcg-row__status--ok">On leave until {{ \Illuminate\Support\Carbon::parse($leave['next']->end_date)->format('M j') }}</span>
                    @elseif ($leave['pending'])
                        <span class="pcg-row__status pcg-row__status--info">{{ $leave['pending'] === 1 ? '1 request waiting' : $leave['pending'] . ' requests waiting' }}</span>
                    @elseif ($leave['next'])
                        <span class="pcg-row__status pcg-row__status--ok">Next: {{ \App\Support\LeaveCalendar::dateLabel((string) $leave['next']->start_date, (string) $leave['next']->end_date) }}</span>
                    @else
                        <span class="pcg-row__status">No leave filed</span>
                    @endif
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('leave.index') }}">{{ $leave['pending'] || $leave['next'] ? 'Open' : 'File leave' }}</a>
                </li>
            @endif
            <li class="pcg-row">
                <span class="pcg-row__icon pcg-row__icon--teal" aria-hidden="true"><i class="fa-solid fa-print"></i></span>
                <span class="pcg-row__name">Print my SOI<small>Ready-to-print Statement of Information</small></span>
                <span class="pcg-row__status {{ $soiSubmitted ? 'pcg-row__status--ok' : '' }}">{{ $soiSubmitted ? 'Final copy' : 'Draft copy' }}</span>
                <a class="pcg-btn pcg-btn--secondary" href="{{ route('generate.soi') }}">Open</a>
            </li>
        </ul>

        @if ($rejectedCount > 0)
            <section class="pcg-card pcg-mb" aria-labelledby="rejected-heading">
                <div class="pcg-card__header"><h3 id="rejected-heading">Documents returned by the reviewer</h3></div>
                <ul class="pcg-list pcg-returned">
                    @foreach ($rejectedDocs as $doc)
                        <li>
                            <strong>{{ $doc->doc_type }}</strong>
                            <span>{{ $doc->comment ?: 'No comment provided' }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <h3 class="pcg-section-title" id="more-heading">Training and services</h3>
        <div class="pcg-appgrid" role="list" aria-labelledby="more-heading">
            <a class="pcg-appcard" href="{{ route('applicant.administrative.training') }}" role="listitem">
                <span class="pcg-row__icon pcg-row__icon--violet" aria-hidden="true"><i class="fa-solid fa-graduation-cap"></i></span>
                <strong>Administrative track</strong>
                <span>Courses for your rank and year in grade.</span>
            </a>
            <a class="pcg-appcard" href="{{ route('applicant.operational.training') }}" role="listitem">
                <span class="pcg-row__icon pcg-row__icon--blue" aria-hidden="true"><i class="fa-solid fa-ship"></i></span>
                <strong>Operational track</strong>
                <span>Operational career track courses.</span>
            </a>
            <a class="pcg-appcard" href="{{ route('mobile.app') }}" role="listitem">
                <span class="pcg-row__icon pcg-row__icon--gold" aria-hidden="true"><i class="fa-solid fa-mobile-screen-button"></i></span>
                <strong>Android app</strong>
                <span>{{ $soiSubmitted ? 'Your records on your phone. Download it now.' : 'Available once your SOI is submitted.' }}</span>
            </a>
        </div>

        <section class="pcg-card" aria-labelledby="classes-heading">
            <div class="pcg-card__header"><h3 id="classes-heading">My MEPTDI classes · {{ $classes->count() }}</h3></div>
            @if ($classes->isEmpty())
                <p class="pcg-empty">You are not enrolled in a MEPTDI class yet.</p>
            @else
                <ul class="pcg-list">
                    @foreach ($classes as $class)
                        <li>
                            <strong>{{ $class->course_name ?? 'Course' }}</strong>
                            <span class="pcg-table__muted">
                                {{ $class->date_attended ? \Illuminate\Support\Carbon::parse($class->date_attended)->format('M d, Y') : '—' }}
                                @if ($class->date_ended) – {{ \Illuminate\Support\Carbon::parse($class->date_ended)->format('M d, Y') }}@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
