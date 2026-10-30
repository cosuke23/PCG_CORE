@extends('layouts.auth')

@section('title', 'Two-factor sign-in')
@section('eyebrow', 'MEPCOM HRMIS · Administration')
@section('headline', 'Confirm it is you')
@section('intro', 'Enter the code from your authenticator app to finish signing in.')

@section('content')
    <span class="pcg-auth__role">Step 2 of 2</span>
    <h2>Enter your code</h2>
    <p class="pcg-auth__lead">Open your authenticator app and enter the current 6-digit code for MEPCOM HRMIS.</p>

    @include('auth.partials.alerts')

    <form method="POST" action="{{ route('admin.two-factor.challenge.verify') }}" data-pcg-busy>
        @csrf
        <div class="pcg-field">
            <label class="pcg-label" for="code">Authentication code</label>
            <input class="pcg-input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                maxlength="32" required autofocus
                @error('code') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <p class="pcg-help">Lost your phone? Enter one of your recovery codes (XXXXX-XXXXX) instead.</p>
        </div>
        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Checking…">Verify</button>
    </form>

    <div class="pcg-auth__alt">
        <a href="{{ route('admin.login') }}">Use a different account</a>
    </div>
@endsection
