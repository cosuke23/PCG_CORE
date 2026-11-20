@extends('layouts.unit')

@section('title', 'Recall')

@section('content')
    @php $open = $list->filter(fn ($r) => $r && !$r->closed_at); @endphp
    <div class="pcg-page">
        <div class="uc-head">
            <h2>{{ $open->isEmpty() ? 'No recall is open' : ($open->count() === 1 ? '1 recall is open' : $open->count() . ' recalls are open') }}</h2>
            <p>Send one message to every approved member of {{ $isM1 ? 'a unit, or every unit' : $own }}: by email, as a portal notification and as an alert on the Android app (within 15 minutes). Members answer "on my way" or "cannot come".</p>
        </div>

        @unless ($ready)
            <p class="pcg-alert pcg-alert--warning">Recalls need a database update that has not been applied yet.</p>
        @endunless

        <div class="lc-layout">
            <div class="lc-layout__main">
                <section class="pcg-card" aria-labelledby="rc-list">
                    <div class="pcg-card__header"><h3 id="rc-list">Recent recalls</h3></div>
                    @if ($list->isEmpty())
                        <p class="pcg-empty">No recall has been sent.</p>
                    @else
                        <table class="pcg-table">
                            <thead><tr><th scope="col">Recall</th><th scope="col">Sent</th><th scope="col" class="lcr-num">On the way</th><th scope="col" class="lcr-num">Cannot</th><th scope="col" class="lcr-num">No answer</th><th scope="col"><span class="pcg-visually-hidden">Open</span></th></tr></thead>
                            <tbody>
                                @foreach ($list as $r)
                                    <tr>
                                        <td><strong>{{ $r->title }}</strong><br><span class="lc-muted">{{ $r->unit_name ?? 'Every unit' }}{{ $r->closed_at ? ' · closed' : '' }}</span></td>
                                        <td class="lcr-nowrap">{{ \Illuminate\Support\Carbon::parse($r->created_at)->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
                                        <td class="lcr-num">{{ $r->coming }}</td>
                                        <td class="lcr-num">{{ $r->cannot }}</td>
                                        <td class="lcr-num"><strong>{{ $r->waiting }}</strong></td>
                                        <td><a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('unit.recall.show', $r->id) }}">Open</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            </div>

            @if ($ready)
                <aside class="lc-layout__side">
                    <section class="pcg-card" aria-labelledby="rc-new">
                        <div class="pcg-card__header"><h3 id="rc-new">Send a recall</h3></div>
                        <div class="pcg-card__body">
                            <form method="POST" action="{{ route('unit.recall.send') }}" class="lc-form">
                                @csrf
                                @if ($isM1)
                                    <div class="pcg-field">
                                        <label class="pcg-label" for="rc-unit">Who</label>
                                        <select class="pcg-input" id="rc-unit" name="unit" required>
                                            <option value="">Choose…</option>
                                            <option value="__all__">Every unit</option>
                                            @foreach ($units as $u)
                                                <option value="{{ $u }}" @selected(old('unit') === $u)>{{ $u }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <p class="lc-muted">To {{ $memberCount ?? 0 }} approved {{ ($memberCount ?? 0) === 1 ? 'member' : 'members' }} of {{ $own }}.</p>
                                @endif
                                <div class="pcg-field">
                                    <label class="pcg-label" for="rc-title">Subject</label>
                                    <input class="pcg-input" id="rc-title" name="title" maxlength="150" required value="{{ old('title') }}" placeholder="e.g. Oil spill response, Batangas">
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="rc-msg">Message</label>
                                    <textarea class="pcg-input" id="rc-msg" name="message" rows="4" maxlength="2000" required>{{ old('message') }}</textarea>
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="rc-to">Report to</label>
                                    <input class="pcg-input" id="rc-to" name="report_to" maxlength="200" value="{{ old('report_to') }}" placeholder="e.g. MEPCOM HQ, by 1800H today">
                                </div>
                                <label class="pcg-check"><input type="checkbox" name="confirm" value="1" required> <span>I confirm this recall is authorized.</span></label>
                                <button type="submit" class="pcg-btn pcg-btn--cta" data-pcg-confirm="Send this recall now? Every member will be emailed and alerted." data-pcg-confirm-title="Send recall" data-pcg-confirm-ok="Send recall" data-pcg-confirm-danger="1">
                                    <i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Send recall
                                </button>
                            </form>
                        </div>
                    </section>
                </aside>
            @endif
        </div>
    </div>
@endsection
