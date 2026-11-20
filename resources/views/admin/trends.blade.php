@extends('layouts.admin')

@section('title', 'Trends')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ number_format($strength) }} active members in {{ $scopeName }} · {{ $eteYear === 1 ? '1 term of enlistment ends' : number_format($eteYear) . ' terms of enlistment end' }} in the next 12 months</h2>
                <p>Strength from the personnel master; ETE, leave and deployments from the member records. Point at a bar for its value; each chart can also be shown as a table.</p>
            </div>
            <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.leave.readiness') }}"><i class="fa-solid fa-people-group" aria-hidden="true"></i> Readiness today</a>
        </div>

        <div class="tr-grid">
            @include('admin.partials.chart', ['id' => 'tr-rank', 'title' => 'Strength by rank', 'rows' => $rank, 'kind' => 'bars', 'unitLabel' => 'members'])
            @include('admin.partials.chart', ['id' => 'tr-unit', 'title' => 'Strength by unit', 'rows' => $unit, 'kind' => 'bars', 'unitLabel' => 'members'])
            @include('admin.partials.chart', ['id' => 'tr-ete', 'title' => 'Terms of enlistment ending, next 24 months (' . number_format($eteTotal) . ')', 'rows' => $ete, 'kind' => 'columns', 'unitLabel' => 'ETE'])
            @include('admin.partials.chart', ['id' => 'tr-leave', 'title' => 'Leave days taken, last 12 months', 'rows' => $leave, 'kind' => 'columns', 'unitLabel' => 'days'])
            @include('admin.partials.chart', ['id' => 'tr-dep', 'title' => 'Members deployed, last 12 months', 'rows' => $deployed, 'kind' => 'columns', 'unitLabel' => 'members'])
        </div>
    </div>
@endsection
