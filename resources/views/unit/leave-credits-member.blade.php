@extends('layouts.unit')

@section('title', 'Leave credits')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    @php $annual = $credits['annual']; $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d); @endphp
    <div class="pcg-page">
        <p><a href="{{ route('unit.leave.credits') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All members</a></p>
        <div class="uc-head">
            <h2>
                @if (!$annual['set'])
                    {{ $m->name }} has no opening balance of ordinary leave credits yet
                @else
                    {{ $m->name }} has {{ $fmt($annual['available']) }} days of ordinary leave available
                @endif
            </h2>
            <p>{{ trim(($m->rank ?? '') . ' ' . $m->name) }} · <span class="pcg-mono">{{ $m->serial_number }}</span>@if ($m->mother_unit) · {{ $m->mother_unit }}@endif</p>
        </div>

        <section class="pcg-card pcg-mb" aria-label="Balances">
            <div class="pcg-card__body">@include('partials.leave-credit-cards', ['self' => false])</div>
        </section>

        <div class="lc-layout">
            <div class="lc-layout__main">
                <section class="pcg-card" aria-labelledby="hist-heading">
                    <div class="pcg-card__header"><h3 id="hist-heading">History</h3></div>
                    <div class="pcg-card__body">@include('partials.leave-credit-history')</div>
                </section>
            </div>

            <aside class="lc-layout__side">
                @include('partials.leave-credit-forms', ['formAction' => route('unit.leave.credits.store', $m->id)])
            </aside>
        </div>
    </div>
@endsection
