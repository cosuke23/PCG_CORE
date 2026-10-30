@extends('layouts.admin')

@section('title', $type ? $title : 'Reports')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $type ? $title : 'HR reports' }}</h2>
                <p>
                    @if ($type)
                        {{ $total }} {{ \Illuminate\Support\Str::plural('row', $total) }}{{ $dated ? ', ' . $from->format('d M Y') . ' – ' . $to->format('d M Y') : '' }}
                        · {{ $scopeUnit ? 'limited to ' . $scopeUnit->code : 'organisation-wide' }} · generated {{ now()->format('d M Y H:i') }}
                    @else
                        Reports read only the records you are allowed to see. Export as CSV (opens in Excel) or print.
                    @endif
                </p>
            </div>
            @if ($type)
                <div class="pcg-actions pcg-no-print">
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.reports.index', array_merge(request()->except('page'), ['format' => 'csv'])) }}">Export CSV</a>
                    <button type="button" class="pcg-btn pcg-btn--secondary" data-on-click="pcg.print">Print</button>
                </div>
            @endif
        </div>

        <section class="pcg-card pcg-no-print">
            <div class="pcg-card__body">
                <form method="GET" action="{{ route('admin.reports.index') }}" class="pcg-filters">
                    <div class="pcg-field">
                        <label class="pcg-label" for="type">Report</label>
                        <select class="pcg-input" id="type" name="type" required>
                            <option value="">Choose a report</option>
                            @foreach (\App\Http\Controllers\ReportController::REPORTS as $key => [$label, $isDated])
                                <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="unit">Unit</label>
                        <select class="pcg-input" id="unit" name="unit">
                            <option value="">All in my scope</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}" @selected((string) ($filters['unit'] ?? '') === (string) $u->id)>{{ str_repeat('— ', $u->depth) }}{{ $u->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="from">From <span class="pcg-table__muted" style="display:inline">(dated reports)</span></label>
                        <input class="pcg-input" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="to">To</label>
                        <input class="pcg-input" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
                    </div>
                    <div class="pcg-filters__actions"><button type="submit" class="pcg-btn pcg-btn--primary">Show report</button></div>
                </form>
                <p class="pcg-help">Dated reports default to the last 12 months.</p>
            </div>
        </section>

        @if ($type)
            <section class="pcg-card pcg-mt">
                @if ($total === 0)
                    <p class="pcg-empty">No records for this report and these filters.</p>
                @else
                    <div class="pcg-table-wrap">
                        <table class="pcg-table">
                            <thead><tr>@foreach ($columns as $c)<th scope="col">{{ $c }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>@foreach ($row as $cell)<td>{{ $cell === null || $cell === '' ? '—' : $cell }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pcg-card__body pcg-no-print">{{ $rows->links('admin.partials.pagination') }}</div>
                @endif
            </section>
        @endif
    </div>
@endsection
