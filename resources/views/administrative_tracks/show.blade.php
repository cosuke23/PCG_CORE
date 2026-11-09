@extends('layouts.admin')

@section('title', 'Administrative Course Track')

@php
    $trackCount = $administrativeTracks->count();
    $rankCount = $administrativeTracks->pluck('rank')->filter()->unique()->count();
    $providerCount = $administrativeTracks->pluck('provider')->filter()->unique()->count();
    $activeCount = $administrativeTracks->filter(fn ($t) => strtolower(trim($t->status ?? '')) === 'active')->count();
    $inactiveCount = $administrativeTracks->filter(fn ($t) => strtolower(trim($t->status ?? '')) === 'inactive')->count();
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/datatables/responsive.dataTables-3.0.5.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/course-tracks.css') }}">
@endsection

@section('content')
    <div class="pcg-page ct">

        <header class="pcg-page__header">
            <div>
                <h2>{{ $trackCount }} administrative {{ \Illuminate\Support\Str::plural('track', $trackCount) }} across {{ $rankCount }} {{ \Illuminate\Support\Str::plural('rank', $rankCount) }}</h2>
                <p>The administrative courses each rank must complete, by year in grade.</p>
            </div>
            <a class="pcg-btn pcg-btn--secondary" href="{{ route('personnel.administrative.training') }}">See personnel by rank</a>
        </header>

        <div class="pcg-stats">
            <div class="pcg-stat">
                <span class="pcg-stat__label">Active</span>
                <span class="pcg-stat__value">{{ number_format($activeCount) }}</span>
                <span class="pcg-stat__hint">of {{ number_format($trackCount) }} {{ \Illuminate\Support\Str::plural('track', $trackCount) }}</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">Inactive</span>
                <span class="pcg-stat__value">{{ number_format($inactiveCount) }}</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">Providers</span>
                <span class="pcg-stat__value">{{ number_format($providerCount) }}</span>
                <span class="pcg-stat__hint">schools running these courses</span>
            </div>
        </div>

        <section class="pcg-card ct-card" aria-labelledby="tracks-heading">
            <h3 id="tracks-heading" class="pcg-visually-hidden">Administrative tracks</h3>

            @if ($trackCount > 0)
                <div class="pcg-table-wrap">
                    <table id="administrativeTracksTable" class="display nowrap">
                        <thead>
                            <tr>
                                <th scope="col">No.</th>
                                <th scope="col">Division / unit</th>
                                <th scope="col">Rank</th>
                                <th scope="col">Year in grade</th>
                                <th scope="col">Course / training</th>
                                <th scope="col">Provider</th>
                                <th scope="col">Status</th>
                                <th scope="col">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($administrativeTracks as $track)
                                @php($status = strtolower(trim($track->status ?? '')))
                                <tr>
                                    <td>{{ $track->no ?? $loop->iteration }}</td>
                                    <td>@if (filled($track->division_unit)){{ $track->division_unit }}@else<span class="ct-none">—</span>@endif</td>
                                    <td><span class="ct-rank">{{ $track->rank ?? '—' }}</span></td>
                                    <td>@if (filled($track->year_in_grade)){{ $track->year_in_grade }}@else<span class="ct-none">—</span>@endif</td>
                                    <td>@if (filled($track->course_training))<span class="ct-course">{{ $track->course_training }}</span>@else<span class="ct-none">—</span>@endif</td>
                                    <td>@if (filled($track->provider)){{ $track->provider }}@else<span class="ct-none">—</span>@endif</td>
                                    <td>
                                        @if ($status === 'active')
                                            <span class="pcg-badge pcg-badge--success">Active</span>
                                        @elseif ($status === '')
                                            <span class="ct-none">—</span>
                                        @else
                                            <span class="pcg-badge">{{ $track->status }}</span>
                                        @endif
                                    </td>
                                    <td>@if (filled($track->remarks)){{ $track->remarks }}@else<span class="ct-none">—</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="pcg-empty">No administrative tracks are recorded yet. Ask the system administrator to load the track list; it appears here once loaded.</p>
            @endif
        </section>
    </div>

    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables.responsive-3.0.5.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}">
        $(document).ready(function () {
            $('#administrativeTracksTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, 'All']
                ],
                order: [
                    [2, 'asc'],
                    [3, 'asc']
                ],
                columnDefs: [
                    { targets: 0, searchable: false }
                ],
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ tracks',
                    info: 'Showing _START_ to _END_ of _TOTAL_ tracks',
                    infoEmpty: 'No tracks to show',
                    zeroRecords: 'No track matches that search',
                    emptyTable: 'No administrative tracks yet',
                    paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' }
                }
            });
        });
    </script>
@endsection
