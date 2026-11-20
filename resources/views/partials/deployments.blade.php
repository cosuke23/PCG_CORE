{{-- Deployment and sea-duty records (App\Support\Deployments, DeploymentController), unit checker and admin console.
     Params: $list, $members, $unit, $unitList, $showUnitFilter, $canEdit, $routes (index, store, end route name), $ready, $rnrPer30 --}}
@php $today = \App\Support\LeaveCalendar::today()->toDateString(); $kinds = \App\Support\Deployments::KINDS; @endphp

@unless ($ready)
    <p class="pcg-alert pcg-alert--warning pcg-mb">Deployment records need a database update that has not been applied yet.</p>
@endunless

@if ($showUnitFilter)
    <form method="GET" action="{{ $routes['index'] }}" class="lc-filter pcg-mb" data-pcg-no-busy>
        <label class="pcg-label" for="dp-unit">Unit</label>
        <select class="pcg-input pcg-input--sm" id="dp-unit" name="unit">
            <option value="">All units</option>
            @foreach ($unitList as $u)
                <option value="{{ $u }}" @selected(strcasecmp($unit, $u) === 0)>{{ $u }}</option>
            @endforeach
        </select>
        <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Show</button>
    </form>
@endif

<div class="lc-layout">
    <div class="lc-layout__main">
        <section class="pcg-card" aria-labelledby="dp-list">
            <div class="pcg-card__header"><h3 id="dp-list">Deployments</h3></div>
            @if ($list->isEmpty())
                <p class="pcg-empty">No deployment has been recorded yet.</p>
            @else
                <table class="pcg-table">
                    <thead><tr><th scope="col">Member</th><th scope="col">Where</th><th scope="col">Dates</th><th scope="col" class="lcr-num">Days</th><th scope="col">RNR</th>@if ($canEdit)<th scope="col"><span class="pcg-visually-hidden">End</span></th>@endif</tr></thead>
                    <tbody>
                        @foreach ($list as $d)
                            <tr>
                                <td><strong>{{ trim(($d->rank ?? '') . ' ' . $d->name) }}</strong><br><span class="lc-muted">{{ $d->mother_unit }}</span></td>
                                <td>
                                    {{ $d->assignment }}<br>
                                    <span class="pcg-badge {{ $d->active ? 'pcg-badge--info' : ($d->upcoming ? 'pcg-badge--warning' : '') }}">{{ $d->active ? 'Deployed now' : ($d->upcoming ? 'Starts ' . \Illuminate\Support\Carbon::parse($d->start_date)->format('M j') : 'Ended') }}</span>
                                    <span class="lc-muted">{{ \App\Support\Deployments::kindLabel($d->kind) }}</span>
                                </td>
                                <td class="lcr-nowrap">{{ \Illuminate\Support\Carbon::parse($d->start_date)->format('M j, Y') }} –<br>{{ $d->end_date ? \Illuminate\Support\Carbon::parse($d->end_date)->format('M j, Y') : 'ongoing' }}</td>
                                <td class="lcr-num">{{ $d->length }}</td>
                                <td>{{ $d->rnr_days !== null ? \App\Support\LeaveCredits::fmt((float) $d->rnr_days) . ' days' : '—' }}</td>
                                @if ($canEdit)
                                    <td>
                                        @if ($d->end_date === null || ($d->rnr_entry_id === null && $d->end_date <= $today))
                                            <details class="dp-end">
                                                <summary class="pcg-btn pcg-btn--secondary pcg-btn--sm">{{ $d->end_date === null ? 'End' : 'Grant RNR' }}</summary>
                                                <form method="POST" action="{{ route($routes['end'], $d->id) }}" class="lc-form">
                                                    @csrf
                                                    <div class="pcg-field">
                                                        <label class="pcg-label" for="de-{{ $d->id }}">Ended on</label>
                                                        <input class="pcg-input pcg-input--sm" type="date" id="de-{{ $d->id }}" name="end_date" required min="{{ $d->start_date }}" value="{{ $d->end_date ?? $today }}">
                                                    </div>
                                                    @php $suggest = \App\Support\Deployments::suggestedRnr(\App\Support\Deployments::length($d->start_date, $d->end_date ?? $today)); @endphp
                                                    <div class="pcg-field">
                                                        <label class="pcg-label" for="dr-{{ $d->id }}">RNR days to add</label>
                                                        <input class="pcg-input pcg-input--sm" type="number" id="dr-{{ $d->id }}" name="rnr_days" min="0" max="60" step="0.5" value="{{ $suggest > 0 ? $suggest : '' }}">
                                                        <p class="pcg-help">Leave empty for none. They go to the member's RNR credits.</p>
                                                    </div>
                                                    <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--sm">Save</button>
                                                </form>
                                            </details>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    @if ($canEdit && $ready)
        <aside class="lc-layout__side">
            <section class="pcg-card" aria-labelledby="dp-new">
                <div class="pcg-card__header"><h3 id="dp-new">Record a deployment</h3></div>
                <div class="pcg-card__body">
                    @if ($members->isEmpty())
                        <p class="pcg-empty">No approved members in this scope.</p>
                    @else
                        <form method="POST" action="{{ $routes['store'] }}" class="lc-form">
                            @csrf
                            <div class="pcg-field">
                                <label class="pcg-label" for="dp-member">Member</label>
                                <select class="pcg-input" id="dp-member" name="user_id" required>
                                    <option value="">Choose…</option>
                                    @foreach ($members as $m)
                                        <option value="{{ $m->id }}" @selected((int) old('user_id') === $m->id)>{{ trim(($m->rank ?? '') . ' ' . $m->name) }}{{ $showUnitFilter && $unit === '' ? ' · ' . $m->mother_unit : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="dp-kind">Kind</label>
                                <select class="pcg-input" id="dp-kind" name="kind" required>
                                    @foreach ($kinds as $k => $label)
                                        <option value="{{ $k }}" @selected(old('kind', 'sea_duty') === $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="dp-where">Vessel, station or mission</label>
                                <input class="pcg-input" id="dp-where" name="assignment" maxlength="150" required placeholder="e.g. BRP Gabriela Silang (OPV-8301), WPS patrol" value="{{ old('assignment') }}">
                            </div>
                            <div class="lc-form__dates">
                                <div class="pcg-field">
                                    <label class="pcg-label" for="dp-from">From</label>
                                    <input class="pcg-input" type="date" id="dp-from" name="start_date" required value="{{ old('start_date', $today) }}">
                                </div>
                                <div class="pcg-field">
                                    <label class="pcg-label" for="dp-to">To <span class="lc-muted">(empty if ongoing)</span></label>
                                    <input class="pcg-input" type="date" id="dp-to" name="end_date" value="{{ old('end_date') }}">
                                </div>
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="dp-rnr">RNR days to add <span class="lc-muted">(only with an end date)</span></label>
                                <input class="pcg-input" type="number" id="dp-rnr" name="rnr_days" min="0" max="60" step="0.5" value="{{ old('rnr_days') }}">
                                @if ($rnrPer30 > 0)
                                    <p class="pcg-help">Rule: {{ \App\Support\LeaveCredits::fmt($rnrPer30) }} days for every 30 days deployed.</p>
                                @endif
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="dp-note">Note</label>
                                <input class="pcg-input" id="dp-note" name="note" maxlength="500" value="{{ old('note') }}" placeholder="Optional, e.g. order number">
                            </div>
                            <button type="submit" class="pcg-btn pcg-btn--cta">Record deployment</button>
                        </form>
                    @endif
                </div>
            </section>
        </aside>
    @endif
</div>
