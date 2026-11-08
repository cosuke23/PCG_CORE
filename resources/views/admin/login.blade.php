@extends('layouts.auth')

@php($reenlistment = ($variant ?? null) === 'reenlistment')
@php($adjutant = ($variant ?? null) === 'adjutant')

@section('title', $adjutant ? 'Adjutant sign in' : ($reenlistment ? 'Re-enlistment sign in' : 'Administrator sign in'))
@section('eyebrow', $adjutant ? 'MEPCOM HRMIS · Adjutant' : ($reenlistment ? 'MEPCOM HRMIS · Re-enlistment' : 'MEPCOM HRMIS · Administration'))
@section('headline', $adjutant ? 'Coast Guard awards and schooling' : ($reenlistment ? 'Re-enlistment of non-officers' : 'Administration and MEPTDI Learning Management'))
@section('intro', $adjutant
    ? 'Record Coast Guard awards and decorations and Coast Guard schooling in the Statement of Information of the personnel in your command or unit.'
    : ($reenlistment
        ? 'Non-officer personnel whose end of term of enlistment (ETE) falls within the coming year, with their unit, status and re-enlistment form.'
        : 'Approve registrations, review HR analytics, and manage training courses, applications and OSAERT records.'))

@section('content')
    <span class="pcg-auth__role">{{ $adjutant ? 'Adjutant' : ($reenlistment ? 'Re-enlistment admin' : 'Administrator') }}</span>
    <h2>Sign in</h2>
    <p class="pcg-auth__lead">{{ $adjutant ? 'For Adjutants. Sign in with your email and password, then enter the one-time code (OTP) sent to your email.' : ($reenlistment ? 'For re-enlistment administrators. Sign in with your email and password.' : 'Restricted to the system administrator.') }}</p>

    @include('auth.partials.alerts')
    @include('auth.partials.credentials', ['action' => route('admin.login.submit')])
@endsection
