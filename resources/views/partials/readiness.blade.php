{{-- Manpower readiness table and the people away (App\Support\Readiness).
     Params: $units, $people, $totals, $day, $unit, $units list ($unitList), $action, $showUnitFilter --}}
@php $isToday = $day->isSameDay(\App\Support\LeaveCalendar::today()); @endphp
<form method="GET" action="{{ $action }}" class="lc-filter pcg-mb" data-pcg-no-busy>
    <label class="pcg-label" for="rd-date">Day</label>
    <input class="pcg-input pcg-input--sm" type="date" id="rd-date" name="date" value="{{ $day->toDateString() }}">
    @if ($showUnitFilter)
        <label class="pcg-label" for="rd-unit">Unit</label>
        <select class="pcg-input pcg-input--sm" id="rd-unit" name="unit">
            <option value="">All units</option>
            @foreach ($unitList as $u)
                <option value="{{ $u }}" @selected(strcasecmp($unit, $u) === 0)>{{ $u }}</option>
            @endforeach
        </select>
    @endif
    <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Show</button>
</form>

<ul class="lcr-cards pcg-mb">
    <li class="lcr-card"><span class="lcr-card__label">Strength</span><strong class="lcr-card__value">{{ number_format($totals['strength']) }}</strong><span class="lcr-card__unit">approved members</span></li>
    <li class="lcr-card"><span class="lcr-card__label">Available</span><strong class="lcr-card__value">{{ number_format($totals['available']) }}</strong><span class="lcr-card__unit">{{ $totals['percent'] }}% of strength</span></li>
    <li class="lcr-card"><span class="lcr-card__label">On leave</span><strong class="lcr-card__value">{{ number_format($totals['leave']) }}</strong><span class="lcr-card__unit">approved leave</span></li>
    <li class="lcr-card"><span class="lcr-card__label">Deployed</span><strong class="lcr-card__value">{{ number_format($totals['deployed'] ?? 0) }}</strong><span class="lcr-card__unit">sea duty and deployments</span></li>
    <li class="lcr-card"><span class="lcr-card__label">In training</span><strong class="lcr-card__value">{{ number_format($totals['training']) }}</strong><span class="lcr-card__unit">MEPTDI classes</span></li>
</ul>

<section class="pcg-card pcg-mb" aria-labelledby="rd-units">
    <div class="pcg-card__header"><h3 id="rd-units">By unit, {{ $isToday ? 'today' : $day->format('F j, Y') }}</h3></div>
    @if ($units->isEmpty())
        <p class="pcg-empty">No approved members found.</p>
    @else
        <table class="pcg-table">
            <thead><tr><th scope="col">Unit</th><th scope="col" class="lcr-num">Strength</th><th scope="col" class="lcr-num">Deployed</th><th scope="col" class="lcr-num">On leave</th><th scope="col" class="lcr-num">In training</th><th scope="col" class="lcr-num">Available</th><th scope="col">Readiness</th></tr></thead>
            <tbody>
                @foreach ($units as $r)
                    <tr>
                        <td><strong>{{ $r->unit }}</strong></td>
                        <td class="lcr-num">{{ $r->strength }}</td>
                        <td class="lcr-num">{{ $r->deployed ?? 0 }}</td>
                        <td class="lcr-num">{{ $r->leave }}</td>
                        <td class="lcr-num">{{ $r->training }}</td>
                        <td class="lcr-num"><strong>{{ $r->available }}</strong></td>
                        <td><span class="rd-bar {{ $r->percent < 70 ? 'is-low' : '' }}" style="--rd: {{ $r->percent }}%"><span>{{ $r->percent }}%</span></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>

<section class="pcg-card" aria-labelledby="rd-away">
    <div class="pcg-card__header"><h3 id="rd-away">{{ $people->count() === 1 ? '1 member is' : $people->count() . ' members are' }} away {{ $isToday ? 'today' : 'on ' . $day->format('F j') }}</h3></div>
    @if ($people->isEmpty())
        <p class="pcg-empty">Everyone is available.</p>
    @else
        <table class="pcg-table">
            <thead><tr><th scope="col">Member</th><th scope="col">Unit</th><th scope="col">Away for</th><th scope="col">Until</th></tr></thead>
            <tbody>
                @foreach ($people as $p)
                    <tr>
                        <td><strong>{{ trim(($p->rank ?? '') . ' ' . $p->name) }}</strong> <span class="lc-muted pcg-mono">{{ $p->serial }}</span></td>
                        <td>{{ $p->unit }}</td>
                        <td><span class="pcg-badge pcg-badge--{{ ['leave' => 'warning', 'deployed' => 'success'][$p->state] ?? 'info' }}">{{ ['leave' => 'Leave', 'deployed' => 'Deployed'][$p->state] ?? 'Training' }}</span> {{ $p->detail }}</td>
                        <td class="lcr-nowrap">{{ $p->until ? \Illuminate\Support\Carbon::parse($p->until)->format('M j, Y') : ($p->state === 'deployed' ? 'ongoing' : '—') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
