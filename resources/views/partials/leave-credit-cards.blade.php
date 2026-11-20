{{-- Balance per credit pool (App\Support\LeaveCredits::balances). Params: $credits, $self (member wording) --}}
@php $self = $self ?? false; $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d); @endphp
<ul class="lcr-cards">
    @foreach ($credits as $c)
        <li class="lcr-card{{ $c['set'] ? '' : ' is-unset' }}{{ $c['set'] && $c['available'] <= 0 ? ' is-empty' : '' }}">
            <span class="lcr-card__label">{{ $c['label'] }}</span>
            @if ($c['set'])
                <strong class="lcr-card__value">{{ $fmt($c['available']) }}</strong>
                <span class="lcr-card__unit">{{ abs(($c['available'] ?? 0) - 1) < 1e-9 ? 'day' : 'days' }} available</span>
                @if ($c['held'] > 0)
                    <span class="lcr-card__held">{{ $fmt($c['held']) }} held by {{ $self ? 'your requests' : 'requests' }} waiting for approval</span>
                @endif
            @else
                <strong class="lcr-card__value">—</strong>
                <span class="lcr-card__unit">{{ $self ? 'Not set yet: ask your unit checker' : 'Opening balance not set' }}</span>
            @endif
            <span class="lcr-card__rule">{{ $c['rule'] }}</span>
        </li>
    @endforeach
</ul>
