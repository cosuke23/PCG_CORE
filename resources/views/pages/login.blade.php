@extends('layouts.auth')

@section('title', 'Personnel sign in')
@section('eyebrow', 'MEPCOM HRMIS')
@section('headline', 'Securing Marine Life Through Action and Integrity')
@section('intro', 'Welcome to the MEPCOM Human Resource Management Integrated System. Your gateway to efficient and professional service.')

@section('content')
    <span class="pcg-auth__role">Personnel</span>
    <h2>Sign in</h2>
    <p class="pcg-auth__lead">Use the email and password you registered with. New accounts must be approved by the administrator first.</p>

    @include('auth.partials.alerts')
    @include('auth.partials.credentials', ['action' => route('login.store')])

    <p class="pcg-help">Forgot your password? Contact the HRMIS administrator to have it reset.</p>

    <div class="pcg-auth__alt">
        <h3>No account yet? Register as:</h3>
        <div class="pcg-btn-grid">
            <a class="pcg-btn pcg-btn--secondary" href="{{ route('register', ['type' => 'officer']) }}">
                Officer
                <small>Commissioned officer</small>
            </a>
            <a class="pcg-btn pcg-btn--secondary" href="{{ route('register', ['type' => 'non_officer']) }}">
                Non-Officer
                <small>Enlisted personnel</small>
            </a>
        </div>
    </div>
@endsection
