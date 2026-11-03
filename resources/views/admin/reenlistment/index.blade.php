@extends('layouts.admin')

@section('title', 'Re-enlistment')

@php
    $windowTitle = match ($filters['window']) {
        'overdue' => 'ETE already passed',
        'no_ete' => 'Non-officers without an ETE',
        'all' => 'All non-officers',
        default => 'ETE within the next ' . $filters['months'] . ' months',
    };
    $tab = fn (array $over) => route('admin.reenlistment.index', array_merge(request()->except('page'), $over));
@endphp

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Re-enlistment · ETE watchlist</h2>
                <p>Personnel who registered as <strong>non-officers</strong>, by end of term of enlistment (ETE).
                    Today is {{ $today->format('d M Y') }}; “within the next 12 months” means an ETE up to {{ $today->copy()->addMonthsNoOverflow(12)->format('d M Y') }}.</p>
            </div>
            <div class="pcg-actions pcg-no-print">
                @if ($canExport)
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.reenlistment.export', request()->except('page')) }}"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV</a>
                @endif
                <button type="button" class="pcg-btn pcg-btn--secondary" data-on-click="pcg.print"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
            </div>
        </div>

        <div class="pcg-stats">
            <a class="pcg-stat pcg-stat--danger pcg-stat--link" href="{{ $tab(['window' => 'due', 'months' => 3]) }}">
                <span class="pcg-stat__label">ETE within 3 months</span>
                <span class="pcg-stat__value">{{ number_format($counts['due3']) }}</span>
                <span class="pcg-stat__hint">act now</span>
            </a>
            <a class="pcg-stat pcg-stat--warning pcg-stat--link" href="{{ $tab(['window' => 'due', 'months' => 6]) }}">
                <span class="pcg-stat__label">ETE within 6 months</span>
                <span class="pcg-stat__value">{{ number_format($counts['due6']) }}</span>
                <span class="pcg-stat__hint">includes the 3-month group</span>
            </a>
            <a class="pcg-stat pcg-stat--link" href="{{ $tab(['window' => 'due', 'months' => 12]) }}">
                <span class="pcg-stat__label">ETE within 12 months</span>
                <span class="pcg-stat__value">{{ number_format($counts['due12']) }}</span>
                <span class="pcg-stat__hint">the re-enlistment list</span>
            </a>
            <a class="pcg-stat pcg-stat--danger pcg-stat--link" href="{{ $tab(['window' => 'overdue']) }}">
                <span class="pcg-stat__label">ETE already passed</span>
                <span class="pcg-stat__value">{{ number_format($counts['overdue']) }}</span>
                <span class="pcg-stat__hint">{{ number_format($counts['no_ete']) }} without an ETE · {{ number_format($counts['all']) }} non-officers in total</span>
            </a>
        </div>

        <nav class="pcg-tabs pcg-no-print" aria-label="Lists">
            <a href="{{ $tab(['window' => 'due', 'months' => 12]) }}" @if ($filters['window'] === 'due') aria-current="page" @endif>Due within 12 months<span>{{ $counts['due12'] }}</span></a>
            <a href="{{ $tab(['window' => 'overdue']) }}" @if ($filters['window'] === 'overdue') aria-current="page" @endif>ETE passed<span>{{ $counts['overdue'] }}</span></a>
            <a href="{{ $tab(['window' => 'no_ete']) }}" @if ($filters['window'] === 'no_ete') aria-current="page" @endif>No ETE<span>{{ $counts['no_ete'] }}</span></a>
            <a href="{{ $tab(['window' => 'all']) }}" @if ($filters['window'] === 'all') aria-current="page" @endif>All non-officers<span>{{ $counts['all'] }}</span></a>
        </nav>

        <section class="pcg-card pcg-mt pcg-no-print">
            <div class="pcg-card__body">
                <form method="GET" action="{{ route('admin.reenlistment.index') }}" class="pcg-filters">
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
                            <option value="">All</option>
                            @foreach ($units as $u)
                                <option value="{{ $u }}" @selected($filters['unit'] === $u)>{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="status">Account status</label>
                        <select class="pcg-input" id="status" name="status">
                            @foreach (\App\Http\Controllers\ReenlistmentController::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="q">Search</label>
                        <input class="pcg-input" type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Name, serial number or email" maxlength="100">
                    </div>
                    <div class="pcg-filters__actions">
                        <button type="submit" class="pcg-btn pcg-btn--primary">Apply</button>
                        <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.reenlistment.index', ['window' => $filters['window']]) }}">Clear</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="pcg-card pcg-mt" aria-labelledby="list-heading">
            <div class="pcg-card__header">
                <h3 id="list-heading">{{ $windowTitle }} · {{ number_format($rows->total()) }} {{ \Illuminate\Support\Str::plural('person', $rows->total()) }}</h3>
            </div>
            @if ($rows->total() === 0)
                <p class="pcg-empty">Nobody matches this list and these filters.</p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">Serial no.</th>
                                <th scope="col">Rank and name</th>
                                <th scope="col">Command / unit</th>
                                <th scope="col">Appointed</th>
                                <th scope="col">ETE</th>
                                <th scope="col">Time to ETE</th>
                                <th scope="col">Account</th>
                                <th scope="col">Re-enlistment form</th>
                                <th scope="col" class="pcg-no-print"><span class="pcg-visually-hidden">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td class="pcg-nowrap">{{ $r->serial_number ?: '—' }}</td>
                                    <td><strong>{{ $r->rank }}</strong> {{ $r->name }}<span class="pcg-table__muted">{{ $r->email }}</span></td>
                                    <td>{{ $r->mother_unit ?: '—' }}@if ($r->unit_assignment)<span class="pcg-table__muted">{{ $r->unit_assignment }}</span>@endif</td>
                                    <td class="pcg-nowrap">{{ $r->date_of_appointment ? \Carbon\Carbon::parse($r->date_of_appointment)->format('d M Y') : '—' }}</td>
                                    <td class="pcg-nowrap"><strong>{{ $r->ete ? \Carbon\Carbon::parse($r->ete)->format('d M Y') : '—' }}</strong></td>
                                    <td class="pcg-nowrap"><span class="pcg-badge {{ $r->ete_tone ? 'pcg-badge--' . $r->ete_tone : '' }}">{{ $r->ete_label }}</span></td>
                                    <td>{{ ucfirst(strtolower((string) $r->status)) ?: '—' }}</td>
                                    <td>@if ($r->form_submitted)<span class="pcg-badge pcg-badge--success">Submitted</span>@else<span class="pcg-badge">Not yet</span>@endif</td>
                                    <td class="pcg-no-print"><a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('admin.reenlistment.show', $r->id) }}">Open</a></td>
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
