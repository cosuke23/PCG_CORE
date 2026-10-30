@extends('layouts.admin')

@section('title', 'HR Analytics Dashboard')

@section('styles')
<script src="{{ asset('vendor/chartjs/chart-4.4.4.umd.min.js') }}"></script>
<style>
    .analytics-card {
        transition: all 0.2s ease;
    }

    .analytics-card:hover {
        transform: translateY(-2px);
    }
</style>

@endsection

@section('content')

<div class="flex min-h-screen bg-gray-100">

    {{-- Sidebar: provided by layouts/admin --}}

    {{-- ================= MAIN CONTENT ================= --}}
    <main class="flex-1 p-10">

        {{-- HEADER --}}
        <div class="flex flex-col md:flex-row md:items-center
                    md:justify-between gap-4 mb-8">

            <div>
                <h1 class="text-3xl font-bold text-gray-800">
                    HR Analytics Dashboard
                </h1>

                <p class="text-gray-500 mt-1">
                    Human Resource Management Information System
                </p>
            </div>


            {{-- FISCAL YEAR FILTER --}}
            <form method="GET" action="{{ route('admin.hr-analytics') }}" class="flex items-center gap-2">

                <label for="year" class="font-semibold text-gray-700">
                    Fiscal Year:
                </label>

                <select name="year" id="year" data-on-change="pcg.submit-form"
                    class="border border-gray-300 rounded-lg
                           px-4 py-2 bg-white shadow-sm
                           focus:ring-2 focus:ring-blue-500">

                    @forelse($availableYears as $availableYear)
                    <option value="{{ $availableYear }}" {{ $year == $availableYear ? 'selected' : '' }}>

                        FY {{ $availableYear }}

                    </option>

                    @empty

                    <option value="{{ $year }}">
                        FY {{ $year }}
                    </option>
                    @endforelse

                </select>

            </form>

        </div>


        {{-- ================= KPI CARDS ================= --}}
        <div class="grid grid-cols-1 sm:grid-cols-2
                    lg:grid-cols-3 xl:grid-cols-6 gap-5 mb-8">


            {{-- TOTAL --}}
            <div class="analytics-card bg-white rounded-xl shadow p-5">

                <p class="text-sm text-gray-500">
                    Total Personnel
                </p>

                <h2 class="text-3xl font-bold text-blue-600 mt-2">
                    {{ number_format($totalPersonnel) }}
                </h2>

                <p class="text-xs text-gray-400 mt-2">
                    FY {{ $year }}
                </p>

            </div>


            {{-- ACTIVE --}}
            <div class="analytics-card bg-white rounded-xl shadow p-5">

                <p class="text-sm text-gray-500">
                    Active Personnel
                </p>

                <h2 class="text-3xl font-bold text-green-600 mt-2">
                    {{ number_format($activePersonnel) }}
                </h2>

                <p class="text-xs text-gray-400 mt-2">
                    Currently active
                </p>

            </div>


            {{-- PENDING --}}
            <div class="analytics-card bg-white rounded-xl shadow p-5">

                <p class="text-sm text-gray-500">
                    Pending
                </p>

                <h2 class="text-3xl font-bold text-yellow-500 mt-2">
                    {{ number_format($pendingPersonnel) }}
                </h2>

                <p class="text-xs text-gray-400 mt-2">
                    Awaiting approval
                </p>

            </div>


            {{-- APPROVED --}}
            <div class="analytics-card bg-white rounded-xl shadow p-5">

                <p class="text-sm text-gray-500">
                    Approved
                </p>

                <h2 class="text-3xl font-bold text-green-600 mt-2">
                    {{ number_format($approvedPersonnel) }}
                </h2>

                <p class="text-xs text-gray-400 mt-2">
                    Approved personnel
                </p>

            </div>


            {{-- REJECTED --}}
            <div class="analytics-card bg-white rounded-xl shadow p-5">

                <p class="text-sm text-gray-500">
                    Rejected
                </p>

                <h2 class="text-3xl font-bold text-red-600 mt-2">
                    {{ number_format($rejectedPersonnel) }}
                </h2>

                <p class="text-xs text-gray-400 mt-2">
                    Rejected personnel
                </p>

            </div>


            {{-- UNITS --}}
            <div class="analytics-card bg-white rounded-xl shadow p-5">

                <p class="text-sm text-gray-500">
                    Units Represented
                </p>

                <h2 class="text-3xl font-bold text-purple-600 mt-2">
                    {{ number_format($unitsRepresented) }}
                </h2>

                <p class="text-xs text-gray-400 mt-2">
                    With personnel records
                </p>

            </div>

        </div>
        {{-- ================= HR ANALYTICS CHARTS ================= --}}

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            {{-- STATUS DISTRIBUTION --}}
            <div class="bg-white rounded-xl shadow p-6">

                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-800">
                        Personnel Status
                    </h2>

                    <p class="text-sm text-gray-500">
                        Personnel status distribution for FY {{ $year }}
                    </p>
                </div>

                <div class="h-72">
                    <canvas id="statusChart"></canvas>
                </div>

            </div>


            {{-- OFFICER VS NON-OFFICER --}}
            <div class="bg-white rounded-xl shadow p-6">

                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-800">
                        Workforce Composition
                    </h2>

                    <p class="text-sm text-gray-500">
                        Officer and Non-Officer distribution
                    </p>
                </div>

                <div class="h-72">
                    <canvas id="registrationChart"></canvas>
                </div>

            </div>


            {{-- PERSONNEL BY UNIT --}}
            <div class="bg-white rounded-xl shadow p-6 lg:col-span-2">

                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-800">
                        Personnel by Unit
                    </h2>

                    <p class="text-sm text-gray-500">
                        Distribution of personnel across MEPCOM units
                    </p>
                </div>

                <div class="h-80">
                    <canvas id="unitChart"></canvas>
                </div>

            </div>


            {{-- RANK DISTRIBUTION --}}
            <div class="bg-white rounded-xl shadow p-6">

                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-800">
                        Rank Distribution
                    </h2>

                    <p class="text-sm text-gray-500">
                        Personnel distribution by rank
                    </p>
                </div>

                <div class="h-72">
                    <canvas id="rankChart"></canvas>
                </div>

            </div>


            {{-- TRAINING / ACADEMIC --}}
            <div class="bg-white rounded-xl shadow p-6">

                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-800">
                        Training & Academic Distribution
                    </h2>

                    <p class="text-sm text-gray-500">
                        Top courses recorded for FY {{ $year }}
                    </p>
                </div>

                <div class="h-72">
                    <canvas id="academicChart"></canvas>
                </div>

            </div>


            {{-- HISTORICAL TREND --}}
            <div class="bg-white rounded-xl shadow p-6 lg:col-span-2">

                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-800">
                        Historical Personnel Trend
                    </h2>

                    <p class="text-sm text-gray-500">
                        Personnel records by fiscal year
                    </p>
                </div>

                <div class="h-80">
                    <canvas id="yearlyChart"></canvas>
                </div>

            </div>

        </div>

        {{-- ================= OVERVIEW ================= --}}
        <div class="bg-white rounded-xl shadow p-6">

            <div class="flex items-center justify-between mb-5">

                <div>
                    <h2 class="text-xl font-bold text-gray-800">
                        HR Overview
                    </h2>

                    <p class="text-sm text-gray-500">
                        Summary of personnel data for FY {{ $year }}
                    </p>
                </div>

            </div>


            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div class="border rounded-lg p-5">

                    <h3 class="font-semibold text-gray-700 mb-3">
                        Workforce Overview
                    </h3>

                    <p class="text-gray-500 text-sm">
                        Total personnel:
                        <strong>
                            {{ number_format($totalPersonnel) }}
                        </strong>
                    </p>

                    <p class="text-gray-500 text-sm mt-2">
                        Active personnel:
                        <strong>
                            {{ number_format($activePersonnel) }}
                        </strong>
                    </p>

                    <p class="text-gray-500 text-sm mt-2">
                        Units represented:
                        <strong>
                            {{ number_format($unitsRepresented) }}
                        </strong>
                    </p>

                </div>


                <div class="border rounded-lg p-5">

                    <h3 class="font-semibold text-gray-700 mb-3">
                        Personnel Status
                    </h3>

                    <p class="text-gray-500 text-sm">
                        Approved:
                        <strong>
                            {{ number_format($approvedPersonnel) }}
                        </strong>
                    </p>

                    <p class="text-gray-500 text-sm mt-2">
                        Pending:
                        <strong>
                            {{ number_format($pendingPersonnel) }}
                        </strong>
                    </p>

                    <p class="text-gray-500 text-sm mt-2">
                        Rejected:
                        <strong>
                            {{ number_format($rejectedPersonnel) }}
                        </strong>
                    </p>

                </div>

            </div>

        </div>

    </main>

</div>
<script src="{{ asset('vendor/chartjs/chart-4.4.4.umd.min.js') }}"></script>

<script nonce="{{ $cspNonce ?? '' }}">
    /*
        |--------------------------------------------------------------------------
        | DATA FROM LARAVEL
        |--------------------------------------------------------------------------
        */

    const statusData = @json($statusChart);

    const registrationData = @json($registrationChart);

    const unitData = @json($unitChart);

    const rankData = @json($rankChart);

    const academicData = @json($academicChart);

    const yearlyData = @json($yearlyChart);


    /*
    |--------------------------------------------------------------------------
    | STATUS CHART
    |--------------------------------------------------------------------------
    */

    new Chart(document.getElementById('statusChart'), {

        type: 'doughnut',

        data: {

            labels: statusData.map(item => item.status),

            datasets: [{
                data: statusData.map(item => item.total)
            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    position: 'bottom'
                }

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | OFFICER / NON-OFFICER
    |--------------------------------------------------------------------------
    */

    new Chart(document.getElementById('registrationChart'), {

        type: 'doughnut',

        data: {

            labels: registrationData.map(item => item.registration_type),

            datasets: [{
                data: registrationData.map(item => item.total)
            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    position: 'bottom'
                }

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | UNIT CHART
    |--------------------------------------------------------------------------
    */

    new Chart(document.getElementById('unitChart'), {

        type: 'bar',

        data: {

            labels: unitData.map(item => item.unit),

            datasets: [{

                label: 'Personnel',

                data: unitData.map(item => item.total)

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }

                }

            },

            plugins: {

                legend: {
                    display: false
                }

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | RANK CHART
    |--------------------------------------------------------------------------
    */

    new Chart(document.getElementById('rankChart'), {

        type: 'bar',

        data: {

            labels: rankData.map(item => item.rank),

            datasets: [{

                label: 'Personnel',

                data: rankData.map(item => item.total)

            }]

        },

        options: {

            indexAxis: 'y',

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                x: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }

                }

            },

            plugins: {

                legend: {
                    display: false
                }

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | TRAINING / ACADEMIC CHART
    |--------------------------------------------------------------------------
    */

    new Chart(document.getElementById('academicChart'), {

        type: 'bar',

        data: {

            labels: academicData.map(item => item.course),

            datasets: [{

                label: 'Personnel',

                data: academicData.map(item => item.total)

            }]

        },

        options: {

            indexAxis: 'y',

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                x: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }

                }

            },

            plugins: {

                legend: {
                    display: false
                }

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | YEARLY TREND
    |--------------------------------------------------------------------------
    */

    new Chart(document.getElementById('yearlyChart'), {

        type: 'line',

        data: {

            labels: yearlyData.map(item => 'FY ' + item.year),

            datasets: [{

                label: 'Personnel Records',

                data: yearlyData.map(item => item.total),

                tension: 0.3,

                fill: true

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }

                }

            }

        }

    });
</script>
@endsection