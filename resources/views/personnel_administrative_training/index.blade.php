@extends('layouts.admin')

@section('title', 'Personnel Administrative Training')

@php
    $personCount = $personnelTraining->count();
    $optionCount = $personnelTraining->sum(fn ($person) => $person->trainings->count());
    $rankCount = $personnelTraining->pluck('user_rank')->filter()->unique()->count();
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/course-tracks.css') }}">
@endsection

@section('content')
    <div class="pcg-page ct">

        <header class="pcg-page__header">
            <div>
                <h2>{{ number_format($personCount) }} {{ \Illuminate\Support\Str::plural('person', $personCount) }} with administrative courses for their rank</h2>
                <p>Each row lists the administrative courses that match the person's rank. Open the list to see them.</p>
            </div>
            <a class="pcg-btn pcg-btn--secondary" href="{{ route('administrative.tracks') }}">Administrative tracks</a>
        </header>

        <div class="pcg-stats">
            <div class="pcg-stat">
                <span class="pcg-stat__label">Course options</span>
                <span class="pcg-stat__value">{{ number_format($optionCount) }}</span>
                <span class="pcg-stat__hint">across everyone listed</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">Ranks</span>
                <span class="pcg-stat__value">{{ number_format($rankCount) }}</span>
                <span class="pcg-stat__hint">among the people listed</span>
            </div>
        </div>

        <section class="pcg-card ct-card" aria-labelledby="personnel-heading">
            <h3 id="personnel-heading" class="pcg-visually-hidden">Personnel and their administrative courses</h3>

            @if ($personCount > 0)
                <div class="pcg-table-wrap">
                    <table id="personnelTrainingTable" class="display nowrap">
                        <thead>
                            <tr>
                                <th scope="col">No.</th>
                                <th scope="col">Personnel</th>
                                <th scope="col">Rank</th>
                                <th scope="col">Available courses</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($personnelTraining as $person)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><span class="ct-name">{{ $person->name }}</span></td>
                                    <td><span class="ct-rank">{{ $person->user_rank }}</span></td>
                                    <td>
                                        <label class="pcg-visually-hidden" for="admin-course-{{ $person->user_id }}">Administrative courses for {{ $person->name }}</label>
                                        <select class="pcg-input pcg-input--sm ct-select" id="admin-course-{{ $person->user_id }}">
                                            <option value="">Select course / training</option>
                                            @foreach ($person->trainings as $training)
                                                <option value="{{ $training->track_id }}">{{ $training->course_training }}@if ($training->provider) — {{ $training->provider }}@endif</option>
                                            @endforeach
                                        </select>
                                        <span class="ct-count">{{ $person->trainings->count() }} {{ \Illuminate\Support\Str::plural('course', $person->trainings->count()) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="pcg-empty">Nobody matches an administrative track yet. Check that the ranks in <a href="{{ route('administrative.tracks') }}">Administrative tracks</a> match the personnel ranks.</p>
            @endif
        </section>
    </div>

    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}">
        $(document).ready(function () {
            $('#personnelTrainingTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, 'All']
                ],
                order: [
                    [1, 'asc']
                ],
                columnDefs: [
                    { targets: 0, searchable: false, orderable: false }
                ],
                language: {
                    search: 'Search personnel:',
                    lengthMenu: 'Show _MENU_ people',
                    info: 'Showing _START_ to _END_ of _TOTAL_ people',
                    infoEmpty: 'Nobody to show',
                    zeroRecords: 'Nobody matches that search',
                    emptyTable: 'Nobody matches an administrative track yet',
                    paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' }
                }
            });
        });
    </script>
@endsection
