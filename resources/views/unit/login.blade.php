@extends('layouts.auth')

@section('title', 'Unit checker sign in')
@section('eyebrow', 'MEPCOM HRMIS · Document review')
@section('headline', 'Unit Document Checker')
@section('intro', 'Review personnel documents submitted by your unit, record decisions and manage 201 files.')

@section('glass', '1')

@section('content')
    <div class="pcg-auth__app">
        <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="" width="44" height="44">
        <div>
            <strong>PCG HRMIS</strong>
            <span>Unit checker</span>
        </div>
    </div>
    <span class="pcg-auth__role">Unit checker</span>
    <h2>Sign in</h2>
    <p class="pcg-auth__lead">For unit document checkers with an active account.</p>

    @include('auth.partials.alerts')
    @include('auth.partials.credentials', ['action' => url('/unit/login')])
@endsection
