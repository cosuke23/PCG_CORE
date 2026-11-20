@extends('layouts.admin')

@section('title', 'Readiness')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ number_format($totals['available']) }} of {{ number_format($totals['strength']) }} members are available {{ $day->isSameDay(\App\Support\LeaveCalendar::today()) ? 'today' : 'on ' . $day->format('F j') }} in {{ $unit !== '' ? $unit : $scopeName }}</h2>
                <p>Strength, approved leave and MEPTDI classes on the day you choose.</p>
            </div>
        </div>
        @include('partials.readiness', ['showUnitFilter' => true])
    </div>
@endsection
