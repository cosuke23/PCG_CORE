@extends('layouts.admin')

@section('title', 'OSAERT Training')

@section('styles')

<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">

<script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>

<script src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>

@endsection

@section('content')

<script src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>

<div class="flex min-h-screen bg-gray-100">

    <div class="flex-shrink-0">
        @include('admin.partials.lms-menu')
    </div>

    <main class="flex-1 p-10 min-w-0">

        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-3xl font-bold text-gray-800">
                    OSAERT
                </h2>
                <p class="text-gray-500 mt-1">
                    Oil Spill Awareness and Emergency Response Training
                </p>
            </div>

            <a href="{{ route('admin.osaert.template') }}"
                class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg shadow transition">
                <i class="fas fa-file-csv mr-2"></i>
                Download CSV Template
            </a>
        </div>

        @if(session('success'))
        <div class="mb-6 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mb-6 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i>
            {{ session('error') }}
        </div>
        @endif

        @if($errors->any())
        <div class="mb-6 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg">
            <div class="font-semibold mb-2">
                Please check the following:
            </div>

            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(session('upload_errors'))
        <div class="mb-6 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg">
            <div class="font-semibold mb-2">
                <i class="fas fa-triangle-exclamation mr-2"></i>
                CSV Upload Errors
            </div>

            <ul class="list-disc list-inside text-sm">
                @foreach(session('upload_errors') as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

            <div class="bg-blue-600 text-white p-5 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">
                            Total Trainees
                        </p>

                        <p class="text-3xl font-bold mt-1">
                            {{ number_format($totalTrainees) }}
                        </p>
                    </div>

                    <i class="fas fa-users text-3xl opacity-80"></i>
                </div>
            </div>

            <div class="bg-green-600 text-white p-5 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">
                            Completed Training
                        </p>

                        <p class="text-3xl font-bold mt-1">
                            {{ number_format($completedTrainees) }}
                        </p>
                    </div>

                    <i class="fas fa-circle-check text-3xl opacity-80"></i>
                </div>
            </div>

            <div class="bg-purple-600 text-white p-5 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-80">
                            Training Records
                        </p>

                        <p class="text-3xl font-bold mt-1">
                            {{ number_format($totalTrainees) }}
                        </p>
                    </div>

                    <i class="fas fa-graduation-cap text-3xl opacity-80"></i>
                </div>
            </div>

        </div>

        <div class="bg-white p-6 rounded-lg shadow mb-6">

            <div class="flex justify-between items-center mb-5">

                <div>
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-file-import mr-2 text-blue-600"></i>
                        Bulk Upload Trainees
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        Upload multiple OSAERT trainees using a CSV file.
                    </p>
                </div>

                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">
                    CSV Import
                </span>

            </div>

            <form action="{{ route('admin.osaert.bulk-upload') }}"
                method="POST"
                enctype="multipart/form-data">

                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div>

                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            CSV File
                        </label>

                        <input type="file"
                            name="file"
                            accept=".csv,.txt"
                            required
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

                        <p class="text-xs text-gray-500 mt-2">
                            Accepted format: CSV or TXT. Maximum file size: 10 MB.
                        </p>

                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">

                        <h4 class="font-semibold text-blue-800 mb-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Required CSV Columns
                        </h4>

                        <div class="grid grid-cols-2 gap-2 text-sm text-blue-700">

                            <div>
                                <i class="fas fa-check mr-1"></i>
                                name
                            </div>

                            <div>
                                <i class="fas fa-check mr-1"></i>
                                company
                            </div>

                            <div>
                                <i class="fas fa-check mr-1"></i>
                                date_attended
                            </div>

                            <div>
                                <i class="fas fa-check mr-1"></i>
                                date_ended
                            </div>

                        </div>

                        <p class="text-xs text-blue-600 mt-3">
                            Date format: DD/MM/YYYY
                        </p>

                    </div>

                </div>

                <div class="flex justify-between items-center mt-6 pt-5 border-t">

                    <a href="{{ route('admin.osaert.template') }}"
                        class="text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-download mr-2"></i>
                        Download CSV Template
                    </a>

                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition">
                        <i class="fas fa-upload mr-2"></i>
                        Upload Trainees
                    </button>

                </div>

            </form>

        </div>

        <div class="bg-white p-6 rounded-lg shadow mb-6">

            <div class="flex justify-between items-center mb-5">

                <div>
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-filter mr-2 text-blue-600"></i>
                        Filter Training Records
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        Filter trainees by training start and completion year.
                    </p>
                </div>

                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">
                    {{ number_format($trainees->count()) }} Results
                </span>

            </div>

            <form method="GET"
                action="{{ route('admin.osaert.index') }}">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <div>

                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Year Started
                        </label>

                        <select name="started_year"
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

                            <option value="">
                                All Years
                            </option>

                            @foreach($startedYears as $year)

                            <option value="{{ $year }}"
                                {{ (string) $startedYear === (string) $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>

                            @endforeach

                        </select>

                    </div>

                    <div>

                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Year Ended
                        </label>

                        <select name="ended_year"
                            class="w-full border border-gray-300 rounded-lg px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

                            <option value="">
                                All Years
                            </option>

                            @foreach($endedYears as $year)

                            <option value="{{ $year }}"
                                {{ (string) $endedYear === (string) $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>

                            @endforeach

                        </select>

                    </div>

                    <div class="flex items-end gap-2">

                        <button type="submit"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg shadow transition">
                            <i class="fas fa-filter mr-2"></i>
                            Apply Filter
                        </button>

                        <a href="{{ route('admin.osaert.index') }}"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-3 rounded-lg shadow transition">
                            <i class="fas fa-rotate-left"></i>
                        </a>

                    </div>

                </div>

            </form>

        </div>

        <div class="bg-white p-6 rounded-lg shadow">

            <div class="flex justify-between items-center mb-4">

                <div>
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-users mr-2 text-blue-600"></i>
                        OSAERT Trainee List
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        List of registered Oil Spill Awareness and Emergency Response Training participants.
                    </p>
                </div>

                <div class="flex items-center gap-3">

                    <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">
                        {{ number_format($trainees->count()) }} Trainees
                    </span>

                    @if($trainees->count() > 0)

                    <button type="button"
                        id="generateCertificatesBtn"
                        class="inline-flex items-center bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg shadow transition font-semibold">
                        <i class="fas fa-certificate mr-2"></i>
                        Generate Certificates
                        <span class="ml-2 bg-white bg-opacity-20 px-2 py-0.5 rounded-full text-xs">
                            {{ $trainees->whereNotNull('date_ended')->where('date_ended', '!=', '')->count() }}
                        </span>
                    </button>

                    @endif

                </div>

            </div>

            <div class="mb-4 bg-purple-50 border border-purple-200 rounded-lg px-4 py-3">

                <div class="flex items-center text-purple-800 text-sm">

                    <i class="fas fa-info-circle mr-2"></i>

                    <span>
                        The Generate Certificates button will generate certificates for the
                        completed trainees currently displayed by the selected filters.
                    </span>

                </div>

            </div>

            <div class="overflow-x-auto">

                <table id="osaertTraineesTable"
                    class="w-full">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Trainee Name</th>
                            <th>Company</th>
                            <th>Date Started</th>
                            <th>Date Ended</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($trainees as $trainee)

                        <tr>

                            <td>
                                {{ $loop->iteration }}
                            </td>

                            <td>

                                <div class="flex items-center">

                                    <div class="w-9 h-9 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-user"></i>
                                    </div>

                                    <div>

                                        <div class="font-semibold text-gray-800">
                                            {{ $trainee->name }}
                                        </div>

                                    </div>

                                </div>

                            </td>

                            <td>

                                <span class="text-gray-700">
                                    {{ $trainee->company }}
                                </span>

                            </td>

                            <td>

                                @if($trainee->date_attended)

                                <span class="inline-flex items-center bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">

                                    <i class="fas fa-calendar-check mr-2"></i>

                                    {{ $trainee->date_attended }}

                                </span>

                                @else

                                <span class="text-gray-400">
                                    —
                                </span>

                                @endif

                            </td>

                            <td>

                                @if($trainee->date_ended)

                                <span class="inline-flex items-center bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">

                                    <i class="fas fa-calendar-day mr-2"></i>

                                    {{ $trainee->date_ended }}

                                </span>

                                @else

                                <span class="text-gray-400">
                                    —
                                </span>

                                @endif

                            </td>

                            <td>

                                @if($trainee->date_ended)

                                <span class="inline-flex items-center bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">

                                    <i class="fas fa-check-circle mr-2"></i>
                                    Completed

                                </span>

                                @elseif($trainee->date_attended)

                                <span class="inline-flex items-center bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-sm font-semibold">

                                    <i class="fas fa-clock mr-2"></i>
                                    In Training

                                </span>

                                @else

                                <span class="inline-flex items-center bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-semibold">

                                    <i class="fas fa-minus-circle mr-2"></i>
                                    Pending

                                </span>

                                @endif

                            </td>

                        </tr>

                        @empty

                        <tr>

                            <td colspan="6"
                                class="text-center py-8 text-gray-500">

                                <i class="fas fa-users text-4xl mb-3 block text-gray-300"></i>

                                No OSAERT trainees found.

                            </td>

                        </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>
<script src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>
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
            search: 'Search Trainees:',
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

    $('#generateCertificatesBtn').on('click', function () {

        const startedYear = @json($startedYear);
        const endedYear = @json($endedYear);
        const certificateCount = @json($completedFilteredTrainees);

        if (certificateCount <= 0) {

            Swal.fire({
                icon: 'warning',
                title: 'No Completed Trainees',
                text: 'There are no completed trainees in the current filtered results.',
                confirmButtonColor: '#7e22ce'
            });

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

        Swal.fire({
            title: 'Generate Certificates?',
            html:
                '<div class="text-gray-600">' +
                'This will generate certificates for ' +
                '<strong>' + certificateCount + '</strong>' +
                ' completed trainee(s).' +
                '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-certificate mr-2"></i> Generate',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#7e22ce',
            cancelButtonColor: '#6b7280'
        }).then(function (result) {

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
x

@endsection