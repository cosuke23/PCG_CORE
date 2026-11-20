@extends('layouts.unit')

@section('title', 'Readiness')

@section('content')
    <div class="pcg-page">
        <div class="uc-head">
            <h2>{{ $totals['available'] }} of {{ $totals['strength'] }} {{ $isM1 && $unit === '' ? 'members' : 'members of ' . ($unit ?: 'your unit') }} are available {{ $day->isSameDay(\App\Support\LeaveCalendar::today()) ? 'today' : 'on ' . $day->format('F j') }}</h2>
            <p>Strength, approved leave and MEPTDI classes on the day you choose.</p>
        </div>
        @include('partials.readiness', ['showUnitFilter' => $isM1])
    </div>
@endsection
