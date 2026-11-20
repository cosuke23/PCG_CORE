@extends('layouts.admin')

@section('title', 'Leave summary')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $total->members === 1 ? '1 member' : number_format($total->members) . ' members' }} took {{ number_format($total->days) }} leave {{ $total->days === 1 ? 'day' : 'days' }} in {{ $month->format('F Y') }} in {{ $scopeName }}</h2>
                <p>Monthly leave summary by unit and type, and the CSV download.</p>
            </div>
            <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.leave.index') }}"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Leave requests</a>
        </div>
        @include('partials.leave-report', ['showUnitFilter' => true])
    </div>
@endsection
