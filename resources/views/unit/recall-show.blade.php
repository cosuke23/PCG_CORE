@extends('layouts.unit')

@section('title', 'Recall')

@section('content')
    <div class="pcg-page">
        <p><a href="{{ route('unit.recall') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All recalls</a></p>
        <div class="uc-head">
            <h2>{{ $r->waiting === 0 ? 'Everyone answered' : ($r->waiting === 1 ? '1 member has not answered' : $r->waiting . ' members have not answered') }} · {{ $r->coming }} on the way, {{ $r->cannot }} cannot come</h2>
            <p>{{ $r->title }} · {{ $r->unit_name ?? 'Every unit' }} · sent {{ \Illuminate\Support\Carbon::parse($r->created_at)->timezone('Asia/Manila')->format('M j, Y g:i A') }} by {{ $r->sent_by_name }}{{ $r->closed_at ? ' · closed' : '' }}</p>
        </div>

        <ul class="lcr-cards pcg-mb">
            <li class="lcr-card"><span class="lcr-card__label">On the way</span><strong class="lcr-card__value">{{ $r->coming }}</strong></li>
            <li class="lcr-card"><span class="lcr-card__label">Cannot come</span><strong class="lcr-card__value">{{ $r->cannot }}</strong></li>
            <li class="lcr-card"><span class="lcr-card__label">No answer</span><strong class="lcr-card__value">{{ $r->waiting }}</strong></li>
            <li class="lcr-card"><span class="lcr-card__label">Recalled</span><strong class="lcr-card__value">{{ $r->recipients }}</strong></li>
        </ul>

        <section class="pcg-card pcg-mb">
            <div class="pcg-card__body"><p class="rc-msg">{{ $r->message }}</p>@if ($r->report_to)<p><strong>Report to:</strong> {{ $r->report_to }}</p>@endif</div>
        </section>

        <section class="pcg-card pcg-mb" aria-labelledby="rc-who">
            <div class="pcg-card__header"><h3 id="rc-who">Answers</h3></div>
            <table class="pcg-table">
                <thead><tr><th scope="col">Member</th><th scope="col">Unit</th><th scope="col">Answer</th><th scope="col">When</th></tr></thead>
                <tbody>
                    @foreach ($responses as $x)
                        <tr>
                            <td><strong>{{ trim(($x->rank ?? '') . ' ' . $x->name) }}</strong></td>
                            <td>{{ $x->mother_unit }}</td>
                            <td>
                                <span class="pcg-badge {{ ['coming' => 'pcg-badge--success', 'cannot' => 'pcg-badge--danger'][$x->answer] ?? 'pcg-badge--warning' }}">{{ \App\Support\Recalls::ANSWERS[$x->answer] ?? 'No answer yet' }}</span>
                                @if ($x->note)<br><span class="lc-muted">{{ $x->note }}</span>@endif
                            </td>
                            <td class="lcr-nowrap">{{ $x->answered_at ? \Illuminate\Support\Carbon::parse($x->answered_at)->timezone('Asia/Manila')->format('M j, g:i A') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        @unless ($r->closed_at)
            <form method="POST" action="{{ route('unit.recall.close', $r->id) }}">
                @csrf
                <button type="submit" class="pcg-btn pcg-btn--secondary" data-pcg-confirm="Close this recall? Members can no longer answer it." data-pcg-confirm-ok="Close recall">Close recall</button>
            </form>
        @endunless
    </div>
@endsection
