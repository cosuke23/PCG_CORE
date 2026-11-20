{{-- Leave month summary and CSV export (App\Support\LeaveReport, LeaveReportController).
     Params: $byUnit, $byType, $total, $month, $from, $to, $unit, $unitList, $showUnitFilter, $action, $exportUrl, $prev, $next --}}
<form method="GET" action="{{ $action }}" class="lc-filter pcg-mb" data-pcg-no-busy>
    <a class="pcg-btn pcg-btn--ghost pcg-btn--sm" href="{{ $action }}?{{ http_build_query(array_filter(['month' => $prev, 'unit' => $showUnitFilter ? $unit : null])) }}" aria-label="Previous month"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
    <label class="pcg-label" for="lr-month">Month</label>
    <input class="pcg-input pcg-input--sm" type="month" id="lr-month" name="month" value="{{ $month->format('Y-m') }}">
    <a class="pcg-btn pcg-btn--ghost pcg-btn--sm" href="{{ $action }}?{{ http_build_query(array_filter(['month' => $next, 'unit' => $showUnitFilter ? $unit : null])) }}" aria-label="Next month"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    @if ($showUnitFilter)
        <label class="pcg-label" for="lr-unit">Unit</label>
        <select class="pcg-input pcg-input--sm" id="lr-unit" name="unit">
            <option value="">All units</option>
            @foreach ($unitList as $u)
                <option value="{{ $u }}" @selected(strcasecmp($unit, $u) === 0)>{{ $u }}</option>
            @endforeach
        </select>
    @endif
    <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Show</button>
</form>

<ul class="lcr-cards pcg-mb">
    <li class="lcr-card"><span class="lcr-card__label">Members on leave</span><strong class="lcr-card__value">{{ number_format($total->members) }}</strong><span class="lcr-card__unit">approved, in {{ $month->format('F') }}</span></li>
    <li class="lcr-card"><span class="lcr-card__label">Leave days</span><strong class="lcr-card__value">{{ number_format($total->days) }}</strong><span class="lcr-card__unit">inside the month</span></li>
    <li class="lcr-card"><span class="lcr-card__label">Waiting</span><strong class="lcr-card__value">{{ number_format($total->pending) }}</strong><span class="lcr-card__unit">requests not decided</span></li>
    <li class="lcr-card"><span class="lcr-card__label">Declined</span><strong class="lcr-card__value">{{ number_format($total->declined) }}</strong><span class="lcr-card__unit">requests</span></li>
</ul>

<section class="pcg-card pcg-mb" aria-labelledby="lr-units">
    <div class="pcg-card__header"><h3 id="lr-units">By unit, {{ $month->format('F Y') }}</h3></div>
    @if ($byUnit->isEmpty())
        <p class="pcg-empty">No leave in {{ $month->format('F Y') }}.</p>
    @else
        <table class="pcg-table">
            <thead><tr><th scope="col">Unit</th><th scope="col" class="lcr-num">Members on leave</th><th scope="col" class="lcr-num">Approved requests</th><th scope="col" class="lcr-num">Days in month</th><th scope="col" class="lcr-num">Waiting</th><th scope="col" class="lcr-num">Declined</th></tr></thead>
            <tbody>
                @foreach ($byUnit as $r)
                    <tr><td><strong>{{ $r->unit }}</strong></td><td class="lcr-num">{{ $r->members }}</td><td class="lcr-num">{{ $r->approved }}</td><td class="lcr-num"><strong>{{ $r->days }}</strong></td><td class="lcr-num">{{ $r->pending }}</td><td class="lcr-num">{{ $r->declined }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>

<section class="pcg-card pcg-mb" aria-labelledby="lr-types">
    <div class="pcg-card__header"><h3 id="lr-types">By leave type (approved)</h3></div>
    @if ($byType->isEmpty())
        <p class="pcg-empty">No approved leave in {{ $month->format('F Y') }}.</p>
    @else
        <table class="pcg-table">
            <thead><tr><th scope="col">Leave type</th><th scope="col" class="lcr-num">Members</th><th scope="col" class="lcr-num">Requests</th><th scope="col" class="lcr-num">Days in month</th></tr></thead>
            <tbody>
                @foreach ($byType as $r)
                    <tr><td>{{ $r->type }}</td><td class="lcr-num">{{ $r->members }}</td><td class="lcr-num">{{ $r->approved }}</td><td class="lcr-num"><strong>{{ $r->days }}</strong></td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>

<section class="pcg-card" aria-labelledby="lr-export">
    <div class="pcg-card__header"><h3 id="lr-export">Download as CSV</h3></div>
    <div class="pcg-card__body">
        <p class="pcg-help">Every request that overlaps the dates, with serial, unit, type, dates, status and the decision (reasons are not included). Opens in Excel. Downloads are recorded in the audit log.</p>
        <form method="GET" action="{{ $exportUrl }}" class="lc-filter" data-pcg-no-busy>
            @if ($showUnitFilter && $unit !== '')
                <input type="hidden" name="unit" value="{{ $unit }}">
            @endif
            <label class="pcg-label" for="lr-from">From</label>
            <input class="pcg-input pcg-input--sm" type="date" id="lr-from" name="from" value="{{ $from }}" required>
            <label class="pcg-label" for="lr-to">To</label>
            <input class="pcg-input pcg-input--sm" type="date" id="lr-to" name="to" value="{{ $to }}" required>
            <label class="pcg-label" for="lr-status">Status</label>
            <select class="pcg-input pcg-input--sm" id="lr-status" name="status">
                @foreach (\App\Support\LeaveReport::STATUSES as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--sm"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Download CSV</button>
        </form>
        <p class="pcg-help">Up to one year at a time.</p>
    </div>
</section>
