@extends('layouts.admin')

@section('title', 'Leave')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    @php
        $pendingCount = (int) ($counts['pending'] ?? 0);
        $tab = fn ($s) => route('admin.leave.index', array_filter(['status' => $s === 'pending' ? null : $s, 'unit' => $unit, 'q' => $q, 'month' => $month->format('Y-m')]));
        $badge = ['pending' => 'warning', 'approved' => 'success', 'declined' => 'danger', 'cancelled' => 'info'];
        $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d);
        $where = $unit !== '' ? $unit : $scopeName;
    @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>
                    @if ($pendingCount)
                        {{ $pendingCount === 1 ? '1 leave request is' : number_format($pendingCount) . ' leave requests are' }} waiting for approval in {{ $where }}
                    @else
                        No leave is waiting for approval in {{ $where }}
                    @endif
                </h2>
                <p>{{ $awayToday === 1 ? '1 member is' : number_format($awayToday) . ' members are' }} on approved leave today. Unit checkers decide their unit's leave; you can decide any request in your scope{{ $canApprove ? '' : ' (view only for your role)' }}.</p>
            </div>
            <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.leave.credits', array_filter(['unit' => $unit])) }}"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Leave credits</a>
        </div>

        <section class="pcg-card pcg-mb">
            <div class="pcg-card__body">
                <form method="GET" action="{{ route('admin.leave.index') }}" class="pcg-filters" role="search" data-pcg-no-busy>
                    @if ($status !== 'pending')<input type="hidden" name="status" value="{{ $status }}">@endif
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <div class="pcg-field">
                        <label class="pcg-label" for="unit">Unit</label>
                        <select class="pcg-input" id="unit" name="unit">
                            <option value="">{{ $scopeName === 'all units' ? 'All units' : 'All units in ' . $scopeName }}</option>
                            @foreach ($units as $u)
                                <option value="{{ $u }}" @selected(strcasecmp($unit, $u) === 0)>{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="q">Name or serial number</label>
                        <input class="pcg-input" type="search" id="q" name="q" value="{{ $q }}" maxlength="100" autocomplete="off">
                    </div>
                    <div class="pcg-filters__actions">
                        <button type="submit" class="pcg-btn pcg-btn--secondary">Show</button>
                        @if ($unit !== '' || $q !== '')
                            <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.leave.index') }}">Clear</a>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        <div class="lc-layout">
            <div class="lc-layout__main">
                <section class="pcg-card pcg-mb" aria-label="Leave calendar">
                    <div class="pcg-card__body">
                        @include('partials.leave-calendar', [
                            'selectable' => false,
                            'prevUrl' => route('admin.leave.index', $query + ['month' => $prev]),
                            'nextUrl' => route('admin.leave.index', $query + ['month' => $next]),
                            'caption' => 'Approved leave in ' . $where . '. Open a day to see everyone who is away.',
                        ])
                    </div>
                </section>
                @include('partials.leave-legend')
            </div>

            <aside class="lc-layout__side">
                <section class="pcg-card" aria-labelledby="leave-list-heading">
                    <div class="pcg-card__header"><h3 id="leave-list-heading">Requests</h3></div>
                    <nav class="pcg-tabs lcr-tabs" aria-label="Status">
                        @foreach ($statuses as $key => $label)
                            <a href="{{ $tab($key) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }}@if ($key !== 'all') <span class="pcg-num">{{ (int) ($counts[$key] ?? 0) }}</span>@endif</a>
                        @endforeach
                    </nav>
                    @if ($list->isEmpty())
                        <p class="pcg-empty">No {{ $status === 'all' ? '' : strtolower($statuses[$status]) . ' ' }}requests{{ $q !== '' ? ' match the search' : '' }}.</p>
                    @else
                        <ul class="lc-mine">
                            @foreach ($list as $r)
                                @php $st = strtolower((string) $r->status); @endphp
                                <li class="lc-mine__item">
                                    <div class="lc-mine__top">
                                        <span class="lc-chip lc-c{{ \App\Support\LeaveCalendar::colour($r->serial_number) }}"><span class="lc-chip__ini">{{ \App\Support\LeaveCalendar::initials($r->name) }}</span></span>
                                        <strong>{{ trim(($r->rank ?? '') . ' ' . $r->name) }}</strong>
                                        <span class="pcg-badge pcg-badge--{{ $badge[$st] ?? 'info' }}">{{ $statuses[$st] ?? ucfirst($st) }}</span>
                                    </div>
                                    <div class="lc-muted"><span class="pcg-mono">{{ $r->serial_number }}</span>@if ($r->mother_unit) · {{ $r->mother_unit }}@endif</div>
                                    <div><strong>{{ \App\Support\LeaveTypes::label($r->leave_type) }}</strong></div>
                                    <div class="lc-muted">{{ \App\Support\LeaveCalendar::dateLabel((string) $r->start_date, (string) $r->end_date) }} · {{ $r->days }} {{ (int) $r->days === 1 ? 'day' : 'days' }}</div>
                                    @if ($r->reason)
                                        <div class="lc-mine__note"><i class="fa-solid fa-quote-left" aria-hidden="true"></i> {{ $r->reason }}</div>
                                    @endif
                                    @if ($st !== 'pending' && $r->reviewer_name)
                                        <div class="lc-mine__note">{{ ucfirst($st) }} by {{ $r->reviewer_name }}@if ($r->review_note): {{ $r->review_note }}@endif</div>
                                    @endif
                                    @if ($st === 'pending' && !empty($r->credit))
                                        <div class="lcr-avail{{ $r->shortfall ? ' is-empty' : '' }}">
                                            {{ $r->credit['label'] }}: {{ $r->credit['set'] ? $fmt($r->credit['balance']) . ' days' : 'not set' }}
                                            @if ($r->shortfall) · not enough to approve @else · {{ $fmt($r->credit['balance'] - $r->days) }} left after this @endif
                                            @if ($canCredits) · <a href="{{ route('admin.leave.credits.show', $r->user_id) }}">credits</a>@endif
                                        </div>
                                    @endif
                                    @if ($st === 'pending' && $canApprove)
                                        <form method="POST" action="{{ route('admin.leave.review', $r->id) }}" class="lc-review">
                                            @csrf
                                            <label class="pcg-label" for="note-{{ $r->id }}">Note to the member <span class="lc-muted">(needed to decline)</span></label>
                                            <input class="pcg-input pcg-input--sm" id="note-{{ $r->id }}" name="note" maxlength="500">
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
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="pcg-card__body">{{ $list->links() }}</div>
                    @endif
                </section>
            </aside>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ \App\Support\Asset::v('js/leave-calendar.js') }}" nonce="{{ $cspNonce ?? '' }}"></script>
@endsection
