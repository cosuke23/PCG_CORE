{{--
    Month calendar of leave (App\Support\LeaveCalendar). Every person has a fixed colour
    and initials, so several people on one day stay apart; a day lists everyone when
    opened. $selectable = the member picks the first and last day by clicking.
    Params: $weeks, $byDay, $month, $prevUrl, $nextUrl, $selectable, $minDate (optional), $caption
--}}
@php
    $selectable = $selectable ?? false;
    $minDate = $minDate ?? null;
    $perDay = 3;
@endphp
<div class="lc" data-leave-calendar @if ($selectable) data-selectable @endif>
    <div class="lc__bar">
        <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ $prevUrl }}" aria-label="Previous month"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
        <h3 class="lc__month">{{ $month->format('F Y') }}</h3>
        <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ $nextUrl }}" aria-label="Next month"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    </div>
    @if (!empty($caption))<p class="lc__caption">{{ $caption }}</p>@endif

    <div class="lc__grid" role="grid" aria-label="Leave in {{ $month->format('F Y') }}">
        <div class="lc__row lc__row--head" role="row">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd)
                <div class="lc__wd" role="columnheader">{{ $wd }}</div>
            @endforeach
        </div>
        @foreach ($weeks as $week)
            <div class="lc__row" role="row">
                @foreach ($week as $d)
                    @php
                        $entries = $byDay[$d['date']] ?? [];
                        $disabled = $selectable && $minDate && $d['date'] < $minDate;
                        $count = count($entries);
                    @endphp
                    <div class="lc-day{{ $d['in_month'] ? '' : ' is-out' }}{{ $d['today'] ? ' is-today' : '' }}{{ $d['weekend'] ? ' is-weekend' : '' }}{{ $disabled ? ' is-disabled' : '' }}{{ $count ? ' has-leave' : '' }}"
                         role="gridcell" tabindex="{{ $disabled ? '-1' : '0' }}" data-date="{{ $d['date'] }}"
                         @if ($disabled) aria-disabled="true" @endif
                         aria-label="{{ \Illuminate\Support\Carbon::parse($d['date'])->format('l, F j') }}{{ $count ? ', ' . $count . ' on leave' : '' }}">
                        <span class="lc-day__num">{{ $d['day'] }}</span>
                        @if ($count)
                            <ul class="lc-chips" aria-hidden="true">
                                @foreach (array_slice($entries, 0, $perDay) as $e)
                                    <li class="lc-chip lc-c{{ $e['colour'] }}{{ $e['pending'] ? ' is-pending' : '' }}{{ $e['own'] ? ' is-own' : '' }}">
                                        <span class="lc-chip__ini">{{ $e['initials'] }}</span><span class="lc-chip__name">{{ $e['short'] }}</span>
                                    </li>
                                @endforeach
                                @if ($count > $perDay)
                                    <li class="lc-more">+{{ $count - $perDay }}</li>
                                @endif
                            </ul>
                            <ul class="lc-day__all" hidden>
                                @foreach ($entries as $e)
                                    <li data-colour="{{ $e['colour'] }}" data-pending="{{ $e['pending'] ? '1' : '0' }}">
                                        <span class="lc-dot lc-c{{ $e['colour'] }}"></span>
                                        <strong>{{ trim($e['rank'] . ' ' . $e['name']) }}</strong>
                                        <span>{{ $e['type'] }}{{ $e['pending'] ? ' (waiting for approval)' : '' }}</span>
                                        <span class="lc-muted">{{ \App\Support\LeaveCalendar::dateLabel($e['start'], $e['end']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="lc-panel" data-lc-panel hidden aria-live="polite">
        <div class="lc-panel__head">
            <strong data-lc-panel-title></strong>
            <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" data-lc-panel-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <ul class="lc-panel__list" data-lc-panel-list></ul>
        <p class="lc-muted" data-lc-panel-empty hidden>Nobody is on leave this day.</p>
    </div>
</div>
