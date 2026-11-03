@extends('layouts.auth')

@php($reenlistment = ($variant ?? null) === 'reenlistment')

@section('title', $reenlistment ? 'Re-enlistment sign in' : 'Administrator sign in')
@section('eyebrow', $reenlistment ? 'MEPCOM HRMIS · Re-enlistment' : 'MEPCOM HRMIS · Administration')
@section('headline', $reenlistment ? 'Re-enlistment of non-officers' : 'Administration and MEPTDI Learning Management')
@section('intro', $reenlistment
    ? 'Non-officer personnel whose end of term of enlistment (ETE) falls within the coming year, with their unit, status and re-enlistment form.'
    : 'Approve registrations, review HR analytics, and manage training courses, applications and OSAERT records.')

@section('content')
    <span class="pcg-auth__role">{{ $reenlistment ? 'Re-enlistment admin' : 'Administrator' }}</span>
    <h2>Sign in</h2>
    <p class="pcg-auth__lead">{{ $reenlistment ? 'For re-enlistment administrators. A one-time code is sent to your email after the password.' : 'Restricted to the system administrator.' }}</p>

    @include('auth.partials.alerts')
    @include('auth.partials.credentials', ['action' => route('admin.login.submit')])
@endsection
