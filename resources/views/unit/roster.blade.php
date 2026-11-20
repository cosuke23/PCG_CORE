@extends('layouts.unit')

@section('title', 'Duty roster')

@section('content')
    @php
        $total = collect($week)->flatten(2)->count();
        $prev = $monday->copy()->subWeek()->toDateString();
        $next = $monday->copy()->addWeek()->toDateString();
        $q = fn ($w) => array_filter(['week' => $w, 'unit' => $isM1 ? $unit : null]);
    @endphp
    <div class="pcg-page">
        <div class="uc-head">
            <h2>{{ $total === 1 ? '1 duty is' : $total . ' duties are' }} rostered in {{ $unit ?: 'this unit' }} for the week of {{ $monday->format('F j') }}</h2>
            <p>Members who are deployed, on approved leave or in a MEPTDI class that day cannot be rostered. Each member is notified of a new or removed duty.</p>
        </div>
        @unless ($ready)
            <p class="pcg-alert pcg-alert--warning">The duty roster needs a database update that has not been applied yet.</p>
        @endunless

        <form method="GET" action="{{ route('unit.roster') }}" class="lc-filter pcg-mb" data-pcg-no-busy>
            <a class="pcg-btn pcg-btn--ghost pcg-btn--sm" href="{{ route('unit.roster', $q($prev)) }}" aria-label="Previous week"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
            <label class="pcg-label" for="ro-week">Week of</label>
            <input class="pcg-input pcg-input--sm" type="date" id="ro-week" name="week" value="{{ $monday->toDateString() }}">
            <a class="pcg-btn pcg-btn--ghost pcg-btn--sm" href="{{ route('unit.roster', $q($next)) }}" aria-label="Next week"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
            @if ($isM1)
                <label class="pcg-label" for="ro-unit">Unit</label>
                <select class="pcg-input pcg-input--sm" id="ro-unit" name="unit">
                    @foreach ($units as $u)
                        <option value="{{ $u }}" @selected(strcasecmp($unit, $u) === 0)>{{ $u }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Show</button>
        </form>

        <div class="lc-layout">
            <div class="lc-layout__main">
                <section class="pcg-card" aria-label="Roster for the week">
                    <table class="pcg-table ro-table">
                        <thead><tr><th scope="col">Day</th>@foreach (\App\Support\DutyRoster::SHIFTS as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($week as $date => $shifts)
                                @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
                                <tr class="{{ $d->isSameDay(\App\Support\LeaveCalendar::today()) ? 'is-today' : '' }}">
                                    <th scope="row" class="lcr-nowrap">{{ $d->format('D M j') }}</th>
                                    @foreach ($shifts as $shift => $items)
                                        <td>
                                            @foreach ($items as $a)
                                                <div class="ro-item">
                                                    <span><strong>{{ $a->post }}</strong><br>{{ trim(($a->rank ?? '') . ' ' . $a->name) }}</span>
                                                    <form method="POST" action="{{ route('unit.roster.remove', $a->id) }}">
                                                        @csrf
                                                        <button type="submit" class="pcg-btn pcg-btn--ghost pcg-btn--sm" aria-label="Remove {{ $a->post }} of {{ $a->name }}"
                                                            data-pcg-confirm="Remove this duty? {{ $a->name }} will be notified." data-pcg-confirm-ok="Remove"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            </div>
            @if ($ready)
                <aside class="lc-layout__side">
                    <section class="pcg-card" aria-labelledby="ro-new">
                        <div class="pcg-card__header"><h3 id="ro-new">Add a duty</h3></div>
                        <div class="pcg-card__body">
                            <form method="POST" action="{{ route('unit.roster.store') }}" class="lc-form">
                                @csrf
                                @if ($isM1)<input type="hidden" name="unit" value="{{ $unit }}">@endif
                                <div class="pcg-field">
                                    <label class="pcg-label" for="ro-member">Member</label>
                                    <select class="pcg-input" id="ro-member" name="user_id" required>
                                        <option value="">Choose…</option>
                                        @foreach ($members as $m)
                                            <option value="{{ $m->id }}" @selected((int) old('user_id') === $m->id)>{{ trim(($m->rank ?? '') . ' ' . $m->name) }}</option>
                                        @endforeach
                                    </select>
                                    @error('user_id')<p class="pcg-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="lc-form__dates">
                                    <div class="pcg-field">
                                        <label class="pcg-label" for="ro-date">Day</label>
                                        <input class="pcg-input" type="date" id="ro-date" name="duty_date" required value="{{ old('duty_date', $monday->toDateString()) }}">
                                    </div>
                                    <div class="pcg-field">
                                        <label class="pcg-label" for="ro-shift">Shift</label>
                                        <select class="pcg-input" id="ro-shift" name="shift" required>
                                            @foreach (\App\Support\DutyRoster::SHIFTS as $k => $label)
                                                <option value="{{ $k }}" @selected(old('shift') === $k)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('shift')<p class="pcg-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="ro-post">Duty or post</label>
                                    <input class="pcg-input" id="ro-post" name="post" maxlength="100" required value="{{ old('post') }}" placeholder="e.g. Officer of the Day, Duty driver">
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="ro-note">Note</label>
                                    <input class="pcg-input" id="ro-note" name="note" maxlength="200" value="{{ old('note') }}">
                                </div>
                                <button type="submit" class="pcg-btn pcg-btn--cta">Add to the roster</button>
                            </form>
                        </div>
                    </section>
                </aside>
            @endif
        </div>
    </div>
@endsection
