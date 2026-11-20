@extends('layouts.personnel')

@section('title', 'Notifications')

@section('content')
    @php
        $icon = fn ($kind) => match (true) {
            str_starts_with($kind, 'leave') => ['calendar-days', 'blue'],
            str_starts_with($kind, 'clearance') => ['hourglass-half', 'rose'],
            str_starts_with($kind, '201') => ['folder-open', 'violet'],
            str_starts_with($kind, 'soi') => ['id-card', 'teal'],
            default => ['file-arrow-up', 'gold'],
        };
    @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $unread === 0 ? 'You have no new notifications' : ($unread === 1 ? 'You have 1 new notification' : "You have {$unread} new notifications") }}</h2>
                <p>Leave decisions, document reviews, expiring clearances and SOI updates. The Android app shows them on your phone too.</p>
            </div>
        </div>

        @if ($items->isEmpty())
            <p class="pcg-empty">Nothing yet. You will see updates here when your unit or HR acts on your records.</p>
        @else
            <ul class="pcg-rows" aria-label="Notifications">
                @foreach ($items as $n)
                    @php [$i, $c] = $icon($n->kind); @endphp
                    <li class="pcg-row">
                        <span class="pcg-row__icon pcg-row__icon--{{ $c }}" aria-hidden="true"><i class="fa-solid fa-{{ $i }}"></i></span>
                        <span class="pcg-row__name">{{ $n->title }}@if ($n->body)<small>{{ $n->body }}</small>@endif</span>
                        <span class="pcg-row__status {{ $n->read_at === null ? 'pcg-row__status--info' : '' }}">
                            {{ $n->read_at === null ? 'New · ' : '' }}{{ \Illuminate\Support\Carbon::parse($n->created_at)->timezone('Asia/Manila')->format('M j, g:i A') }}
                        </span>
                        @if ($n->url)
                            <a class="pcg-btn pcg-btn--secondary" href="{{ url($n->url) }}">Open</a>
                        @else
                            <span></span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
