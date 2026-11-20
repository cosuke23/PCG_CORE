@extends('layouts.unit')

@section('title', 'Leave approval')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    @php $scopeName = $unit === null ? 'all units' : $unit; @endphp
    <div class="pcg-page">
        <div class="uc-head">
            <h2>
                @if ($pending->isEmpty())
                    No leave is waiting for approval in {{ $scopeName }}
                @else
                    {{ $pending->count() === 1 ? '1 leave request is' : $pending->count() . ' leave requests are' }} waiting for your approval
                @endif
            </h2>
            <p>Approve or decline each request. Approved leave shows on the calendar below and on the calendar of everyone in the unit.</p>
        </div>

        @if ($isM1)
            <form method="GET" action="{{ route('unit.leave') }}" class="lc-filter pcg-mb">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <label class="pcg-label" for="unit-filter">Unit</label>
                <select class="pcg-input pcg-input--sm" id="unit-filter" name="unit">
                    <option value="">All units</option>
                    @foreach ($units as $u)
                        <option value="{{ $u }}" @selected($unit !== null && strcasecmp($unit, $u) === 0)>{{ $u }}</option>
                    @endforeach
                </select>
                <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm" data-pcg-no-busy>Show</button>
            </form>
        @endif

        <div class="lc-layout">
            <div class="lc-layout__main">
                <section class="pcg-card pcg-mb" aria-label="Unit leave calendar">
                    <div class="pcg-card__body">
                        @include('partials.leave-calendar', [
                            'selectable' => false,
                            'prevUrl' => route('unit.leave', $query + ['month' => $prev]),
                            'nextUrl' => route('unit.leave', $query + ['month' => $next]),
                            'caption' => 'Approved leave in ' . $scopeName . '. Open a day to see everyone who is away.',
                        ])
                    </div>
                </section>
                @include('partials.leave-legend')
            </div>

            <aside class="lc-layout__side">
                <section class="pcg-card" aria-labelledby="leave-pending-heading">
                    <div class="pcg-card__header"><h3 id="leave-pending-heading">Waiting for approval</h3></div>
                    @if ($pending->isEmpty())
                        <p class="pcg-empty">Nothing to decide right now.</p>
                    @else
                        <ul class="lc-mine">
                            @foreach ($pending as $r)
                                <li class="lc-mine__item" id="leave-{{ $r->id }}">
                                    <div class="lc-mine__top">
                                        <span class="lc-chip lc-c{{ \App\Support\LeaveCalendar::colour($r->serial_number) }}"><span class="lc-chip__ini">{{ \App\Support\LeaveCalendar::initials($r->name) }}</span></span>
                                        <strong>{{ trim(($r->rank ?? '') . ' ' . $r->name) }}</strong>
                                    </div>
                                    <div class="lc-muted"><span class="pcg-mono">{{ $r->serial_number }}</span>@if ($isM1 && $r->mother_unit) · {{ $r->mother_unit }}@endif</div>
                                    <div><strong>{{ \App\Support\LeaveTypes::label($r->leave_type) }}</strong></div>
                                    <div class="lc-muted">{{ \App\Support\LeaveCalendar::dateLabel((string) $r->start_date, (string) $r->end_date) }} · {{ $r->days }} {{ (int) $r->days === 1 ? 'day' : 'days' }}</div>
                                    @if ($r->reason)
                                        <div class="lc-mine__note"><i class="fa-solid fa-quote-left" aria-hidden="true"></i> {{ $r->reason }}</div>
                                    @endif
                                    @if ($r->credit)
                                        <div class="lcr-avail{{ $r->shortfall ? ' is-empty' : '' }}">
                                            {{ $r->credit['label'] }}: {{ $r->credit['set'] ? \App\Support\LeaveCredits::fmt($r->credit['balance']) . ' days' : 'not set' }}
                                            @if ($r->shortfall) · not enough to approve @else · {{ \App\Support\LeaveCredits::fmt($r->credit['balance'] - $r->days) }} left after this @endif
                                            · <a href="{{ route('unit.leave.credits.show', $r->user_id) }}">credits</a>
                                        </div>
                                    @endif
                                    <form method="POST" action="{{ route('unit.leave.review', $r->id) }}" class="lc-review">
                                        @csrf
                                        <label class="pcg-label" for="note-{{ $r->id }}">Note to the member <span class="lc-muted">(needed to decline)</span></label>
                                        <input class="pcg-input pcg-input--sm" id="note-{{ $r->id }}" name="note" maxlength="500" placeholder="e.g. Ship deploys on these dates">
                                        <div class="lc-review__actions">
                                            <button type="submit" name="decision" value="approve" class="pcg-btn pcg-btn--primary pcg-btn--sm">
                                                <i class="fa-solid fa-check" aria-hidden="true"></i> Approve
                                            </button>
                                            <button type="submit" name="decision" value="decline" class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                                    data-pcg-confirm="Decline this leave of {{ $r->name }}? The member sees your note."
                                                    data-pcg-confirm-title="Decline leave" data-pcg-confirm-ok="Decline" data-pcg-confirm-danger>
                                                Decline
                                            </button>
                                        </div>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </aside>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ \App\Support\Asset::v('js/leave-calendar.js') }}" nonce="{{ $cspNonce ?? '' }}"></script>
@endsection
