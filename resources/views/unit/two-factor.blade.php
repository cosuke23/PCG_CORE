@extends('layouts.auth')

@section('title', 'Unit checker sign-in code')
@section('eyebrow', 'MEPCOM HRMIS · Unit checker')
@section('headline', 'Confirm it is you')
@section('intro', 'We sent a one-time code to your email to finish signing in.')

@section('glass', '1')

@section('content')
    <span class="pcg-auth__role">Step 2 of 2</span>
    <h2>Check your email</h2>
    <p class="pcg-auth__lead">Enter the 6-digit code sent to <strong>{{ $maskedEmail }}</strong>. It expires in {{ $minutes }} minutes and works once.</p>

    @include('auth.partials.alerts')

    <form method="POST" action="{{ route('unit.two-factor.verify') }}" data-pcg-busy>
        @csrf
        <div class="pcg-field">
            <label class="pcg-label" for="code">Sign-in code</label>
            <input class="pcg-input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                maxlength="32" required autofocus
                @error('code') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <p class="pcg-help">Check your spam folder if the email does not arrive within a minute.</p>
        </div>
        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Checking…">Verify</button>
    </form>

    <form method="POST" action="{{ route('unit.two-factor.resend') }}" class="pcg-auth__alt">
        @csrf
        <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--block">Didn't get it? Send a new code</button>
        <p class="pcg-help">A new code can be sent once a minute; it replaces the previous one.</p>
    </form>

    <div class="pcg-auth__alt">
        <a href="{{ $m1 ? url('/unit/loginM1') : url('/unit/login') }}">Use a different account</a>
    </div>
@endsection
