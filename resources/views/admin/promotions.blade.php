@extends('layouts.admin')

@section('title', 'Promotion eligibility')

@section('content')
    @php $n = $counts['eligible'] ?? 0; @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $n === 1 ? '1 member is' : $n . ' members are' }} eligible for promotion by {{ $asOf->format('F j, Y') }} in {{ $unit !== '' ? $unit : $scopeName }}</h2>
                <p>Time in grade from the date of rank in the personnel master ({{ $years['non_officer'] ?? 2 }} years for non-officers, {{ $years['officer'] ?? 3 }} for officers unless a rank has its own rule) and the required schooling from the training records. These are defaults until the PCG directive is set in the configuration; recording a promotion stays on each person's page.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.promotions') }}" class="lc-filter pcg-mb" data-pcg-no-busy>
            <label class="pcg-label" for="pe-date">Eligible by</label>
            <input class="pcg-input pcg-input--sm" type="date" id="pe-date" name="as_of" value="{{ $asOf->toDateString() }}">
            <label class="pcg-label" for="pe-unit">Unit</label>
            <select class="pcg-input pcg-input--sm" id="pe-unit" name="unit">
                <option value="">All units</option>
                @foreach ($units as $u)
                    <option value="{{ $u }}" @selected(strcasecmp($unit, $u) === 0)>{{ $u }}</option>
                @endforeach
            </select>
            <label class="pcg-label" for="pe-state">Show</label>
            <select class="pcg-input pcg-input--sm" id="pe-state" name="state">
                @foreach (['all' => 'Everyone'] + \App\Support\PromotionEligibility::STATES as $k => $label)
                    <option value="{{ $k }}" @selected($state === $k)>{{ $label }}{{ $k === 'all' ? '' : ' (' . ($counts[$k] ?? 0) . ')' }}</option>
                @endforeach
            </select>
            <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Show</button>
            @if ($canExport)
                <a class="pcg-btn pcg-btn--ghost pcg-btn--sm" href="{{ route('admin.promotions.export', request()->query()) }}"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Download CSV</a>
            @endif
        </form>

        <section class="pcg-card">
            @if ($shown->isEmpty())
                <p class="pcg-empty">Nobody in this list.</p>
            @else
                <table class="pcg-table">
                    <thead><tr><th scope="col">Member</th><th scope="col">Rank</th><th scope="col">Unit</th><th scope="col">Date of rank</th><th scope="col" class="lcr-num">In grade</th><th scope="col">Eligible from</th><th scope="col">State</th></tr></thead>
                    <tbody>
                        @foreach ($shown as $r)
                            <tr>
                                <td><a href="{{ route('admin.personnel.show', $r->id) }}"><strong>{{ $r->name }}</strong></a><br><span class="pcg-mono">{{ $r->serial_number }}</span></td>
                                <td>{{ $r->rank }}@if ($r->next) <span class="lc-muted">→ {{ $r->next }}</span>@endif</td>
                                <td>{{ $r->unit ?? '—' }}</td>
                                <td class="lcr-nowrap">{{ $r->rank_date ? \Illuminate\Support\Carbon::parse($r->rank_date)->format('M j, Y') : '—' }}</td>
                                <td class="lcr-num">{{ $r->years_in_grade !== null ? $r->years_in_grade . ' / ' . $r->required_years . ' yrs' : '—' }}</td>
                                <td class="lcr-nowrap">{{ $r->due ? \Illuminate\Support\Carbon::parse($r->due)->format('M j, Y') : '—' }}</td>
                                <td>
                                    <span class="pcg-badge {{ ['eligible' => 'pcg-badge--success', 'schooling' => 'pcg-badge--warning', 'soon' => 'pcg-badge--info', 'no_rank_date' => 'pcg-badge--danger'][$r->state] ?? '' }}">{{ \App\Support\PromotionEligibility::STATES[$r->state] }}</span>
                                    @if ($r->missing)<br><span class="lc-muted">Needs: {{ implode(', ', $r->missing) }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
@endsection
