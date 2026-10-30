{{-- Single-series horizontal bars as a table: the numbers stay readable without the bars. --}}
@php $max = max(1, collect($rows)->max('count')); $sum = max(1, collect($rows)->sum('count')); @endphp
@if (empty($rows))
    <p class="pcg-empty">{{ $empty }}</p>
@else
    <div class="pcg-table-wrap">
        <table class="pcg-table pcg-bars">
            <caption class="pcg-visually-hidden">{{ $caption }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ $labelHeading }}</th>
                    <th scope="col" class="pcg-bars__plot-head"><span class="pcg-visually-hidden">Share</span></th>
                    <th scope="col" class="pcg-bars__num">Personnel</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php $pct = round($row['count'] / $sum * 100); @endphp
                    <tr title="{{ $row['title'] }}: {{ $row['count'] }} ({{ $pct }}%)">
                        <th scope="row">
                            @if (!empty($row['unit_id']))
                                <a href="{{ route('admin.personnel.index', ['unit' => $row['unit_id']]) }}">{{ $row['label'] }}</a>
                            @else
                                {{ $row['label'] }}
                            @endif
                            @if (!empty($row['inactive'])) <span class="pcg-badge">Inactive</span> @endif
                        </th>
                        <td class="pcg-bars__plot" aria-hidden="true">
                            @if ($row['count'] > 0)
                                <span class="pcg-bars__bar" style="width: {{ max(1, round($row['count'] / $max * 100, 1)) }}%"></span>
                            @endif
                        </td>
                        <td class="pcg-bars__num">{{ number_format($row['count']) }} <span class="pcg-table__muted pcg-bars__pct">{{ $pct }}%</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
