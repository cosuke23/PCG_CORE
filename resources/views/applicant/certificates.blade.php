@extends('layouts.personnel')

@section('title', 'Certificates')

@section('content')
    @php $waiting = $list->where('status', 'pending')->count(); $ready = $ready ?? true; @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $waiting ? ($waiting === 1 ? '1 certificate request is' : $waiting . ' certificate requests are') . ' waiting for HR' : 'Request a certificate of service or assignment' }}</h2>
                <p>HR checks your record and issues the certificate. You print it here; it carries a QR code anyone can scan to check it is genuine.</p>
            </div>
        </div>

        @if ($ready)
            <section class="pcg-card pcg-mb" aria-labelledby="req-h">
                <div class="pcg-card__header"><h3 id="req-h">New request</h3></div>
                <div class="pcg-card__body">
                    <form method="POST" action="{{ route('certificates.store') }}" class="lc-form">
                        @csrf
                        <div class="pcg-field">
                            <label class="pcg-label" for="c-kind">Certificate</label>
                            <select class="pcg-input" id="c-kind" name="kind" required>
                                @foreach (\App\Support\ServiceCertificates::KINDS as $k => $label)
                                    <option value="{{ $k }}" @selected(old('kind') === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="c-purpose">Purpose</label>
                            <input class="pcg-input" id="c-purpose" name="purpose" maxlength="200" required value="{{ old('purpose') }}" placeholder="e.g. housing loan application, scholarship of my child">
                        </div>
                        <button type="submit" class="pcg-btn pcg-btn--cta">Send request</button>
                    </form>
                </div>
            </section>
        @else
            <p class="pcg-alert pcg-alert--warning">Certificate requests open after a database update.</p>
        @endif

        @if ($list->isNotEmpty())
            <ul class="pcg-rows" aria-label="My certificate requests">
                @foreach ($list as $c)
                    <li class="pcg-row">
                        <span class="pcg-row__icon pcg-row__icon--violet" aria-hidden="true"><i class="fa-solid fa-file-signature"></i></span>
                        <span class="pcg-row__name">{{ \App\Support\ServiceCertificates::KINDS[$c->kind] ?? $c->kind }}<small>{{ $c->purpose }} · requested {{ \Illuminate\Support\Carbon::parse($c->created_at)->timezone('Asia/Manila')->format('M j, Y') }}@if ($c->decision_note) · {{ $c->decision_note }}@endif</small></span>
                        <span class="pcg-row__status {{ ['issued' => 'pcg-row__status--ok', 'pending' => 'pcg-row__status--info', 'declined' => 'pcg-row__status--danger', 'revoked' => 'pcg-row__status--danger'][$c->status] ?? '' }}">{{ \App\Support\ServiceCertificates::STATUSES[$c->status] ?? $c->status }}</span>
                        @if ($c->status === 'issued')
                            <a class="pcg-btn pcg-btn--action" href="{{ route('certificates.print', $c->id) }}">Print</a>
                        @else
                            <span></span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
