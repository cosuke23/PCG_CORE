@extends('layouts.admin')

@section('title', 'Leave credits')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    @php
        $annual = $credits['annual'];
        $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d);
        $badge = ['pending' => 'warning', 'approved' => 'success', 'declined' => 'danger', 'cancelled' => 'info'];
    @endphp
    <div class="pcg-page">
        <p><a href="{{ route('admin.leave.credits') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All members</a></p>
        <div class="pcg-page__header">
            <div>
                <h2>
                    @if (!$annual['set'])
                        {{ $m->name }} has no opening balance of ordinary leave credits yet
                    @else
                        {{ $m->name }} has {{ $fmt($annual['available']) }} days of ordinary leave available
                    @endif
                </h2>
                <p>{{ trim(($m->rank ?? '') . ' ' . $m->name) }} · <span class="pcg-mono">{{ $m->serial_number }}</span>@if ($m->mother_unit) · {{ $m->mother_unit }}@endif</p>
            </div>
        </div>

        <section class="pcg-card pcg-mb" aria-label="Balances">
            <div class="pcg-card__body">@include('partials.leave-credit-cards', ['self' => false])</div>
        </section>

        <div class="lc-layout">
            <div class="lc-layout__main">
                <section class="pcg-card pcg-mb" aria-labelledby="hist-heading">
                    <div class="pcg-card__header"><h3 id="hist-heading">Credit history</h3></div>
                    <div class="pcg-card__body">@include('partials.leave-credit-history')</div>
                </section>
                <section class="pcg-card" aria-labelledby="recent-heading">
                    <div class="pcg-card__header"><h3 id="recent-heading">Recent leave</h3></div>
                    @if ($recent->isEmpty())
                        <p class="pcg-empty">No leave filed yet.</p>
                    @else
                        <ul class="lc-mine">
                            @foreach ($recent as $r)
                                @php $st = strtolower((string) $r->status); @endphp
                                <li class="lc-mine__item">
                                    <div class="lc-mine__top">
                                        <strong>{{ \App\Support\LeaveTypes::label($r->leave_type) }}</strong>
                                        <span class="pcg-badge pcg-badge--{{ $badge[$st] ?? 'info' }}">{{ ucfirst($st) }}</span>
                                    </div>
                                    <div class="lc-muted">{{ \App\Support\LeaveCalendar::dateLabel((string) $r->start_date, (string) $r->end_date) }} · {{ $r->days }} {{ (int) $r->days === 1 ? 'day' : 'days' }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <aside class="lc-layout__side">
                @if ($canEdit)
                    @include('partials.leave-credit-forms', ['formAction' => route('admin.leave.credits.store', $m->id)])
                @else
                    <section class="pcg-card"><p class="pcg-empty">Your role can view credits but not change them.</p></section>
                @endif
            </aside>
        </div>
    </div>
@endsection
