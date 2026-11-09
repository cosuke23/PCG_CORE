@extends('layouts.admin')

@section('title', 'Personnel Operational Training')

@php
    $personCount = $personnelTraining->count();
    $withRequirement = $personnelTraining->filter(fn ($p) => $p['tracks']->isNotEmpty())->count();
    $withoutRequirement = $personCount - $withRequirement;
    $trackCount = $operationTracks->count();
    $rankCount = $operationTracks->pluck('rank')->filter()->unique()->count();
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
                <h2>{{ number_format($withRequirement) }} of {{ number_format($personCount) }} personnel have an operational course for their rank</h2>
                <p>Ranks are matched to the {{ $trackCount }} operational {{ \Illuminate\Support\Str::plural('track', $trackCount) }} ({{ $rankCount }} {{ \Illuminate\Support\Str::plural('rank', $rankCount) }}). Pick a course in a row to see its unit, year in grade, provider and status.</p>
            </div>
            <a class="pcg-btn pcg-btn--secondary" href="{{ route('operation_tracks.index') }}">Operational tracks</a>
        </header>

        <div class="pcg-stats">
            <div class="pcg-stat">
                <span class="pcg-stat__label">With a requirement</span>
                <span class="pcg-stat__value">{{ number_format($withRequirement) }}</span>
                <span class="pcg-stat__hint">rank has an operational track</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">No requirement</span>
                <span class="pcg-stat__value">{{ number_format($withoutRequirement) }}</span>
                <span class="pcg-stat__hint">no track for their rank</span>
            </div>
        </div>

        <section class="pcg-card ct-card" aria-labelledby="personnel-heading">
            <h3 id="personnel-heading" class="pcg-visually-hidden">Personnel and their operational courses</h3>

            @if ($personCount > 0)
                <div class="pcg-table-wrap">
                    <table id="personnelOperationalTrainingTable" class="display nowrap">
                        <thead>
                            <tr>
                                <th scope="col">No.</th>
                                <th scope="col">Personnel</th>
                                <th scope="col">Rank</th>
                                <th scope="col">Division / unit</th>
                                <th scope="col">Year in grade</th>
                                <th scope="col">Course / training</th>
                                <th scope="col">Provider</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($personnelTraining as $personnel)
                                @php
                                    $user = $personnel['user'];
                                    $tracks = $personnel['tracks'];
                                    $defaultTrack = $tracks->first();
                                    $status = strtolower(trim($defaultTrack->status ?? ''));
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <span class="ct-name">{{ $user->name ?? '—' }}</span>
                                        @if (!empty($user->serial_number))
                                            <span class="ct-sub pcg-mono">{{ $user->serial_number }}</span>
                                        @endif
                                    </td>
                                    <td><span class="ct-rank">{{ $user->rank ?? '—' }}</span></td>
                                    <td><span class="division-display @if (blank($defaultTrack->division_unit ?? null)) ct-none @endif" data-user-id="{{ $user->id }}">{{ filled($defaultTrack->division_unit ?? null) ? $defaultTrack->division_unit : '—' }}</span></td>
                                    <td><span class="year-display @if (blank($defaultTrack->year_in_grade ?? null)) ct-none @endif" data-user-id="{{ $user->id }}">{{ filled($defaultTrack->year_in_grade ?? null) ? $defaultTrack->year_in_grade : '—' }}</span></td>
                                    <td>
                                        @if ($tracks->count() > 0)
                                            <label class="pcg-visually-hidden" for="op-course-{{ $user->id }}">Operational course for {{ $user->name }}</label>
                                            <select
                                                id="op-course-{{ $user->id }}"
                                                name="course_training[{{ $user->id }}]"
                                                class="course-training-select pcg-input pcg-input--sm ct-select"
                                                data-user-id="{{ $user->id }}">
                                                <option value="">Select course / training</option>
                                                @foreach ($tracks as $track)
                                                    <option
                                                        value="{{ $track->id }}"
                                                        data-division="{{ $track->division_unit ?? '' }}"
                                                        data-year="{{ $track->year_in_grade ?? '' }}"
                                                        data-provider="{{ $track->provider ?? '' }}"
                                                        data-status="{{ $track->status ?? '' }}"
                                                        @selected($defaultTrack && $defaultTrack->id == $track->id)>{{ $track->course_training ?? '—' }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span class="ct-quiet">No training requirement</span>
                                        @endif
                                    </td>
                                    <td><span class="provider-display @if (blank($defaultTrack->provider ?? null)) ct-none @endif" data-user-id="{{ $user->id }}">{{ filled($defaultTrack->provider ?? null) ? $defaultTrack->provider : '—' }}</span></td>
                                    <td>
                                        <span class="status-display" data-user-id="{{ $user->id }}">
                                            @if ($status === 'active')
                                                <span class="pcg-badge pcg-badge--success">Active</span>
                                            @elseif ($defaultTrack && $status !== '')
                                                <span class="pcg-badge">{{ $defaultTrack->status }}</span>
                                            @else
                                                <span class="ct-none">—</span>
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="pcg-empty">No personnel with a rank and serial number yet. They appear here once their registration records both.</p>
            @endif
        </section>
    </div>

    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables.responsive-3.0.5.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}">
        $(document).ready(function () {
            $('#personnelOperationalTrainingTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, 'All']
                ],
                order: [
                    [2, 'asc'],
                    [0, 'asc']
                ],
                columnDefs: [
                    { targets: 0, searchable: false }
                ],
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ people',
                    info: 'Showing _START_ to _END_ of _TOTAL_ people',
                    infoEmpty: 'Nobody to show',
                    zeroRecords: 'Nobody matches that search',
                    emptyTable: 'No personnel to show',
                    paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' }
                }
            });

            // Picking a course shows that track's unit, year in grade, provider and status in the row.
            function setCell(userId, cls, value) {
                const text = value === undefined || value === null ? '' : String(value).trim();
                // Responsive child rows copy cells, so update every copy
                document.querySelectorAll('.' + cls + '[data-user-id="' + userId + '"]').forEach(function (cell) {
                    cell.textContent = text === '' ? '—' : text;
                    cell.classList.toggle('ct-none', text === '');
                });
            }

            function setStatus(userId, status) {
                const text = status === undefined || status === null ? '' : String(status).trim();
                const badge = document.createElement('span');
                if (text === '') {
                    badge.className = 'ct-none';
                    badge.textContent = '—';
                } else if (text.toLowerCase() === 'active') {
                    badge.className = 'pcg-badge pcg-badge--success';
                    badge.textContent = 'Active';
                } else {
                    badge.className = 'pcg-badge';
                    badge.textContent = text;
                }
                document.querySelectorAll('.status-display[data-user-id="' + userId + '"]').forEach(function (cell) {
                    cell.replaceChildren(badge.cloneNode(true));
                });
            }

            $(document).on('change', '.course-training-select', function () {
                const userId = this.dataset.userId;
                const option = this.options[this.selectedIndex];
                const picked = option && option.value ? option.dataset : {};

                setCell(userId, 'division-display', picked.division);
                setCell(userId, 'year-display', picked.year);
                setCell(userId, 'provider-display', picked.provider);
                setStatus(userId, picked.status);
            });
        });
    </script>
@endsection
