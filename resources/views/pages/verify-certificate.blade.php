@extends('layouts.auth')

@section('title', 'Check a PCG certificate')
@section('eyebrow', 'Certificate check')
@section('headline', 'Certificates of service and assignment')
@section('intro', 'Check that a certificate printed from PCG HRMIS was issued by MEPCOM HR. Scan its QR code, or enter the certificate number printed on it.')

@section('content')
    <span class="pcg-auth__role">Certificate</span>
    @if ($cert)
        @php $dt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('F j, Y') : '—'; @endphp
        @if ($cert->status === 'issued')
            <div class="pcg-alert pcg-alert--success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> This certificate is genuine and valid.</div>
        @else
            <div class="pcg-alert pcg-alert--danger" role="alert"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> This certificate was revoked and is no longer valid.</div>
        @endif
        <dl class="pcg-dl">
            <div><dt>Certificate</dt><dd>{{ \App\Support\ServiceCertificates::KINDS[$cert->kind] ?? $cert->kind }}</dd></div>
            <div><dt>Number</dt><dd class="pcg-mono">{{ $cert->code }}</dd></div>
            <div><dt>Issued to</dt><dd>{{ trim(($snap['rank'] ?? '') . ' ' . ($snap['name'] ?? '')) }}</dd></div>
            <div><dt>Unit</dt><dd>{{ $snap['unit'] ?? '—' }}</dd></div>
            <div><dt>Issued on</dt><dd>{{ $dt($cert->decided_at) }}</dd></div>
            <div><dt>Purpose</dt><dd>{{ $cert->purpose }}</dd></div>
        </dl>
        <p class="pcg-help">Compare these details with the printed certificate. Anything different means the paper was changed.</p>
    @else
        <h2>Check a certificate</h2>
        @if ($code !== '')
            <div class="pcg-alert pcg-alert--danger" role="alert" id="form-errors">No issued certificate has the number <strong>{{ $code }}</strong>. Check it and try again.</div>
        @endif
        <form method="GET" action="{{ route('certificate.verify') }}">
            <div class="pcg-field">
                <label class="pcg-label" for="code">Certificate number</label>
                <input class="pcg-input pcg-mono" type="text" id="code" name="code" value="{{ $code }}" placeholder="XXXX-XXXX-XXXX"
                    autocomplete="off" spellcheck="false" required autofocus maxlength="14" @if ($code !== '') aria-invalid="true" aria-describedby="form-errors" @endif>
            </div>
            <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block">Check</button>
        </form>
    @endif
@endsection
