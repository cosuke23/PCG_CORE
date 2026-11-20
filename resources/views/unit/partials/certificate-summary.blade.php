{{-- Training certificates and 201 documents near or past their valid-until date, on the unit checker dashboards (App\Support\CertificateExpiry). --}}
@php $ce = \App\Support\CertificateExpiry::forUnitChecker(); @endphp
@if ($ce)
    <section class="uc-leave uc-clear" aria-labelledby="uc-cert-title">
        <div class="uc-leave__main">
            <span class="uc-leave__icon uc-clear__icon" aria-hidden="true"><i class="fa-solid fa-certificate"></i></span>
            <div class="uc-leave__text">
                <h3 id="uc-cert-title">
                    @if ($ce['expired'])
                        {{ $ce['expired'] === 1 ? '1 certificate has' : $ce['expired'] . ' certificates have' }} expired{{ $ce['soon'] ? ', ' . $ce['soon'] . ' more ' . ($ce['soon'] === 1 ? 'expires' : 'expire') . ' within 30 days' : '' }}
                    @else
                        {{ $ce['soon'] === 1 ? '1 certificate expires' : $ce['soon'] . ' certificates expire' }} within 30 days
                    @endif
                </h3>
                <p>Training certificates and 201 documents with an end date in {{ $ce['scope'] }}. Members are emailed and notified 30 days, 7 days and on the day.</p>
            </div>
        </div>
        <ul class="uc-clear__list" aria-label="Certificates to renew">
            @foreach ($ce['list']->take(10) as $c)
                <li class="{{ $c->days_left < 0 ? 'is-expired' : ($c->days_left <= 7 ? 'is-soon' : '') }}">
                    <span class="uc-clear__who">{{ trim(($c->rank ?? '') . ' ' . $c->name) }}</span>
                    <span>{{ $c->title }}</span>
                    <span class="uc-clear__when">{{ \App\Support\ClearanceExpiry::when($c->days_left) }} · {{ \Illuminate\Support\Carbon::parse($c->valid_until)->format('M j, Y') }}</span>
                </li>
            @endforeach
            @if ($ce['list']->count() > 10)
                <li class="lc-muted">+{{ $ce['list']->count() - 10 }} more</li>
            @endif
        </ul>
    </section>
@endif
