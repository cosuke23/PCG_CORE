@extends('layouts.admin')

@section('title', 'Operational Course Track')

@php
    $trackCount = $operationTracks->count();
    $rankCount = $operationTracks->pluck('rank')->filter()->unique()->count();
    $providerCount = $operationTracks->pluck('provider')->filter()->unique()->count();
    $activeCount = $operationTracks->filter(fn ($t) => strtolower(trim($t->status ?? '')) === 'active')->count();
    $inactiveCount = $operationTracks->filter(fn ($t) => strtolower(trim($t->status ?? '')) === 'inactive')->count();
    $knownRanks = $operationTracks->pluck('rank')->filter()->unique()->sort()->values();
    $knownUnits = $operationTracks->pluck('division_unit')->filter()->unique()->sort()->values();
    $knownProviders = $operationTracks->pluck('provider')->filter()->unique()->sort()->values();
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
                <h2>{{ $trackCount }} operational {{ \Illuminate\Support\Str::plural('track', $trackCount) }} across {{ $rankCount }} {{ \Illuminate\Support\Str::plural('rank', $rankCount) }}</h2>
                <p>The courses each rank must complete. Personnel are matched to these tracks by their rank.</p>
            </div>
        </header>

        @if (session('success'))
            <div class="pcg-alert pcg-alert--success" role="status">{{ session('success') }}</div>
        @endif

        <details class="ct-add" @if ($errors->any()) open @endif>
            <summary class="pcg-btn pcg-btn--cta"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add track</summary>
            <form method="POST" action="{{ route('operation_tracks.store') }}" class="pcg-card ct-add__form">
                @csrf
                <h3>New operational track</h3>

                @if ($errors->any())
                    <div class="pcg-alert pcg-alert--danger" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="pcg-form-grid">
                    <div class="pcg-field">
                        <label class="pcg-label" for="track-division">Division / unit</label>
                        <input class="pcg-input" type="text" id="track-division" name="division_unit" value="{{ old('division_unit') }}" maxlength="255" list="track-units" required>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="track-rank">Rank</label>
                        <input class="pcg-input" type="text" id="track-rank" name="rank" value="{{ old('rank') }}" maxlength="100" list="track-ranks" required aria-describedby="track-rank-help">
                        <p class="pcg-help" id="track-rank-help">Write it exactly as the existing tracks do, for example E-4 (PO3), so personnel match.</p>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="track-year">Year in grade</label>
                        <input class="pcg-input" type="number" id="track-year" name="year_in_grade" value="{{ old('year_in_grade') }}" min="0" step="1">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="track-no">Order no.</label>
                        <input class="pcg-input" type="number" id="track-no" name="no" value="{{ old('no') }}" min="0" step="1">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="track-course">Course / training</label>
                        <input class="pcg-input" type="text" id="track-course" name="course_training" value="{{ old('course_training') }}" maxlength="255">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="track-provider">Provider</label>
                        <input class="pcg-input" type="text" id="track-provider" name="provider" value="{{ old('provider') }}" maxlength="255" list="track-providers">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="track-status">Status</label>
                        <select class="pcg-input" id="track-status" name="status">
                            @foreach (['Active', 'Inactive'] as $option)
                                <option value="{{ $option }}" @selected(old('status', 'Active') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field ct-add__wide">
                        <label class="pcg-label" for="track-remarks">Remarks</label>
                        <textarea class="pcg-input" id="track-remarks" name="remarks" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <datalist id="track-ranks">
                    @foreach ($knownRanks as $rank)
                        <option value="{{ $rank }}"></option>
                    @endforeach
                </datalist>
                <datalist id="track-units">
                    @foreach ($knownUnits as $unit)
                        <option value="{{ $unit }}"></option>
                    @endforeach
                </datalist>
                <datalist id="track-providers">
                    @foreach ($knownProviders as $provider)
                        <option value="{{ $provider }}"></option>
                    @endforeach
                </datalist>

                <div class="ct-add__actions">
                    <button type="submit" class="pcg-btn pcg-btn--primary">Save track</button>
                </div>
            </form>
        </details>

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
            <h3 id="tracks-heading" class="pcg-visually-hidden">Operational tracks</h3>

            @if ($trackCount > 0)
                <div class="pcg-table-wrap">
                    <table id="operationTracksTable" class="display nowrap">
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
                            @foreach ($operationTracks as $track)
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
                <p class="pcg-empty">No operational tracks yet. Use <strong>Add track</strong> to enter the first course for a rank.</p>
            @endif
        </section>
    </div>

    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables.responsive-3.0.5.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}">
        $(document).ready(function () {
            $('#operationTracksTable').DataTable({
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
                    emptyTable: 'No operational tracks yet',
                    paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' }
                }
            });
        });
    </script>
@endsection
