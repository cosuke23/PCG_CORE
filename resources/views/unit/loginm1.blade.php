@extends('layouts.auth')

@section('title', 'M1 checker sign in')
@section('eyebrow', 'MEPCOM HRMIS · Document review')
@section('headline', 'M1 Document Checker')
@section('intro', 'Review personnel documents across all units, record decisions and manage 201 files and disciplinary records.')

@section('content')
    <span class="pcg-auth__role">M1 checker</span>
    <h2>Sign in</h2>
    <p class="pcg-auth__lead">For M1 document checkers with an active account.</p>

    @include('auth.partials.alerts')
    @include('auth.partials.credentials', ['action' => url('/unit/loginM1')])
@endsection
