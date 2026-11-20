@extends('layouts.admin')

@section('title', 'Deployments')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $activeCount === 1 ? '1 member is' : $activeCount . ' members are' }} deployed today in {{ $scopeName }}</h2>
                <p>Sea duty, deployments and details. Ending one can add RNR days to the member's credits; deployed members show in readiness.@unless ($canEdit) Your role can view these records but not change them.@endunless</p>
            </div>
            <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.leave.readiness') }}"><i class="fa-solid fa-people-group" aria-hidden="true"></i> Readiness</a>
        </div>
        @include('partials.deployments')
    </div>
@endsection
