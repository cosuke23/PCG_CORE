<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Personnel Administrative Training | MEPCOM Admin</title>


    {{-- Tailwind --}}
    <script src="https://cdn.tailwindcss.com"></script>


    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    {{-- DataTables --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">


    <style>
        .dt-container {
            width: 100%;
        }

        .dt-search input {
            padding: 8px 12px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
        }

        .dt-length select {
            padding: 6px 30px 6px 10px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
        }

        table.dataTable thead th {
            white-space: nowrap;
        }
    </style>

</head>


<body class="bg-slate-100 min-h-screen">


    <div class="flex min-h-screen">


        {{-- ========================================================= --}}
        {{-- SIDEBAR --}}
        {{-- ========================================================= --}}

        <aside class="w-64 bg-slate-900 text-white flex flex-col">


            {{-- Logo / Title --}}

            <div class="p-6 border-b border-slate-700">

                <h2 class="text-xl font-bold">
                    MEPCOM Admin
                </h2>

            </div>


            {{-- Navigation --}}

            <nav class="p-4 space-y-2 flex-1">


                {{-- Dashboard --}}

                <a href="{{ route('admin.dashboard') }}"
                    class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition">

                    <i class="fas fa-gauge-high w-5 mr-2"></i>

                    Dashboard

                </a>


                {{-- HR Analytics --}}

                <a href="{{ route('admin.hr-analytics') }}"
                    class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition">

                    <i class="fas fa-chart-line w-5 mr-2"></i>

                    HR Analytics

                </a>


                {{-- Administrative Track --}}

                <a href="{{ route('administrative.tracks') }}"
                    class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition">

                    <i class="fas fa-graduation-cap w-5 mr-2"></i>

                    Administrative Track

                </a>


                {{-- Operational Track --}}

                <a href="{{ route('operation_tracks.index') }}"
                    class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition">

                    <i class="fas fa-ship w-5 mr-2"></i>

                    Operational Track

                </a>


                {{-- Administrative Training --}}

                <a href="{{ route('personnel.administrative.training') }}"
                    class="flex items-center px-4 py-3 rounded bg-blue-600 hover:bg-slate-800 transition">

                    <i class="fas fa-user-graduate w-5 mr-2"></i>

                    Administrative Training

                </a>


                {{-- Operational Training --}}

                <a href="{{ route('personnel.operational.training') }}"
                    class="flex items-center px-4 py-3 rounded hover:bg-blue-700 transition">

                    <i class="fas fa-ship w-5 mr-2"></i>

                    Operational Training

                </a>


                {{-- Add Unit Account --}}

                <button type="button" id="openUnitModalBtn"
                    class="w-full flex items-center text-left px-4 py-3 rounded hover:bg-blue-700 transition">

                    <i class="fas fa-plus w-5 mr-2"></i>

                    Add Unit Account

                </button>


                {{-- Logout --}}

                <a href="{{ route('admin.logout.legacy') }}"
                    class="flex items-center px-4 py-3 rounded hover:bg-red-700 transition">

                    <i class="fas fa-right-from-bracket w-5 mr-2"></i>

                    Logout

                </a>

            </nav>

        </aside>
        {{-- ========================================================= --}}
        {{-- MAIN CONTENT --}}
        {{-- ========================================================= --}}

        <main class="flex-1 overflow-y-auto">


            <div class="p-6 lg:p-8">


                {{-- Page Header --}}
                <div class="mb-6">

                    <h1 class="text-2xl font-bold text-slate-800">

                        <i class="fas fa-user-graduate text-blue-600 mr-2"></i>

                        Personnel Administrative Training

                    </h1>


                    <p class="text-slate-500 mt-1">

                        View personnel and their available administrative
                        courses based on rank.

                    </p>

                </div>



                {{-- ================================================= --}}
                {{-- SUMMARY --}}
                {{-- ================================================= --}}

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">


                    {{-- Personnel Count --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">

                        <div class="flex items-center justify-between">

                            <div>

                                <p class="text-sm text-slate-500">

                                    Personnel with Training

                                </p>

                                <p class="text-2xl font-bold text-slate-800 mt-1">

                                    {{ $personnelTraining->count() }}

                                </p>

                            </div>


                            <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">

                                <i class="fas fa-users text-blue-600 text-xl">
                                </i>

                            </div>

                        </div>

                    </div>



                    {{-- Total Training --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">

                        <div class="flex items-center justify-between">

                            <div>

                                <p class="text-sm text-slate-500">

                                    Total Available Training

                                </p>

                                <p class="text-2xl font-bold text-slate-800 mt-1">

                                    {{ $personnelTraining->sum(fn($person) => $person->trainings->count()) }}

                                </p>

                            </div>


                            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">

                                <i class="fas fa-book-open text-green-600 text-xl">
                                </i>

                            </div>

                        </div>

                    </div>

                </div>



                {{-- ================================================= --}}
                {{-- TABLE --}}
                {{-- ================================================= --}}

                <div class="bg-white rounded-xl shadow-sm border border-slate-200">


                    {{-- Header --}}
                    <div class="px-6 py-4 bg-slate-900 rounded-t-xl">

                        <h2 class="text-lg font-semibold text-white">

                            <i class="fas fa-users mr-2"></i>

                            Personnel Training Records

                        </h2>

                        <p class="text-slate-400 text-sm mt-1">

                            Available administrative training based on personnel rank.

                        </p>

                    </div>



                    {{-- Body --}}
                    <div class="p-6">


                        @if ($personnelTraining->count() > 0)


                            <div class="overflow-x-auto">


                                <table id="personnelTrainingTable" class="display nowrap w-full text-sm">


                                    <thead>

                                        <tr>

                                            <th>No.</th>

                                            <th>Personnel</th>

                                            <th>Rank</th>

                                            <th>Available Training</th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                        @foreach ($personnelTraining as $person)
                                            <tr>


                                                {{-- No --}}
                                                <td>

                                                    {{ $loop->iteration }}

                                                </td>


                                                {{-- Personnel --}}
                                                <td>

                                                    <div class="font-semibold text-slate-800">

                                                        {{ $person->name }}

                                                    </div>

                                                </td>


                                                {{-- Rank --}}
                                                <td>

                                                    <span
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-800 text-white">

                                                        {{ $person->user_rank }}

                                                    </span>

                                                </td>


                                                {{-- Training Dropdown --}}
                                                <td>

                                                    <div class="relative">

                                                        <select
                                                            class="w-full min-w-[320px] px-3 py-2 border border-slate-300 rounded-lg bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">


                                                            <option value="">

                                                                Select Course / Training

                                                            </option>


                                                            @foreach ($person->trainings as $training)
                                                                <option value="{{ $training->track_id }}">

                                                                    {{ $training->course_training }}

                                                                    @if ($training->provider)
                                                                        — {{ $training->provider }}
                                                                    @endif

                                                                </option>
                                                            @endforeach


                                                        </select>

                                                    </div>

                                                </td>


                                            </tr>
                                        @endforeach


                                    </tbody>

                                </table>

                            </div>
                        @else
                            {{-- No Records --}}
                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-5 py-4">

                                <div class="flex items-start">

                                    <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3">
                                    </i>


                                    <div>

                                        <p class="font-semibold text-yellow-800">

                                            No Personnel Training Found

                                        </p>


                                        <p class="text-sm text-yellow-700 mt-1">

                                            No personnel currently match an
                                            administrative training track.

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



    {{-- ========================================================= --}}
    {{-- JAVASCRIPT --}}
    {{-- ========================================================= --}}

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>


    <script>
        $(document).ready(function() {

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

                    {
                        targets: 0,
                        searchable: false,
                        orderable: false
                    }

                ],

                language: {

                    search: "Search Personnel:",

                    lengthMenu: "Show _MENU_ entries",

                    info: "Showing _START_ to _END_ of _TOTAL_ personnel",

                    zeroRecords: "No matching personnel found",

                    emptyTable: "No personnel training records available",

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


</body>

</html>
