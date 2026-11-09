@extends('layouts.admin')

@section('title', 'OSAERT Training')

@php
    $listCount = $trainees->count();
    $notCompleted = max(0, $totalTrainees - $completedTrainees);
    $filtered = (string) $startedYear !== '' || (string) $endedYear !== '';

    $headline = $totalTrainees > 0
        ? number_format($totalTrainees) . ' OSAERT ' . \Illuminate\Support\Str::plural('trainee', $totalTrainees) . ', ' . number_format($completedTrainees) . ' completed'
        : 'No OSAERT trainees yet';

    $scope = [];
    if ((string) $startedYear !== '') {
        $scope[] = 'started in ' . $startedYear;
    }
    if ((string) $endedYear !== '') {
        $scope[] = 'finished in ' . $endedYear;
    }
    $listTitle = number_format($listCount) . ' ' . \Illuminate\Support\Str::plural('trainee', $listCount)
        . ($scope ? ' who ' . implode(' and ', $scope) : ', newest first');

    $certLabel = $completedFilteredTrainees > 0
        ? 'Generate ' . number_format($completedFilteredTrainees) . ' ' . \Illuminate\Support\Str::plural('certificate', $completedFilteredTrainees)
        : 'Generate certificates';
@endphp

@section('styles')
<link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">
<link rel="stylesheet" href="{{ \App\Support\Asset::v('css/osaert.css') }}">
<script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
<script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
@endsection

@section('content')
<div class="pcg-page pcg-osaert">

    <header class="pcg-page__header pcg-osaert__head">
        <div>
            <h2>{{ $headline }}</h2>
            <p>
                Oil Spill Awareness and Emergency Response Training.
                @if ($notCompleted > 0)
                    {{ number_format($notCompleted) }} {{ $notCompleted === 1 ? 'has' : 'have' }} no end date yet.
                @endif
            </p>
        </div>
    </header>

    @if (session('upload_errors'))
        <div class="pcg-alert pcg-alert--danger" role="alert">
            <strong>The CSV was not imported. Fix these rows and upload it again:</strong>
            <ul>
                @foreach (session('upload_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="pcg-card" aria-labelledby="osaert-list-heading">
        <div class="pcg-card__header pcg-osaert__listhead">
            <div>
                <h3 id="osaert-list-heading">{{ $listTitle }}</h3>
                @if ($listCount > 0)
                    <p>
                        {{ number_format($completedFilteredTrainees) }} of them completed the training and can get a certificate.
                    </p>
                @endif
            </div>

            @if ($listCount > 0)
                <button type="button"
                    id="generateCertificatesBtn"
                    class="pcg-btn pcg-btn--cta">
                    <i class="fa-solid fa-certificate" aria-hidden="true"></i>
                    {{ $certLabel }}
                </button>
            @endif
        </div>

        <form method="GET"
            action="{{ route('admin.osaert.index') }}"
            class="pcg-osaert__filters">

            <div class="pcg-field">
                <label class="pcg-label" for="osaert-started-year">Started in</label>
                <select class="pcg-input" id="osaert-started-year" name="started_year">
                    <option value="">Any year</option>
                    @foreach ($startedYears as $year)
                        <option value="{{ $year }}" @selected((string) $startedYear === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pcg-field">
                <label class="pcg-label" for="osaert-ended-year">Finished in</label>
                <select class="pcg-input" id="osaert-ended-year" name="ended_year">
                    <option value="">Any year</option>
                    @foreach ($endedYears as $year)
                        <option value="{{ $year }}" @selected((string) $endedYear === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pcg-osaert__filter-actions">
                <button type="submit" class="pcg-btn pcg-btn--secondary">Apply</button>
                @if ($filtered)
                    <a href="{{ route('admin.osaert.index') }}" class="pcg-btn pcg-btn--ghost">Clear</a>
                @endif
            </div>
        </form>

        @if ($listCount === 0)
            <p class="pcg-empty">
                @if ($filtered)
                    No trainees match these years. <a href="{{ route('admin.osaert.index') }}">Show all trainees</a>.
                @else
                    No trainees yet. Upload a CSV file below to add them.
                @endif
            </p>
        @else
            <div class="pcg-card__body">
                <div class="pcg-table-wrap">
                    <table id="osaertTraineesTable" class="pcg-table pcg-osaert__table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Trainee</th>
                                <th scope="col">Company</th>
                                <th scope="col">Started</th>
                                <th scope="col">Finished</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trainees as $trainee)
                                <tr>
                                    <td class="pcg-num">{{ $loop->iteration }}</td>
                                    <td><strong>{{ $trainee->name }}</strong></td>
                                    <td>{{ $trainee->company ?: '—' }}</td>
                                    <td class="pcg-nowrap pcg-num">{{ $trainee->date_attended ?: '—' }}</td>
                                    <td class="pcg-nowrap pcg-num">{{ $trainee->date_ended ?: '—' }}</td>
                                    <td>
                                        @if ($trainee->date_ended)
                                            <span class="pcg-badge pcg-badge--success"><i class="fa-solid fa-check" aria-hidden="true"></i>&nbsp;Completed</span>
                                        @elseif ($trainee->date_attended)
                                            <span class="pcg-badge">In training</span>
                                        @else
                                            <span class="pcg-badge">Not started</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    <section class="pcg-card pcg-osaert__upload {{ $totalTrainees === 0 ? 'pcg-osaert__upload--first' : '' }}" aria-labelledby="osaert-upload-heading">
        <div class="pcg-card__header">
            <h3 id="osaert-upload-heading">Add trainees from a CSV file</h3>
        </div>

        <form action="{{ route('admin.osaert.bulk-upload') }}"
            method="POST"
            enctype="multipart/form-data"
            class="pcg-card__body">
            @csrf

            <div class="pcg-osaert__upload-grid">
                <div class="pcg-field">
                    <label class="pcg-label" for="osaert-file">CSV file</label>
                    <input type="file"
                        id="osaert-file"
                        name="file"
                        accept=".csv,.txt"
                        required
                        class="pcg-input">
                    <p class="pcg-help">CSV or TXT, up to 10 MB.</p>
                </div>

                <div class="pcg-osaert__columns">
                    <p>
                        The first row must name these columns:
                        <span class="pcg-mono">name</span>,
                        <span class="pcg-mono">company</span>,
                        <span class="pcg-mono">date_attended</span>,
                        <span class="pcg-mono">date_ended</span>.
                        Write dates as DD/MM/YYYY.
                    </p>
                    <a href="{{ route('admin.osaert.template') }}" class="pcg-btn pcg-btn--ghost pcg-btn--sm">
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        Download the CSV template
                    </a>
                </div>
            </div>

            <div class="pcg-osaert__upload-actions">
                <button type="submit" class="pcg-btn {{ $totalTrainees === 0 ? 'pcg-btn--cta' : 'pcg-btn--secondary' }}">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i>
                    Upload trainees
                </button>
            </div>
        </form>
    </section>

</div>

<script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>
<script nonce="{{ $cspNonce ?? '' }}">
$(document).ready(function () {

    $('#osaertTraineesTable').DataTable({
        pageLength: 10,
        lengthMenu: [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, 'All']
        ],
        order: [
            [0, 'asc']
        ],
        language: {
            search: 'Search trainees:',
            searchPlaceholder: 'Name, company...',
            lengthMenu: 'Show _MENU_ trainees',
            info: 'Showing _START_ to _END_ of _TOTAL_ trainees',
            emptyTable: 'No OSAERT trainees found'
        },
        responsive: true,
        autoWidth: false,
        columnDefs: [
            {
                targets: '_all',
                className: 'align-middle'
            }
        ],
        dom:
            '<"flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4"' +
                '<"flex items-center"l>' +
                '<"flex items-center"f>' +
            '>' +
            'rt' +
            '<"flex flex-col md:flex-row md:items-center md:justify-between gap-4 mt-4"' +
                '<"text-sm"i>' +
                '<"flex justify-end"p>' +
            '>'
    });

    // dialogs use the design-system buttons and the console theme
    const swalTheme = {
        customClass: {
            popup: 'osaert-swal',
            confirmButton: 'pcg-btn pcg-btn--primary',
            cancelButton: 'pcg-btn pcg-btn--secondary'
        },
        buttonsStyling: false
    };

    $('#generateCertificatesBtn').on('click', function () {

        const startedYear = @json($startedYear);
        const endedYear = @json($endedYear);
        const certificateCount = @json($completedFilteredTrainees);

        if (certificateCount <= 0) {

            Swal.fire(Object.assign({
                icon: 'warning',
                title: 'No completed trainees',
                text: 'Nobody in this list has an end date yet, so there is no certificate to generate.'
            }, swalTheme));

            return;
        }

        const url = new URL(
            @json(route('admin.osaert.certificates')),
            window.location.origin
        );

        if (startedYear) {
            url.searchParams.set('started_year', startedYear);
        }

        if (endedYear) {
            url.searchParams.set('ended_year', endedYear);
        }

        Swal.fire(Object.assign({
            title: 'Generate certificates?',
            text: 'This opens ' + Number(certificateCount) + ' certificate' + (Number(certificateCount) === 1 ? '' : 's') +
                ' for the completed trainees in this list, in a new tab.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Generate',
            cancelButtonText: 'Cancel'
        }, swalTheme)).then(function (result) {

            if (result.isConfirmed) {

                window.open(
                    url.toString(),
                    '_blank'
                );

            }

        });

    });

});
</script>
@endsection
