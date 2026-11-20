{{-- Leave at a glance on the unit checker dashboards (App\Support\UnitLeaveSummary). --}}
@php $ls = \App\Support\UnitLeaveSummary::get(); @endphp
@if ($ls)
    <section class="uc-leave" aria-labelledby="uc-leave-title">
        <div class="uc-leave__main">
            <span class="uc-leave__icon" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span>
            <div class="uc-leave__text">
                <h3 id="uc-leave-title">
                    @if ($ls['pending'])
                        {{ $ls['pending'] === 1 ? '1 leave request is' : $ls['pending'] . ' leave requests are' }} waiting for your approval
                    @else
                        No leave is waiting for your approval
                    @endif
                </h3>
                <p>
                    {{ $ls['away']->count() === 1 ? '1 member is' : $ls['away']->count() . ' members are' }} on leave today in {{ $ls['scope'] }}
                    · {{ $ls['starting'] }} starting in the next 7 days
                    @if ($ls['noOpening'])
                        · {{ $ls['noOpening'] === 1 ? '1 member has' : $ls['noOpening'] . ' members have' }} no opening balance yet
                    @endif
                </p>
            </div>
            <div class="uc-leave__actions">
                <a class="pcg-btn {{ $ls['pending'] ? 'pcg-btn--primary' : 'pcg-btn--secondary' }}" href="{{ route('unit.leave') }}">
                    <i class="fa-solid fa-calendar-check" aria-hidden="true"></i> {{ $ls['pending'] ? 'Review leave' : 'Open leave calendar' }}
                </a>
                <a class="pcg-btn pcg-btn--ghost" href="{{ route('unit.leave.credits') }}">Leave credits</a>
            </div>
        </div>
        @if ($ls['away']->isNotEmpty())
            <ul class="uc-leave__away" aria-label="On leave today">
                @foreach ($ls['away']->take(8) as $a)
                    <li>
                        <span class="lc-chip lc-c{{ \App\Support\LeaveCalendar::colour($a->serial_number) }}"><span class="lc-chip__ini">{{ \App\Support\LeaveCalendar::initials($a->name) }}</span><span class="lc-chip__name">{{ trim(($a->rank ?? '') . ' ' . \App\Support\LeaveCalendar::shortName($a->name)) }}</span></span>
                        <span class="lc-muted">until {{ \Illuminate\Support\Carbon::parse($a->end_date)->format('M j') }}</span>
                    </li>
                @endforeach
                @if ($ls['away']->count() > 8)
                    <li class="lc-muted">+{{ $ls['away']->count() - 8 }} more</li>
                @endif
            </ul>
        @endif
    </section>
@endif
