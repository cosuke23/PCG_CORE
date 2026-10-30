@extends('layouts.auth')

@section('title', 'Set up two-factor sign-in')
@section('eyebrow', 'MEPCOM HRMIS · Administration')
@section('headline', 'Two-factor sign-in is required')
@section('intro', 'Administrator accounts are protected with a code from an authenticator app on your phone, in addition to your password.')

@section('content')
    <span class="pcg-auth__role">Step 2 of 2</span>
    <h2>Set up your authenticator</h2>
    <p class="pcg-auth__lead">Install an authenticator app (Google Authenticator, Microsoft Authenticator, or similar), add an account, and scan this code.</p>

    @include('auth.partials.alerts')

    <div class="pcg-qr" data-pcg-qr="{{ $uri }}" role="img" aria-label="QR code for your authenticator app"></div>
    <p class="pcg-help">Can't scan? Enter this key manually (time-based):<br>
        <code class="pcg-code">{{ $secret }}</code></p>

    <form method="POST" action="{{ route('admin.two-factor.setup.confirm') }}" data-pcg-busy>
        @csrf
        <div class="pcg-field">
            <label class="pcg-label" for="code">6-digit code from the app</label>
            <input class="pcg-input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                pattern="[0-9 ]{6,7}" maxlength="7" required autofocus
                @error('code') aria-invalid="true" aria-describedby="form-errors" @enderror>
        </div>
        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Checking…">Turn on two-factor sign-in</button>
    </form>

    <script src="{{ asset('vendor/qrcodejs/qrcode-1.0.0.min.js') }}" defer></script>
@endsection
