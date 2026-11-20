{{-- Credit history of each pool (App\Support\LeaveCredits::history). Params: $histories, $pools --}}
@php $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d); @endphp
@foreach ($pools as $pool => $def)
    @php $rows = $histories[$pool] ?? collect(); @endphp
    <details class="lcr-history" @if ($loop->first) open @endif>
        <summary>{{ $def['label'] }} <span class="lc-muted">({{ $rows->count() }} {{ $rows->count() === 1 ? 'entry' : 'entries' }})</span></summary>
        @if ($rows->isEmpty())
            <p class="lc-muted">Nothing recorded yet.</p>
        @else
            <table class="pcg-table lcr-table">
                <thead><tr><th scope="col">Date</th><th scope="col">What</th><th scope="col" class="lcr-num">Days</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td class="lcr-nowrap">{{ \Illuminate\Support\Carbon::parse($r->date)->format('M j, Y') }}</td>
                            <td>
                                {{ \App\Support\LeaveCredits::KINDS[$r->kind] ?? $r->kind }}
                                @if ($r->note)<span class="lc-muted"> · {{ $r->note }}</span>@endif
                                @if ($r->by)<span class="lc-muted"> · by {{ $r->by }}</span>@endif
                            </td>
                            <td class="lcr-num {{ $r->days < 0 ? 'is-minus' : 'is-plus' }}">
                                {{ $r->kind === 'opening' ? '' : ($r->days > 0 ? '+' : '') }}{{ $fmt($r->days) }}{{ $r->kind === 'opening' ? ' (start)' : '' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </details>
@endforeach
