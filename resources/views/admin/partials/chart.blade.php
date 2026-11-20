{{-- One single-series chart (App\Support\Trends): $id, $title, $rows [[label, value, tooltip]], $kind 'bars' (horizontal, categories) or 'columns' (months), $unitLabel --}}
@php $max = max(1, ...array_map(fn ($r) => $r[1], $rows ?: [[0, 0]])); @endphp
<section class="pcg-card tr-card" aria-labelledby="{{ $id }}">
    <div class="pcg-card__header"><h3 id="{{ $id }}">{{ $title }}</h3></div>
    <div class="pcg-card__body">
        @if (!$rows)
            <p class="pcg-empty">No data.</p>
        @elseif ($kind === 'bars')
            <ul class="tr-bars" role="list">
                @foreach ($rows as [$label, $value, $tip])
                    <li class="tr-bars__row" tabindex="0" data-tip="{{ $tip }}">
                        <span class="tr-bars__label">{{ $label }}</span>
                        <span class="tr-bars__track"><span class="tr-bars__bar" style="--v: {{ round($value / $max * 100, 2) }}%"></span></span>
                        <span class="tr-bars__value">{{ number_format($value) }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            @php $peak = collect($rows)->sortByDesc(1)->first(); @endphp
            <div class="tr-cols">
                <div class="tr-cols__grid" style="--n: {{ count($rows) }}">
                    @foreach ($rows as $i => [$label, $value, $tip])
                        <div class="tr-cols__col" tabindex="0" data-tip="{{ $tip }}" style="--v: {{ round($value / $max * 100, 2) }}%">
                            <span class="tr-cols__bar"></span>
                            @if ($value > 0 && $value === $peak[1])<span class="tr-cols__peak">{{ number_format($value) }}</span>@endif
                        </div>
                    @endforeach
                </div>
                <div class="tr-cols__axis" style="--n: {{ count($rows) }}" aria-hidden="true">
                    @foreach ($rows as $i => [$label])
                        <span>{{ $i % (count($rows) > 12 ? 3 : 2) === 0 ? $label : '' }}</span>
                    @endforeach
                </div>
                <p class="tr-cols__max" aria-hidden="true">Scale 0–{{ number_format($max) }} {{ $unitLabel }}</p>
            </div>
        @endif
        @if ($rows)
            <details class="tr-table">
                <summary>Show as a table</summary>
                <table class="pcg-table">
                    <thead><tr><th scope="col">{{ $kind === 'bars' ? 'Group' : 'Month' }}</th><th scope="col" class="lcr-num">{{ ucfirst($unitLabel) }}</th></tr></thead>
                    <tbody>
                        @foreach ($rows as [$label, $value])
                            <tr><td>{{ $label }}</td><td class="lcr-num">{{ number_format($value) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        @endif
    </div>
</section>
