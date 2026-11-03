@extends('layouts.admin')

@section('title', 'Re-enlistment')

@php
    $windowTitle = match ($filters['window']) {
        'overdue' => 'ETE already passed',
        'no_ete' => 'No ETE recorded',
        'all' => 'All non-officers',
        default => 'ETE within the next ' . $filters['months'] . ' months',
    };
    $tab = fn (array $over) => route('admin.reenlistment.index', array_merge(request()->except('page'), $over));
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : null;
@endphp

@section('content')
    <div class="pcg-page pcg-re">
        <header class="pcg-re__head">
            <div>
                <p class="pcg-re__eyebrow">Re-enlistment</p>
                <h2>ETE watchlist</h2>
                <p class="pcg-re__lead">Personnel registered as <strong>non-officers</strong>, sorted by end of term of enlistment (ETE).</p>
                <ul class="pcg-re__chips" aria-label="Dates">
                    <li><i class="fa-regular fa-calendar" aria-hidden="true"></i> As of {{ $today->format('d M Y') }}</li>
                    <li><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> 12-month list ends {{ $today->copy()->addMonthsNoOverflow(12)->format('d M Y') }}</li>
                </ul>
            </div>
            <div class="pcg-actions pcg-no-print">
                @if ($canExport)
                    <a class="pcg-btn pcg-btn--primary" href="{{ route('admin.reenlistment.export', request()->except('page')) }}"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV</a>
                @endif
                <button type="button" class="pcg-btn pcg-btn--secondary" data-on-click="pcg.print"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
            </div>
        </header>

        <div class="pcg-stats pcg-re__stats">
            <a class="pcg-stat pcg-stat--danger pcg-stat--link" href="{{ $tab(['window' => 'due', 'months' => 3]) }}">
                <span class="pcg-stat__label">Within 3 months</span>
                <span class="pcg-stat__value">{{ number_format($counts['due3']) }}</span>
                <span class="pcg-stat__hint">act now</span>
            </a>
            <a class="pcg-stat pcg-stat--warning pcg-stat--link" href="{{ $tab(['window' => 'due', 'months' => 6]) }}">
                <span class="pcg-stat__label">Within 6 months</span>
                <span class="pcg-stat__value">{{ number_format($counts['due6']) }}</span>
                <span class="pcg-stat__hint">prepare the papers</span>
            </a>
            <a class="pcg-stat pcg-stat--link" href="{{ $tab(['window' => 'due', 'months' => 12]) }}">
                <span class="pcg-stat__label">Within 12 months</span>
                <span class="pcg-stat__value">{{ number_format($counts['due12']) }}</span>
                <span class="pcg-stat__hint">the re-enlistment list</span>
            </a>
            <a class="pcg-stat pcg-stat--danger pcg-stat--link" href="{{ $tab(['window' => 'overdue']) }}">
                <span class="pcg-stat__label">ETE passed</span>
                <span class="pcg-stat__value">{{ number_format($counts['overdue']) }}</span>
                <span class="pcg-stat__hint">of {{ number_format($counts['all']) }} non-officers</span>
            </a>
        </div>

        <section class="pcg-card" aria-labelledby="list-heading">
            <div class="pcg-re__toolbar pcg-no-print">
                <nav class="pcg-tabs" aria-label="Lists">
                    <a href="{{ $tab(['window' => 'due', 'months' => 12]) }}" @if ($filters['window'] === 'due') aria-current="page" @endif>Due within 12 months<span>{{ $counts['due12'] }}</span></a>
                    <a href="{{ $tab(['window' => 'overdue']) }}" @if ($filters['window'] === 'overdue') aria-current="page" @endif>ETE passed<span>{{ $counts['overdue'] }}</span></a>
                    <a href="{{ $tab(['window' => 'no_ete']) }}" @if ($filters['window'] === 'no_ete') aria-current="page" @endif>No ETE<span>{{ $counts['no_ete'] }}</span></a>
                    <a href="{{ $tab(['window' => 'all']) }}" @if ($filters['window'] === 'all') aria-current="page" @endif>All<span>{{ $counts['all'] }}</span></a>
                </nav>

                <form method="GET" action="{{ route('admin.reenlistment.index') }}" class="pcg-re__filters">
                    <input type="hidden" name="window" value="{{ $filters['window'] }}">
                    @if ($filters['window'] === 'due')
                        <div class="pcg-field">
                            <label class="pcg-label" for="months">ETE within</label>
                            <select class="pcg-input" id="months" name="months">
                                @foreach ([3, 6, 12] as $m)
                                    <option value="{{ $m }}" @selected($filters['months'] === $m)>{{ $m }} months</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="pcg-field">
                        <label class="pcg-label" for="unit">Command / unit</label>
                        <select class="pcg-input" id="unit" name="unit">
                            <option value="">All units</option>
                            @foreach ($units as $u)
                                <option value="{{ $u }}" @selected($filters['unit'] === $u)>{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field pcg-re__status">
                        <label class="pcg-label" for="status">Account</label>
                        <select class="pcg-input" id="status" name="status">
                            @foreach (\App\Http\Controllers\ReenlistmentController::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field pcg-re__search">
                        <label class="pcg-label" for="q">Search</label>
                        <input class="pcg-input" type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Name, serial no. or email" maxlength="100">
                    </div>
                    <div class="pcg-re__actions">
                        <button type="submit" class="pcg-btn pcg-btn--primary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Apply</button>
                        @if ($filters['unit'] || $filters['q'] || $filters['status'] !== 'active' || $filters['months'] !== 12)
                            <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.reenlistment.index', ['window' => $filters['window']]) }}">Clear</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="pcg-card__header pcg-re__listhead">
                <h3 id="list-heading">{{ $windowTitle }}</h3>
                <span class="pcg-badge">{{ number_format($rows->total()) }} {{ \Illuminate\Support\Str::plural('person', $rows->total()) }}</span>
            </div>

            @if ($rows->total() === 0)
                <p class="pcg-empty">Nobody matches this list and these filters.</p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table pcg-re__table">
                        <thead>
                            <tr>
                                <th scope="col">Personnel</th>
                                <th scope="col">Command / unit</th>
                                <th scope="col">ETE</th>
                                <th scope="col">Time to ETE</th>
                                <th scope="col">Account</th>
                                <th scope="col">Form</th>
                                <th scope="col" class="pcg-no-print"><span class="pcg-visually-hidden">Details</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr class="pcg-re__row {{ $r->ete_tone ? 'is-' . $r->ete_tone : '' }}">
                                    <td data-label="Personnel">
                                        <a class="pcg-re__name" href="{{ route('admin.reenlistment.show', $r->id) }}"><b>{{ $r->rank }}</b> {{ $r->name }}</a>
                                        <span class="pcg-table__muted">{{ $r->serial_number ?: 'no serial' }} · {{ $r->email }}</span>
                                    </td>
                                    <td data-label="Unit">{{ $r->mother_unit ?: '—' }}@if ($r->unit_assignment && $r->unit_assignment !== $r->mother_unit)<span class="pcg-table__muted">{{ $r->unit_assignment }}</span>@endif</td>
                                    <td data-label="ETE" class="pcg-nowrap"><strong>{{ $date($r->ete) ?? '—' }}</strong>
                                        @if ($r->date_of_appointment)<span class="pcg-table__muted">appointed {{ $date($r->date_of_appointment) }}</span>@endif</td>
                                    <td data-label="Time to ETE" class="pcg-nowrap"><span class="pcg-badge {{ $r->ete_tone ? 'pcg-badge--' . $r->ete_tone : '' }}">{{ $r->ete_label }}</span></td>
                                    <td data-label="Account">{{ ucfirst(strtolower((string) $r->status)) ?: '—' }}</td>
                                    <td data-label="Form">@if ($r->form_submitted)<span class="pcg-badge pcg-badge--success"><i class="fa-solid fa-check" aria-hidden="true"></i> Submitted</span>@else<span class="pcg-badge">Not yet</span>@endif</td>
                                    <td class="pcg-no-print pcg-re__go"><a href="{{ route('admin.reenlistment.show', $r->id) }}" aria-label="Details for {{ $r->name }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pcg-card__body pcg-no-print">{{ $rows->links('admin.partials.pagination') }}</div>
            @endif
        </section>
    </div>
@endsection
