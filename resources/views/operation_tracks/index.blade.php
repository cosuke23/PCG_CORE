@extends('layouts.admin')

@section('title', 'Operational Course Track')

@section('styles')
    {{-- DataTables CSS --}}
    <link
        rel="stylesheet"
        href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">

    {{-- DataTables Responsive CSS --}}
    <link
        rel="stylesheet"
        href="{{ asset('vendor/datatables/responsive.dataTables-3.0.5.min.css') }}">

    <style>
        /* =========================
           DataTables Custom Styling
        ========================= */

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

        .dt-length label {
            color: #475569;
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
    </style>
    
@endsection

@section('content')
    <div class="flex min-h-screen">


        {{-- Sidebar: provided by layouts/admin --}}


        {{-- ================= MAIN CONTENT ================= --}}
        <main class="flex-1 overflow-y-auto">

            <div class="p-6 lg:p-8">


                {{-- ================= PAGE HEADER ================= --}}
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">

                    <div>

                        <h1 class="text-2xl font-bold text-slate-800">

                            <i class="fas fa-ship text-blue-600 mr-2"></i>

                            Operational Course Track

                        </h1>

                        <p class="text-slate-500 mt-1">

                            Manage operational course and training requirements based on personnel rank and unit.

                        </p>

                    </div>

                </div>


                {{-- ================= SUMMARY CARDS ================= --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">


                    {{-- Total Operational Tracks --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">

                        <div class="flex items-center justify-between">

                            <div>

                                <p class="text-sm text-slate-500">
                                    Total Operational Tracks
                                </p>

                                <p class="text-2xl font-bold text-slate-800 mt-1">

                                    {{ $operationTracks->count() }}

                                </p>

                            </div>


                            <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">

                                <i class="fas fa-ship text-blue-600 text-xl"></i>

                            </div>

                        </div>

                    </div>


                    {{-- Ranks Covered --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">

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


                            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">

                                <i class="fas fa-user-shield text-green-600 text-xl"></i>

                            </div>

                        </div>

                    </div>


                    {{-- Providers --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">

                        <div class="flex items-center justify-between">

                            <div>

                                <p class="text-sm text-slate-500">
                                    Providers
                                </p>

                                <p class="text-2xl font-bold text-slate-800 mt-1">

                                    {{
                                        $operationTracks
                                            ->pluck('provider')
                                            ->filter()
                                            ->unique()
                                            ->count()
                                    }}

                                </p>

                            </div>


                            <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">

                                <i class="fas fa-building text-purple-600 text-xl"></i>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- ================= OPERATIONAL COURSE TABLE ================= --}}
                <div class="bg-white rounded-xl shadow-sm border border-slate-200">


                    {{-- Table Header --}}
                    <div class="px-6 py-4 bg-slate-900 rounded-t-xl">

                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">

                            <div>

                                <h2 class="text-lg font-semibold text-white">

                                    <i class="fas fa-ship mr-2"></i>

                                    Operational Course / Training Tracks

                                </h2>

                                <p class="text-slate-400 text-sm mt-1">

                                    Operational training requirements by division, unit, and rank

                                </p>

                            </div>

                        </div>

                    </div>


                    {{-- Table Body --}}
                    <div class="p-6">

                        @if($operationTracks->count() > 0)

                        <div class="overflow-x-auto">

                            <table
                                id="operationTracksTable"
                                class="display nowrap w-full text-sm">

                                <thead>

                                    <tr>

                                        <th>No.</th>

                                        <th>Division / Unit</th>

                                        <th>Rank</th>

                                        <th>Year in Grade</th>

                                        <th>Course / Training</th>

                                        <th>Provider</th>

                                        <th>Status</th>

                                        <th>Remarks</th>

                                    </tr>

                                </thead>


                                <tbody>

                                    @foreach($operationTracks as $track)

                                    <tr>


                                        {{-- No. --}}
                                        <td>

                                            {{ $track->no ?? $loop->iteration }}

                                        </td>


                                        {{-- Division / Unit --}}
                                        <td>

                                            {{ $track->division_unit ?? 'N/A' }}

                                        </td>


                                        {{-- Rank --}}
                                        <td>

                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-200 text-slate-800">

                                                {{ $track->rank ?? 'N/A' }}

                                            </span>

                                        </td>


                                        {{-- Year in Grade --}}
                                        <td>

                                            {{ $track->year_in_grade ?? 'N/A' }}

                                        </td>


                                        {{-- Course / Training --}}
                                        <td>

                                            <strong class="text-slate-800">

                                                {{ $track->course_training ?? 'N/A' }}

                                            </strong>

                                        </td>


                                        {{-- Provider --}}
                                        <td>

                                            {{ $track->provider ?? 'N/A' }}

                                        </td>


                                        {{-- Status --}}
                                        <td>

                                            @php

                                            $status = strtolower(
                                            trim($track->status ?? '')
                                            );

                                            @endphp


                                            @if($status === 'active')

                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">

                                                <span
                                                    class="w-2 h-2 bg-green-500 rounded-full mr-2">
                                                </span>

                                                Active

                                            </span>


                                            @elseif($status === 'inactive')

                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">

                                                <span
                                                    class="w-2 h-2 bg-red-500 rounded-full mr-2">
                                                </span>

                                                Inactive

                                            </span>


                                            @else

                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">

                                                {{ $track->status ?? 'N/A' }}

                                            </span>

                                            @endif

                                        </td>


                                        {{-- Remarks --}}
                                        <td>

                                            {{ $track->remarks ?? 'N/A' }}

                                        </td>


                                    </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>


                        @else

                        {{-- No Data --}}
                        <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-5 py-4">

                            <div class="flex items-start">

                                <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>

                                <div>

                                    <p class="font-semibold text-yellow-800">

                                        No Operational Course Track Found

                                    </p>

                                    <p class="text-sm text-yellow-700 mt-1">

                                        There are currently no operational
                                        course or training records.

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


    {{-- ================= JAVASCRIPT ================= --}}

    {{-- jQuery --}}
    <script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>


    {{-- DataTables --}}
    <script
        src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}">
    </script>


    {{-- DataTables Responsive --}}
    <script
        src="{{ asset('vendor/datatables/dataTables.responsive-3.0.5.min.js') }}">
    </script>


    <script>
        $(document).ready(function() {

            /*
            |--------------------------------------------------------------------------
            | Operational Tracks DataTable
            |--------------------------------------------------------------------------
            */

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

                    zeroRecords: "No matching operational tracks found",

                    emptyTable: "No operational tracks available",

                    paginate: {

                        first: "First",

                        last: "Last",

                        next: "Next",

                        previous: "Previous"

                    }

                }

            });

        });
    </script>
@endsection
