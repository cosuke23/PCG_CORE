@extends('layouts.admin')

@section('title', 'Personnel Operational Training')

@section('styles')
    {{-- =========================================================
         TAILWIND CSS
    ========================================================== --}}

    {{-- =========================================================
         FONT AWESOME
    ========================================================== --}}

    {{-- =========================================================
         DATATABLES CSS
    ========================================================== --}}

    <link
        rel="stylesheet"
        href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">

    {{-- =========================================================
         DATATABLES RESPONSIVE CSS
    ========================================================== --}}

    <link
        rel="stylesheet"
        href="{{ asset('vendor/datatables/responsive.dataTables-3.0.5.min.css') }}">

    <style>
        /* =========================================================
           DATATABLES CUSTOM STYLING
        ========================================================= */

        .dt-container {
            width: 100%;
        }

        .dt-search {
            margin-bottom: 15px;
        }

        .dt-search label {
            font-weight: 600;
            color: #475569;
        }

        .dt-search input {
            margin-left: 8px !important;
            padding: 8px 12px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            outline: none !important;
        }

        .dt-search input:focus {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }

        .dt-length {
            margin-bottom: 15px;
        }

        .dt-length select {
            padding: 6px 30px 6px 10px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
        }

        table.dataTable thead th {
            white-space: nowrap;
        }

        table.dataTable tbody td {
            vertical-align: middle;
        }

        .dt-info {
            color: #64748b;
            font-size: 14px;
        }

        .dt-paging-button {
            border-radius: 6px !important;
        }

        .dt-paging-button.current {
            background: #2563eb !important;
            color: white !important;
            border: 1px solid #2563eb !important;
        }

        .dt-paging-button:hover {
            background: #dbeafe !important;
            color: #1d4ed8 !important;
        }

        /* =========================================================
           COURSE DROPDOWN
        ========================================================== */

        .course-training-select {
            min-width: 260px;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background-color: white;
            color: #334155;
            font-size: 14px;
        }

        .course-training-select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }

        .course-training-select option {
            padding: 8px;
        }
    </style>
@endsection

@section('content')
    <div class="flex min-h-screen">


        {{-- =========================================================
         SIDEBAR
    ========================================================== --}}

        {{-- Sidebar: provided by layouts/admin --}}

        {{-- =========================================================
         MAIN CONTENT
    ========================================================== --}}

        <main class="flex-1 overflow-y-auto">


            <div class="p-6 lg:p-8">


                {{-- =================================================
                 PAGE HEADER
            ================================================== --}}

                <div class="mb-6">

                    <h1 class="text-2xl font-bold text-slate-800">

                        <i class="fas fa-ship text-blue-600 mr-2"></i>

                        Personnel Operational Training

                    </h1>


                    <p class="text-slate-500 mt-1">

                        View operational course and training requirements
                        based on personnel rank.

                    </p>

                </div>



                {{-- =================================================
                 SUMMARY CARDS
            ================================================== --}}

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">


                    {{-- Personnel --}}

                    <div
                        class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">

                        <div class="flex items-center justify-between">

                            <div>

                                <p class="text-sm text-slate-500">
                                    Personnel
                                </p>

                                <p class="text-2xl font-bold text-slate-800 mt-1">

                                    {{ $personnelTraining->count() }}

                                </p>

                            </div>


                            <div
                                class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">

                                <i class="fas fa-users text-blue-600 text-xl"></i>

                            </div>

                        </div>

                    </div>



                    {{-- Operational Tracks --}}

                    <div
                        class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">

                        <div class="flex items-center justify-between">

                            <div>

                                <p class="text-sm text-slate-500">
                                    Operational Tracks
                                </p>

                                <p class="text-2xl font-bold text-slate-800 mt-1">

                                    {{ $operationTracks->count() }}

                                </p>

                            </div>


                            <div
                                class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">

                                <i class="fas fa-ship text-green-600 text-xl"></i>

                            </div>

                        </div>

                    </div>



                    {{-- Ranks Covered --}}

                    <div
                        class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">

                        <div class="flex items-center justify-between">

                            <div>

                                <p class="text-sm text-slate-500">
                                    Ranks Covered
                                </p>

                                <p class="text-2xl font-bold text-slate-800 mt-1">

                                    {{
                                    $operationTracks
                                        ->pluck('rank')
                                        ->filter()
                                        ->unique()
                                        ->count()
                                }}

                                </p>

                            </div>


                            <div
                                class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">

                                <i class="fas fa-user-shield text-purple-600 text-xl"></i>

                            </div>

                        </div>

                    </div>

                </div>



                {{-- =================================================
                 PERSONNEL OPERATIONAL TRAINING TABLE
            ================================================== --}}

                <div
                    class="bg-white rounded-xl shadow-sm border border-slate-200">


                    {{-- Table Header --}}

                    <div
                        class="px-6 py-4 bg-slate-900 rounded-t-xl">

                        <h2 class="text-lg font-semibold text-white">

                            <i class="fas fa-user-graduate mr-2"></i>

                            Personnel Operational Training

                        </h2>


                        <p class="text-slate-400 text-sm mt-1">

                            Select an operational course based on the
                            personnel's rank.

                        </p>

                    </div>



                    {{-- Table Body --}}

                    <div class="p-6">


                        @if($personnelTraining->count() > 0)


                        <div class="overflow-x-auto">


                            <table
                                id="personnelOperationalTrainingTable"
                                class="display nowrap w-full text-sm">


                                <thead>

                                    <tr>

                                        <th>No.</th>

                                        <th>Personnel</th>

                                        <th>Rank</th>

                                        <th>Division / Unit</th>

                                        <th>Year in Grade</th>

                                        <th>Course / Training</th>

                                        <th>Provider</th>

                                        <th>Status</th>

                                    </tr>

                                </thead>


                                <tbody>


                                    @foreach($personnelTraining as $personnel)

                                    @php

                                    $user = $personnel['user'];

                                    $tracks = $personnel['tracks'];

                                    /*
                                    |--------------------------------------------------------------------------
                                    | Select first track as default
                                    |--------------------------------------------------------------------------
                                    */

                                    $defaultTrack = $tracks->first();

                                    @endphp


                                    <tr>


                                        {{-- =================================================
                                         NO.
                                    ================================================== --}}

                                        <td>

                                            {{ $loop->iteration }}

                                        </td>



                                        {{-- =================================================
                                         PERSONNEL
                                    ================================================== --}}

                                        <td>

                                            <div>

                                                <strong class="text-slate-800">

                                                    {{ $user->name ?? 'N/A' }}

                                                </strong>


                                                @if(!empty($user->serial_number))

                                                <div class="text-xs text-slate-500 mt-1">

                                                    <i class="fas fa-id-card mr-1"></i>

                                                    {{ $user->serial_number }}

                                                </div>

                                                @endif

                                            </div>

                                        </td>



                                        {{-- =================================================
                                         RANK
                                    ================================================== --}}

                                        <td>

                                            <span
                                                class="inline-flex items-center px-2.5 py-1
                                                   rounded-full text-xs font-semibold
                                                   bg-slate-200 text-slate-800">

                                                {{ $user->rank ?? 'N/A' }}

                                            </span>

                                        </td>



                                        {{-- =================================================
                                         DIVISION / UNIT
                                    ================================================== --}}

                                        <td>

                                            <span
                                                class="division-display"
                                                data-user-id="{{ $user->id }}">

                                                {{ $defaultTrack->division_unit ?? 'N/A' }}

                                            </span>

                                        </td>



                                        {{-- =================================================
                                         YEAR IN GRADE
                                    ================================================== --}}

                                        <td>

                                            <span
                                                class="year-display"
                                                data-user-id="{{ $user->id }}">

                                                {{ $defaultTrack->year_in_grade ?? 'N/A' }}

                                            </span>

                                        </td>



                                        {{-- =================================================
                                         COURSE / TRAINING DROPDOWN
                                    ================================================== --}}

                                        <td>

                                            @if($tracks->count() > 0)


                                            <select
                                                name="course_training[{{ $user->id }}]"
                                                class="course-training-select"
                                                data-user-id="{{ $user->id }}">


                                                <option value="">

                                                    -- Select Course / Training --

                                                </option>


                                                @foreach($tracks as $track)


                                                <option
                                                    value="{{ $track->id }}"

                                                    data-division="{{ $track->division_unit ?? '' }}"

                                                    data-year="{{ $track->year_in_grade ?? '' }}"

                                                    data-provider="{{ $track->provider ?? '' }}"

                                                    data-status="{{ $track->status ?? '' }}"

                                                    {{ $defaultTrack && $defaultTrack->id == $track->id ? 'selected' : '' }}>

                                                    {{ $track->course_training ?? 'N/A' }}

                                                </option>


                                                @endforeach


                                            </select>


                                            @else


                                            <span
                                                class="inline-flex items-center
                                                       px-2.5 py-1 rounded-lg
                                                       text-xs font-semibold
                                                       text-yellow-700
                                                       bg-yellow-50
                                                       border border-yellow-200">

                                                <i
                                                    class="fas fa-exclamation-triangle mr-2">
                                                </i>

                                                No training requirement

                                            </span>


                                            @endif

                                        </td>



                                        {{-- =================================================
                                         PROVIDER
                                    ================================================== --}}

                                        <td>

                                            <span
                                                class="provider-display"
                                                data-user-id="{{ $user->id }}">

                                                {{ $defaultTrack->provider ?? 'N/A' }}

                                            </span>

                                        </td>



                                        {{-- =================================================
                                         STATUS
                                    ================================================== --}}

                                        <td>

                                            <span
                                                class="status-display"
                                                data-user-id="{{ $user->id }}">


                                                @php

                                                $status = strtolower(
                                                trim(
                                                $defaultTrack->status ?? ''
                                                )
                                                );

                                                @endphp


                                                @if($status === 'active')


                                                <span
                                                    class="inline-flex items-center
                                                           px-2.5 py-1 rounded-full
                                                           text-xs font-semibold
                                                           bg-green-100 text-green-700">

                                                    <span
                                                        class="w-2 h-2
                                                               bg-green-500
                                                               rounded-full mr-2">
                                                    </span>

                                                    Active

                                                </span>


                                                @elseif($status === 'inactive')


                                                <span
                                                    class="inline-flex items-center
                                                           px-2.5 py-1 rounded-full
                                                           text-xs font-semibold
                                                           bg-red-100 text-red-700">

                                                    <span
                                                        class="w-2 h-2
                                                               bg-red-500
                                                               rounded-full mr-2">
                                                    </span>

                                                    Inactive

                                                </span>


                                                @elseif($defaultTrack)


                                                <span
                                                    class="inline-flex items-center
                                                           px-2.5 py-1 rounded-full
                                                           text-xs font-semibold
                                                           bg-slate-100 text-slate-600">

                                                    {{ $defaultTrack->status ?? 'N/A' }}

                                                </span>


                                                @else


                                                <span
                                                    class="inline-flex items-center
                                                           px-2.5 py-1 rounded-full
                                                           text-xs font-semibold
                                                           bg-slate-100 text-slate-600">

                                                    Not Available

                                                </span>


                                                @endif


                                            </span>

                                        </td>

                                    </tr>


                                    @endforeach


                                </tbody>

                            </table>

                        </div>


                        @else


                        {{-- =================================================
                         NO PERSONNEL
                    ================================================== --}}

                        <div
                            class="rounded-lg border border-yellow-200
                               bg-yellow-50 px-5 py-4">

                            <div class="flex items-start">

                                <i
                                    class="fas fa-exclamation-triangle
                                       text-yellow-600 mt-1 mr-3">
                                </i>


                                <div>

                                    <p class="font-semibold text-yellow-800">

                                        No Personnel Found

                                    </p>


                                    <p class="text-sm text-yellow-700 mt-1">

                                        There are currently no personnel
                                        available for operational training
                                        evaluation.

                                    </p>

                                </div>

                            </div>

                        </div>


                        @endif


                    </div>

                </div>

            </div>

        </main>

    </div>



    {{-- =========================================================
     JAVASCRIPT
========================================================== --}}

    <script
        src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}">
    </script>


    <script
        src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}">
    </script>


    <script
        src="{{ asset('vendor/datatables/dataTables.responsive-3.0.5.min.js') }}">
    </script>



    <script nonce="{{ $cspNonce ?? '' }}">
        $(document).ready(function() {


            /* =========================================================
               DATATABLE
            ========================================================== */

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

                    {
                        targets: 0,
                        searchable: false
                    }

                ],

                language: {

                    search: "Search:",

                    lengthMenu: "Show _MENU_ entries",

                    info: "Showing _START_ to _END_ of _TOTAL_ records",

                    infoEmpty: "Showing 0 to 0 of 0 records",

                    zeroRecords: "No matching personnel operational training found",

                    emptyTable: "No personnel operational training available",

                    paginate: {

                        first: "First",

                        last: "Last",

                        next: "Next",

                        previous: "Previous"

                    }

                }

            });



            /* =========================================================
               COURSE DROPDOWN CHANGE
            ========================================================== */

            $(document).on(
                'change',
                '.course-training-select',
                function() {


                    const select = $(this);


                    const userId =
                        select.data('user-id');


                    const selectedOption =
                        select.find('option:selected');


                    /* -----------------------------------------------------
                       No course selected
                    ----------------------------------------------------- */

                    if (!selectedOption.val()) {

                        $('[data-user-id="' + userId + '"].division-display')
                            .text('N/A');

                        $('[data-user-id="' + userId + '"].year-display')
                            .text('N/A');

                        $('[data-user-id="' + userId + '"].provider-display')
                            .text('N/A');

                        $('[data-user-id="' + userId + '"].status-display')
                            .html(`
                        <span
                            class="inline-flex items-center
                                   px-2.5 py-1 rounded-full
                                   text-xs font-semibold
                                   bg-slate-100 text-slate-600">

                            Not Available

                        </span>
                    `);

                        return;

                    }


                    /* -----------------------------------------------------
                       Get selected course information
                    ----------------------------------------------------- */

                    const division =
                        selectedOption.data('division') || 'N/A';


                    const year =
                        selectedOption.data('year') || 'N/A';


                    const provider =
                        selectedOption.data('provider') || 'N/A';


                    const status =
                        selectedOption.data('status') || 'N/A';



                    /* -----------------------------------------------------
                       Update Division / Unit
                    ----------------------------------------------------- */

                    $('[data-user-id="' + userId + '"].division-display')
                        .text(division);



                    /* -----------------------------------------------------
                       Update Year in Grade
                    ----------------------------------------------------- */

                    $('[data-user-id="' + userId + '"].year-display')
                        .text(year);



                    /* -----------------------------------------------------
                       Update Provider
                    ----------------------------------------------------- */

                    $('[data-user-id="' + userId + '"].provider-display')
                        .text(provider);



                    /* -----------------------------------------------------
                       Update Status
                    ----------------------------------------------------- */

                    let statusHtml = '';


                    if (status.toLowerCase() === 'active') {


                        statusHtml = `

                    <span
                        class="inline-flex items-center
                               px-2.5 py-1 rounded-full
                               text-xs font-semibold
                               bg-green-100 text-green-700">

                        <span
                            class="w-2 h-2 bg-green-500
                                   rounded-full mr-2">
                        </span>

                        Active

                    </span>

                `;


                    } else if (status.toLowerCase() === 'inactive') {


                        statusHtml = `

                    <span
                        class="inline-flex items-center
                               px-2.5 py-1 rounded-full
                               text-xs font-semibold
                               bg-red-100 text-red-700">

                        <span
                            class="w-2 h-2 bg-red-500
                                   rounded-full mr-2">
                        </span>

                        Inactive

                    </span>

                `;


                    } else {


                        statusHtml = `

                    <span
                        class="inline-flex items-center
                               px-2.5 py-1 rounded-full
                               text-xs font-semibold
                               bg-slate-100 text-slate-600">

                        ${status}

                    </span>

                `;

                    }


                    $('[data-user-id="' + userId + '"].status-display')
                        .html(statusHtml);


                }
            );


        });
    </script>
@endsection
