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
                <section class="pcg-card pcg-mb" aria-labelledby="open-heading">
                    <div class="pcg-card__header"><h3 id="open-heading">{{ $annual['set'] ? 'Reset the opening balance' : 'Set the opening balance' }}</h3></div>
                    <div class="pcg-card__body">
                        <form method="POST" action="{{ route('unit.leave.credits.store', $m->id) }}" class="lc-form">
                            @csrf
                            <input type="hidden" name="action" value="opening">
                            <p class="lc-muted">Ordinary leave credits the member has on the date below, e.g. from the leave card. 2.5 days are added on the 1st of every following month.@if ($annual['set']) A new opening balance replaces the old one and everything before it.@endif</p>
                            <div class="lc-form__dates">
                                <div class="pcg-field">
                                    <label class="pcg-label" for="o-days">Days</label>
                                    <input class="pcg-input" type="number" id="o-days" name="days" min="0" max="365" step="0.5" required value="{{ old('action') === 'opening' ? old('days') : '' }}">
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="o-date">As of</label>
                                    <input class="pcg-input" type="date" id="o-date" name="effective_date" required value="{{ old('action') === 'opening' ? old('effective_date') : $today }}">
                                </div>
                            </div>
                            <input type="hidden" name="note" value="Opening balance">
                            <button type="submit" class="pcg-btn pcg-btn--primary"
                                    @if ($annual['set']) data-pcg-confirm="Replace {{ $m->name }}'s opening balance? Entries before the new date stop counting." data-pcg-confirm-title="Reset opening balance" data-pcg-confirm-ok="Replace" @endif>
                                {{ $annual['set'] ? 'Replace opening balance' : 'Set opening balance' }}
                            </button>
                        </form>
                    </div>
                </section>

                <section class="pcg-card pcg-mb" aria-labelledby="rnr-heading">
                    <div class="pcg-card__header"><h3 id="rnr-heading">Add RNR days</h3></div>
                    <div class="pcg-card__body">
                        <form method="POST" action="{{ route('unit.leave.credits.store', $m->id) }}" class="lc-form">
                            @csrf
                            <input type="hidden" name="action" value="grant">
                            <div class="lc-form__dates">
                                <div class="pcg-field">
                                    <label class="pcg-label" for="g-days">Days earned</label>
                                    <input class="pcg-input" type="number" id="g-days" name="days" min="0.5" max="365" step="0.5" required value="{{ old('action') === 'grant' ? old('days') : '' }}">
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="g-date">Earned on</label>
                                    <input class="pcg-input" type="date" id="g-date" name="effective_date" required value="{{ old('action') === 'grant' ? old('effective_date') : $today }}">
                                </div>
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="g-note">For</label>
                                <input class="pcg-input" id="g-note" name="note" maxlength="500" required placeholder="e.g. WPS patrol on BRP Gabriela Silang, 1–20 Nov" value="{{ old('action') === 'grant' ? old('note') : '' }}">
                            </div>
                            <button type="submit" class="pcg-btn pcg-btn--primary">Add RNR days</button>
                        </form>
                    </div>
                </section>

                <section class="pcg-card" aria-labelledby="adj-heading">
                    <div class="pcg-card__header"><h3 id="adj-heading">Adjust credits</h3></div>
                    <div class="pcg-card__body">
                        <form method="POST" action="{{ route('unit.leave.credits.store', $m->id) }}" class="lc-form">
                            @csrf
                            <input type="hidden" name="action" value="adjustment">
                            <div class="pcg-field">
                                <label class="pcg-label" for="a-pool">Credits</label>
                                <select class="pcg-input" id="a-pool" name="pool" required>
                                    @foreach ($pools as $key => $p)
                                        <option value="{{ $key }}" @selected(old('pool') === $key)>{{ $p['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lc-form__dates">
                                <div class="pcg-field">
                                    <label class="pcg-label" for="a-days">Days (+ or −)</label>
                                    <input class="pcg-input" type="number" id="a-days" name="days" min="-365" max="365" step="0.5" required value="{{ old('action') === 'adjustment' ? old('days') : '' }}">
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="a-date">Date</label>
                                    <input class="pcg-input" type="date" id="a-date" name="effective_date" required value="{{ old('action') === 'adjustment' ? old('effective_date') : $today }}">
                                </div>
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="a-note">Reason</label>
                                <input class="pcg-input" id="a-note" name="note" maxlength="500" required placeholder="e.g. correction from the leave card" value="{{ old('action') === 'adjustment' ? old('note') : '' }}">
                            </div>
                            <button type="submit" class="pcg-btn pcg-btn--secondary">Record adjustment</button>
                        </form>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
