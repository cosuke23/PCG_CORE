@extends('layouts.auth')

@section('title', 'Recovery codes')
@section('eyebrow', 'MEPCOM HRMIS · Administration')
@section('headline', 'Two-factor sign-in is on')
@section('intro', 'Keep these recovery codes somewhere safe and offline. Each one works once if you lose your phone.')

@section('content')
    <span class="pcg-auth__role">Save these now</span>
    <h2>Your recovery codes</h2>
    <p class="pcg-auth__lead">They are shown only once. Print them or write them down; do not store them on the same phone.</p>

    <ul class="pcg-codes" aria-label="Recovery codes">
        @foreach ($codes as $code)
            <li><code>{{ $code }}</code></li>
        @endforeach
    </ul>

    <a class="pcg-btn pcg-btn--primary pcg-btn--block" href="{{ route(\App\Http\Controllers\AdminLoginController::homeRoute(session('admin_role'))) }}">I have saved them — continue</a>
@endsection
