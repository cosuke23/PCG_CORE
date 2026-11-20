@extends('layouts.personnel')

@section('title', 'My duties')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                @php $next = $list->first(); @endphp
                <h2>{{ $next ? 'Your next duty: ' . $next->post . ', ' . \Illuminate\Support\Carbon::parse($next->duty_date)->format('l, F j') : 'You have no duty rostered' }}</h2>
                <p>Duties your unit has rostered for you. You are notified when one is added or removed.</p>
            </div>
        </div>
        @if ($list->isNotEmpty())
            <ul class="pcg-rows" aria-label="Upcoming duties">
                @foreach ($list as $d)
                    <li class="pcg-row">
                        <span class="pcg-row__icon pcg-row__icon--teal" aria-hidden="true"><i class="fa-solid fa-clipboard-user"></i></span>
                        <span class="pcg-row__name">{{ $d->post }}<small>{{ \App\Support\DutyRoster::SHIFTS[$d->shift] ?? $d->shift }}{{ $d->note ? ' · ' . $d->note : '' }}</small></span>
                        <span class="pcg-row__status">{{ \Illuminate\Support\Carbon::parse($d->duty_date)->format('D M j, Y') }}</span>
                        <span></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
