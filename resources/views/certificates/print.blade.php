<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ \App\Support\ServiceCertificates::KINDS[$c->kind] ?? 'Certificate' }} · {{ $snap['name'] ?? '' }} · PCG HRMIS</title>
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-print.css') }}">
</head>
@php
    $dt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('F j, Y') : '';
    $who = trim(($snap['rank'] ?? '') . ' ' . ($snap['name'] ?? ''));
@endphp
<body>
    <div class="lp-tools">
        <a href="{{ $back }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</a>
        <button type="button" data-on-click="pcg.print"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
    </div>

    <main class="lp-sheet">
        <header class="lp-head">
            <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="" width="64" height="64">
            <div>
                <p>Republic of the Philippines · Department of Transportation</p>
                <h1>Philippine Coast Guard</h1>
                <p>Marine Environmental Protection Command</p>
            </div>
            <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="" width="64" height="64">
        </header>

        <h2 class="lp-title">{{ \App\Support\ServiceCertificates::KINDS[$c->kind] ?? 'Certificate' }}</h2>
        <p class="lp-ref">Certificate number <span class="lp-mono">{{ $c->code }}</span> · issued {{ $dt($c->decided_at) }}</p>

        <section class="sc-body">
            <p>TO WHOM IT MAY CONCERN:</p>
            @if ($c->kind === 'assignment')
                <p>This is to certify that <strong>{{ $who }}</strong>, serial number <span class="lp-mono">{{ $snap['serial_number'] ?? '' }}</span>, is presently assigned
                    to <strong>{{ $snap['unit'] ?? '' }}</strong>{{ !empty($snap['position']) ? ' as ' : '' }}@if (!empty($snap['position']))<strong>{{ $snap['position'] }}</strong>@endif{{ !empty($snap['assigned_since']) ? ' since ' . $dt($snap['assigned_since']) : '' }}, Philippine Coast Guard.</p>
            @else
                <p>This is to certify that <strong>{{ $who }}</strong>, serial number <span class="lp-mono">{{ $snap['serial_number'] ?? '' }}</span>, is a member of the
                    Philippine Coast Guard{{ !empty($snap['entered_service']) ? ' who entered the service on ' . $dt($snap['entered_service']) : '' }} and is
                    {{ ($snap['status'] ?? 'active') === 'active' ? 'in active service' : 'recorded as ' . $snap['status'] }}, presently with <strong>{{ $snap['unit'] ?? '' }}</strong>.</p>
            @endif
            <p>This certification is issued upon the request of the above-named for <strong>{{ $c->purpose }}</strong>.</p>
        </section>

        <section class="lp-signs">
            <div><span class="lp-line"></span>&nbsp;<small>Authorized signatory</small></div>
            <div class="sc-qr">
                <div data-pcg-qr="{{ $verifyUrl }}" aria-label="QR code to check this certificate"></div>
                <small>Scan, or open {{ preg_replace('#^https?://#', '', url('/verify/certificate')) }} and enter <span class="lp-mono">{{ $c->code }}</span></small>
            </div>
        </section>

        <footer class="lp-foot">Prepared by {{ $c->decided_by_name ?: 'HR' }} from the PCG HRMIS personnel master. Not valid with erasures.</footer>
    </main>
    <script src="{{ asset('vendor/qrcodejs/qrcode-1.0.0.min.js') }}" nonce="{{ $cspNonce ?? '' }}"></script>
    <script src="{{ \App\Support\Asset::v('js/pcg-ui.js') }}" nonce="{{ $cspNonce ?? '' }}"></script>
</body>
</html>
