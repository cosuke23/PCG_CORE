@extends('layouts.auth')

@section('title', 'Unit checker sign in')
@section('eyebrow', 'MEPCOM HRMIS · Document review')
@section('headline', 'Unit Document Checker')
@section('intro', 'Review personnel documents submitted by your unit, record decisions and manage 201 files.')

@section('content')
    <span class="pcg-auth__role">Unit checker</span>
    <h2>Sign in</h2>
    <p class="pcg-auth__lead">For unit document checkers with an active account.</p>

    @include('auth.partials.alerts')
    @include('auth.partials.credentials', ['action' => url('/unit/login')])
@endsection
