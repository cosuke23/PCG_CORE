{{-- Clearances near or past their valid-until date, on the unit checker dashboards (App\Support\ClearanceExpiry). --}}
@php $cs = \App\Support\ClearanceExpiry::forUnitChecker(); @endphp
@if ($cs)
    <section class="uc-leave uc-clear" aria-labelledby="uc-clear-title">
        <div class="uc-leave__main">
            <span class="uc-leave__icon uc-clear__icon" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
            <div class="uc-leave__text">
                <h3 id="uc-clear-title">
                    @if ($cs['expired'])
                        {{ $cs['expired'] === 1 ? '1 clearance has' : $cs['expired'] . ' clearances have' }} expired{{ $cs['soon'] ? ', ' . $cs['soon'] . ' more ' . ($cs['soon'] === 1 ? 'expires' : 'expire') . ' within 30 days' : '' }}
                    @else
                        {{ $cs['soon'] === 1 ? '1 clearance expires' : $cs['soon'] . ' clearances expire' }} within 30 days
                    @endif
                </h3>
                <p>NBI, Ombudsman and Sandiganbayan clearances in {{ $cs['scope'] }}. Members are emailed 30 days, 7 days and on the day; an expired box opens for the new upload.</p>
            </div>
        </div>
        <ul class="uc-clear__list" aria-label="Clearances to renew">
            @foreach ($cs['list']->take(10) as $c)
                <li class="{{ $c->days_left < 0 ? 'is-expired' : ($c->days_left <= 7 ? 'is-soon' : '') }}">
                    <span class="uc-clear__who">{{ trim(($c->rank ?? '') . ' ' . $c->name) }}</span>
                    <span>{{ $c->label }}</span>
                    <span class="uc-clear__when">{{ \App\Support\ClearanceExpiry::when($c->days_left) }} · {{ \Illuminate\Support\Carbon::parse($c->valid_until)->format('M j, Y') }}</span>
                </li>
            @endforeach
            @if ($cs['list']->count() > 10)
                <li class="lc-muted">+{{ $cs['list']->count() - 10 }} more</li>
            @endif
        </ul>
    </section>
@endif
