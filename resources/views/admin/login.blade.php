@extends('layouts.auth')

@section('title', 'Administrator sign in')
@section('eyebrow', 'MEPCOM HRMIS · Administration')
@section('headline', 'Administration and MEPTDI Learning Management')
@section('intro', 'Approve registrations, review HR analytics, and manage training courses, applications and OSAERT records.')

@section('content')
    <span class="pcg-auth__role">Administrator</span>
    <h2>Sign in</h2>
    <p class="pcg-auth__lead">Restricted to the system administrator.</p>

    @include('auth.partials.alerts')
    @include('auth.partials.credentials', ['action' => route('admin.login.submit')])
@endsection
