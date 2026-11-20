@extends('layouts.unit')

@section('title', 'Leave summary')

@section('content')
    <div class="pcg-page">
        <div class="uc-head">
            <h2>{{ $total->members === 1 ? '1 member' : $total->members . ' members' }} {{ $isM1 && $unit === '' ? '' : 'of ' . $unit . ' ' }}took {{ $total->days }} leave {{ $total->days === 1 ? 'day' : 'days' }} in {{ $month->format('F Y') }}</h2>
            <p>Monthly leave summary by unit and type, and the CSV download.</p>
        </div>
        @include('partials.leave-report', ['showUnitFilter' => $isM1])
    </div>
@endsection
