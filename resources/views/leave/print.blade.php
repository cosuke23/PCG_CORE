<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Leave form · {{ $l->name }} · {{ 'PCG HRMIS' }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-print.css') }}">
</head>
@php
    $st = strtolower((string) $l->status);
    $statusText = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'declined' => 'Declined', 'cancelled' => 'Cancelled'][$st] ?? ucfirst($st);
    $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d);
    $dt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('F j, Y') : '';
@endphp
<body>
    <div class="lp-tools">
        <a href="{{ $back }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</a>
        <button type="button" data-on-click="pcg.print"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
    </div>

    <main class="lp-sheet {{ $st !== 'approved' ? 'is-not-approved' : '' }}" data-status="{{ $statusText }}">
        <header class="lp-head">
            <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="" width="64" height="64">
            <div>
                <p>Republic of the Philippines · Department of Transportation</p>
                <h1>Philippine Coast Guard</h1>
                <p>Marine Environmental Protection Command</p>
            </div>
            <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="" width="64" height="64">
        </header>

        <h2 class="lp-title">Application for leave</h2>
        <p class="lp-ref">Reference <span class="lp-mono">LV-{{ str_pad((string) $l->id, 6, '0', STR_PAD_LEFT) }}</span> · filed {{ $dt($l->created_at) }}</p>

        <section class="lp-grid">
            <div><span>Name</span><strong>{{ $l->name }}</strong></div>
            <div><span>Rank</span><strong>{{ $l->rank ?: '—' }}</strong></div>
            <div><span>Serial number</span><strong class="lp-mono">{{ $l->serial_number }}</strong></div>
            <div><span>Unit</span><strong>{{ $l->mother_unit ?: '—' }}</strong></div>
        </section>

        <section class="lp-grid">
            <div class="lp-wide"><span>Type of leave</span><strong>{{ \App\Support\LeaveTypes::label($l->leave_type) }}</strong></div>
            <div><span>From</span><strong>{{ $dt($l->start_date) }}</strong></div>
            <div><span>To</span><strong>{{ $dt($l->end_date) }}</strong></div>
            <div><span>Number of days</span><strong>{{ $l->days }} {{ \App\Support\LeaveCalendar::countMode() === 'working' ? 'working' : '' }} {{ (int) $l->days === 1 ? 'day' : 'days' }}</strong></div>
            <div><span>Status</span><strong>{{ $statusText }}</strong></div>
            <div class="lp-wide"><span>Reason or details</span><strong>{{ $l->reason ?: '—' }}</strong></div>
            @if ($credit)
                <div class="lp-wide"><span>{{ $credit['label'] }}</span><strong>{{ $credit['set'] ? $fmt($credit['balance']) . ' days balance as of ' . $printedAt->format('F j, Y') : 'Not set' }}</strong></div>
            @endif
        </section>

        <section class="lp-grid">
            <div><span>Decided by</span><strong>{{ $l->reviewer_name ?: '—' }}</strong></div>
            <div><span>Date decided</span><strong>{{ $dt($l->reviewed_at) ?: '—' }}</strong></div>
            @if ($l->review_note)
                <div class="lp-wide"><span>Remarks</span><strong>{{ $l->review_note }}</strong></div>
            @endif
        </section>

        <section class="lp-signs">
            <div><span class="lp-line"></span>{{ trim(($l->rank ?? '') . ' ' . $l->name) }}<small>Applicant</small></div>
            <div><span class="lp-line"></span>&nbsp;<small>Unit / approving officer</small></div>
        </section>

        <footer class="lp-foot">Printed {{ $printedAt->format('F j, Y g:i A') }} from {{ 'PCG HRMIS' }}.</footer>
    </main>
    <script src="{{ \App\Support\Asset::v('js/pcg-ui.js') }}" nonce="{{ $cspNonce ?? '' }}"></script>
</body>
</html>
