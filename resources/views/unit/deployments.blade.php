@extends('layouts.unit')

@section('title', 'Deployments')

@section('content')
    <div class="pcg-page">
        <div class="uc-head">
            <h2>{{ $activeCount === 1 ? '1 member is' : $activeCount . ' members are' }} deployed today in {{ $scopeName }}</h2>
            <p>Sea duty, deployments and details. Ending one can add RNR days to the member's credits; deployed members show in readiness.</p>
        </div>
        @include('partials.deployments')
    </div>
@endsection
