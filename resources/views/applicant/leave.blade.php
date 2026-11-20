@extends('layouts.personnel')

@section('title', 'Leave')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    @php
        $waiting = $mine->filter(fn ($r) => strtolower((string) $r->status) === 'pending')->count();
        $statusBadge = ['pending' => 'warning', 'approved' => 'success', 'declined' => 'danger', 'cancelled' => 'info'];
        $statusLabel = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];
        $today = \App\Support\LeaveCalendar::today()->toDateString();
    @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                @if ($waiting)
                    <h2>{{ $waiting === 1 ? '1 leave request is' : $waiting . ' leave requests are' }} waiting for your unit's approval</h2>
                @else
                    <h2>Pick your leave dates on the calendar</h2>
                @endif
                <p>Click the first day, then the last day, choose the type of leave and file it. Your unit checker approves it;
                    approved leave shows on the calendar for everyone in {{ $unit !== '' ? $unit : 'your unit' }}.</p>
            </div>
        </div>

        <div class="lc-layout">
            <div class="lc-layout__main">
                <section class="pcg-card pcg-mb" aria-label="Leave calendar">
                    <div class="pcg-card__body">
                        @include('partials.leave-calendar', [
                            'selectable' => true,
                            'prevUrl' => route('leave.index', ['month' => $prev]),
                            'nextUrl' => route('leave.index', ['month' => $next]),
                            'caption' => $unit !== '' ? 'Approved leave in ' . $unit . '. Your requests waiting for approval have a dashed outline.' : 'Your unit is not set on your record yet, so only your own leave is shown.',
                        ])
                    </div>
                </section>
                @include('partials.leave-legend')
            </div>

            <aside class="lc-layout__side">
                <section class="pcg-card pcg-mb" aria-labelledby="leave-credits-heading">
                    <div class="pcg-card__header"><h3 id="leave-credits-heading">My leave credits</h3></div>
                    <div class="pcg-card__body">@include('partials.leave-credit-cards', ['self' => true])</div>
                </section>

                <section class="pcg-card pcg-mb" aria-labelledby="leave-form-heading">
                    <div class="pcg-card__header"><h3 id="leave-form-heading">File leave</h3></div>
                    <div class="pcg-card__body">
                        <form method="POST" action="{{ route('leave.store') }}" id="leave-form" class="lc-form">
                            @csrf
                            <div class="lc-form__dates">
                                <div class="pcg-field">
                                    <label class="pcg-label" for="leave-start">First day</label>
                                    <input class="pcg-input" type="date" id="leave-start" name="start_date" required
                                           min="{{ $minDate }}" value="{{ old('start_date') }}" data-lc-start>
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="leave-end">Last day</label>
                                    <input class="pcg-input" type="date" id="leave-end" name="end_date" required
                                           min="{{ $minDate }}" value="{{ old('end_date') }}" data-lc-end>
                                </div>
                            </div>
                            <p class="lc-summary" data-lc-summary aria-live="polite">No dates picked yet.</p>

                            <fieldset class="lc-types">
                                <legend class="pcg-label">Type of leave</legend>
                                @foreach ($types as $key => $t)
                                    <label class="lc-type">
                                        <input type="radio" name="leave_type" value="{{ $key }}" required data-max="{{ $t['max_days'] }}"
                                               @checked(old('leave_type', 'rnr') === $key)>
                                        <span class="lc-type__text">
                                            <strong>{{ $t['label'] }}</strong>
                                            <span class="lc-muted">{{ $t['hint'] }} Up to {{ $t['max_days'] }} days.@if ($t['sensitive']) Others in the unit see only "On leave".@endif</span>
                                            @if (!empty($t['pool']))
                                                @php $c = $credits[$t['pool']]; @endphp
                                                <span class="lcr-avail{{ !$c['set'] || $c['available'] <= 0 ? ' is-empty' : '' }}">{{ $c['set'] ? \App\Support\LeaveCredits::fmt($c['available']) . ' days available' : 'Credits not set yet' }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </fieldset>

                            <div class="pcg-field">
                                <label class="pcg-label" for="leave-reason">Reason or details <span class="lc-muted">(optional)</span></label>
                                <textarea class="pcg-input" id="leave-reason" name="reason" rows="3" maxlength="500"
                                          placeholder="e.g. after the West Philippine Sea patrol, 1–20 Nov">{{ old('reason') }}</textarea>
                            </div>

                            <button type="submit" class="pcg-btn pcg-btn--cta lc-form__submit">
                                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> File leave
                            </button>
                        </form>
                    </div>
                </section>

                <section class="pcg-card" aria-labelledby="leave-mine-heading">
                    <div class="pcg-card__header"><h3 id="leave-mine-heading">My leave</h3></div>
                    @if ($mine->isEmpty())
                        <p class="pcg-empty">You have not filed any leave yet.</p>
                    @else
                        <ul class="lc-mine">
                            @foreach ($mine as $r)
                                @php
                                    $st = strtolower((string) $r->status);
                                    $canCancel = $st === 'pending' || ($st === 'approved' && (string) $r->start_date > $today);
                                @endphp
                                <li class="lc-mine__item">
                                    <div class="lc-mine__top">
                                        <strong>{{ \App\Support\LeaveTypes::label($r->leave_type) }}</strong>
                                        <span class="pcg-badge pcg-badge--{{ $statusBadge[$st] ?? 'info' }}">{{ $statusLabel[$st] ?? ucfirst($st) }}</span>
                                    </div>
                                    <div class="lc-muted">{{ \App\Support\LeaveCalendar::dateLabel((string) $r->start_date, (string) $r->end_date) }} · {{ $r->days }} {{ (int) $r->days === 1 ? 'day' : 'days' }}</div>
                                    @if ($r->review_note)
                                        <div class="lc-mine__note"><i class="fa-solid fa-comment" aria-hidden="true"></i> {{ $r->review_note }}</div>
                                    @endif
                                    @if (in_array($st, ['approved', 'pending'], true))
                                        <a class="lc-print" href="{{ route('leave.print', $r->id) }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Print leave form</a>
                                    @endif
                                    @if ($canCancel)
                                        <form method="POST" action="{{ route('leave.cancel', $r->id) }}">
                                            @csrf
                                            <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                                    data-pcg-confirm="Cancel this {{ strtolower(\App\Support\LeaveTypes::label($r->leave_type)) }} for {{ \App\Support\LeaveCalendar::dateLabel((string) $r->start_date, (string) $r->end_date) }}?"
                                                    data-pcg-confirm-title="Cancel leave" data-pcg-confirm-ok="Cancel leave" data-pcg-confirm-danger>
                                                Cancel
                                            </button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="pcg-card pcg-mt" aria-labelledby="leave-credit-history-heading">
                    <div class="pcg-card__header"><h3 id="leave-credit-history-heading">Credit history</h3></div>
                    <div class="pcg-card__body">@include('partials.leave-credit-history', ['pools' => \App\Support\LeaveCredits::POOLS])</div>
                </section>
            </aside>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ \App\Support\Asset::v('js/leave-calendar.js') }}" nonce="{{ $cspNonce ?? '' }}"></script>
@endsection
