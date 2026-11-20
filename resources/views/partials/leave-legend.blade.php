{{-- Who is on leave in the month shown, one line per leave, in each person's calendar colour. --}}
<section class="pcg-card" aria-labelledby="leave-legend-heading">
    <div class="pcg-card__header"><h3 id="leave-legend-heading">{{ count($legend) === 1 ? '1 leave' : count($legend) . ' leaves' }} in {{ $month->format('F') }}</h3></div>
    @if (!$legend)
        <p class="pcg-empty">Nobody in the unit is on approved leave this month.</p>
    @else
        <ul class="lc-legend">
            @foreach ($legend as $e)
                <li class="{{ $e['pending'] ? 'is-pending' : '' }}">
                    <span class="lc-chip lc-c{{ $e['colour'] }}{{ $e['pending'] ? ' is-pending' : '' }}"><span class="lc-chip__ini">{{ $e['initials'] }}</span></span>
                    <span class="lc-legend__who">
                        <strong>{{ trim($e['rank'] . ' ' . $e['name']) }}</strong>
                        <span class="lc-muted">{{ $e['type'] }}{{ $e['pending'] ? ' · waiting for approval' : '' }}</span>
                    </span>
                    <span class="lc-legend__when">{{ \App\Support\LeaveCalendar::dateLabel($e['start'], $e['end']) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
