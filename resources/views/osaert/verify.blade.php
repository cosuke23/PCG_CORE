@extends('layouts.auth')

@section('title', 'Verify an OSAERT certificate')
@section('eyebrow', 'Certificate verification')
@section('headline', 'Oil Spill Awareness and Emergency Response Training')
@section('intro', 'Check that an OSAERT certificate was issued by MEPCOM. Enter the certificate number printed on it, or scan its QR code.')

@section('content')
    <span class="pcg-auth__role">OSAERT</span>
    <h2>Verify a certificate</h2>
    <p class="pcg-auth__lead">The number is printed at the top of the certificate, for example <strong>OSAERT-2025-000012</strong>.</p>

    @if ($notFound)
        <div class="pcg-alert pcg-alert--danger" role="alert" id="form-errors">
            No issued certificate matches <strong>{{ $number }}</strong>. Check the number and try again.
        </div>
    @endif

    <form method="GET" action="{{ route('osaert.verify') }}">
        <div class="pcg-field">
            <label class="pcg-label" for="certificate">Certificate number</label>
            <input class="pcg-input" type="text" id="certificate" name="certificate" value="{{ $number }}"
                placeholder="OSAERT-YYYY-NNNNNN" autocomplete="off" spellcheck="false" required autofocus
                @if ($notFound) aria-invalid="true" aria-describedby="form-errors" @endif>
        </div>
        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block">Verify</button>
    </form>
@endsection
