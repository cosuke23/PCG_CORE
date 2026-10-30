{{-- Public page reached from the QR code on a printed OSAERT certificate. --}}
@extends('layouts.auth')

@section('title', 'OSAERT certificate verified')
@section('eyebrow', 'Certificate verification')
@section('headline', 'Oil Spill Awareness and Emergency Response Training')
@section('intro', 'This page confirms an OSAERT certificate against the MEPCOM training records.')

@section('content')
    <span class="pcg-auth__role">OSAERT</span>

    <div class="pcg-alert pcg-alert--success" role="status">
        <strong>Certificate verified.</strong> It matches the training records.
    </div>

    <h2>{{ $trainee->name }}</h2>
    <p class="pcg-auth__lead">Certificate No. <strong>{{ $trainee->certificate_number }}</strong></p>

    <dl class="pcg-dl">
        <div>
            <dt>Company / organization</dt>
            <dd>{{ $trainee->company ?: '—' }}</dd>
        </div>
        <div>
            <dt>Training</dt>
            <dd>Oil Spill Awareness and Emergency Response Training</dd>
        </div>
        <div>
            <dt>Date started</dt>
            <dd>{{ $trainee->date_attended ?: '—' }}</dd>
        </div>
        <div>
            <dt>Date completed</dt>
            <dd>{{ $trainee->date_ended }}</dd>
        </div>
    </dl>

    <div class="pcg-auth__alt">
        <a class="pcg-btn pcg-btn--secondary pcg-btn--block" href="{{ route('osaert.verify') }}">Verify another certificate</a>
    </div>
@endsection
