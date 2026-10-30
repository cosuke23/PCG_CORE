@extends('layouts.admin')

@section('content')

<div class="min-h-screen bg-gray-950 text-white px-4 sm:px-6 lg:px-8 py-6">

    <div class="max-w-7xl mx-auto">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">

            <div>
                <h1 class="text-2xl font-bold text-white">
                    Training Applications
                </h1>

                <p class="text-gray-400 mt-1">
                    Manage and monitor personnel training applications.
                </p>
            </div>

        </div>


        {{-- KPI CARDS --}}

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">

            <div class="bg-gray-900 border border-white/10 rounded-2xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">
                            Total Applications
                        </p>

                        <h2 class="text-3xl font-bold mt-2 text-white">
                            {{ $totalApplications }}
                        </h2>
                    </div>

                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M9 12h6m-6 4h6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                        </svg>
                    </div>
                </div>
            </div>


            <div class="bg-gray-900 border border-white/10 rounded-2xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">
                            Pending
                        </p>

                        <h2 class="text-3xl font-bold mt-2 text-yellow-400">
                            {{ $pendingApplications }}
                        </h2>
                    </div>

                    <div class="w-12 h-12 rounded-xl bg-yellow-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>


            <div class="bg-gray-900 border border-white/10 rounded-2xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">
                            Approved
                        </p>

                        <h2 class="text-3xl font-bold mt-2 text-green-400">
                            {{ $approvedApplications }}
                        </h2>
                    </div>

                    <div class="w-12 h-12 rounded-xl bg-green-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
            </div>


            <div class="bg-gray-900 border border-white/10 rounded-2xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">
                            Rejected
                        </p>

                        <h2 class="text-3xl font-bold mt-2 text-red-400">
                            {{ $rejectedApplications }}
                        </h2>
                    </div>

                    <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                </div>
            </div>


            <div class="bg-gray-900 border border-white/10 rounded-2xl p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">
                            Completed
                        </p>

                        <h2 class="text-3xl font-bold mt-2 text-blue-400">
                            {{ $completedApplications }}
                        </h2>
                    </div>

                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

        </div>


        {{-- ACT / OCT CARDS --}}

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

            <div class="bg-gray-900 border border-blue-500/20 rounded-2xl p-6">

                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm text-gray-400">
                            Administrative Career / Training Track
                        </p>

                        <h2 class="text-4xl font-bold text-blue-400 mt-2">
                            {{ $actApplications }}
                        </h2>

                        <p class="text-gray-500 text-sm mt-1">
                            ACT Applications
                        </p>
                    </div>

                    <div class="w-14 h-14 rounded-2xl bg-blue-500/10 flex items-center justify-center">

                        <svg class="w-7 h-7 text-blue-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">

                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>

                        </svg>

                    </div>

                </div>

            </div>


            <div class="bg-gray-900 border border-purple-500/20 rounded-2xl p-6">

                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm text-gray-400">
                            Operational Career / Training Track
                        </p>

                        <h2 class="text-4xl font-bold text-purple-400 mt-2">
                            {{ $octApplications }}
                        </h2>

                        <p class="text-gray-500 text-sm mt-1">
                            OCT Applications
                        </p>
                    </div>

                    <div class="w-14 h-14 rounded-2xl bg-purple-500/10 flex items-center justify-center">

                        <svg class="w-7 h-7 text-purple-400"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">

                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>

                        </svg>

                    </div>

                </div>

            </div>

        </div>


        {{-- GRAPHS --}}

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <div class="bg-gray-900 border border-white/10 rounded-2xl p-6">

                <div class="mb-5">

                    <h2 class="text-lg font-semibold text-white">
                        Application Status
                    </h2>

                    <p class="text-gray-500 text-sm mt-1">
                        Distribution of applications by status.
                    </p>

                </div>

                <div class="relative h-80">

                    <canvas id="statusChart"></canvas>

                </div>

            </div>


            <div class="bg-gray-900 border border-white/10 rounded-2xl p-6">

                <div class="mb-5">

                    <h2 class="text-lg font-semibold text-white">
                        ACT vs OCT Applications
                    </h2>

                    <p class="text-gray-500 text-sm mt-1">
                        Comparison of applications by training track.
                    </p>

                </div>

                <div class="relative h-80">

                    <canvas id="trackChart"></canvas>

                </div>

            </div>

        </div>


        <div class="bg-gray-900 border border-white/10 rounded-2xl p-6 mb-8">

            <div class="mb-5">

                <h2 class="text-lg font-semibold text-white">
                    Application Overview
                </h2>

                <p class="text-gray-500 text-sm mt-1">
                    Overall training application statistics.
                </p>

            </div>

            <div class="relative h-80">

                <canvas id="overviewChart"></canvas>

            </div>

        </div>


        {{-- FILTERS --}}

        <div class="bg-gray-900 border border-white/10 rounded-2xl p-6 mb-8">

            <form method="GET"
                  action="{{ route('admin.training-applications.index') }}">

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                    <div>

                        <label class="block text-sm text-gray-400 mb-2">
                            Search
                        </label>

                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Serial number, course, rank..."
                            class="w-full bg-gray-800 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500">

                    </div>


                    <div>

                        <label class="block text-sm text-gray-400 mb-2">
                            Training Type
                        </label>

                        <select
                            name="type"
                            class="w-full bg-gray-800 border border-white/10 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">

                            <option value="">
                                All Types
                            </option>

                            <option value="ACT"
                                {{ request('type') === 'ACT' ? 'selected' : '' }}>
                                ACT
                            </option>

                            <option value="OCT"
                                {{ request('type') === 'OCT' ? 'selected' : '' }}>
                                OCT
                            </option>

                        </select>

                    </div>


                    <div>

                        <label class="block text-sm text-gray-400 mb-2">
                            Status
                        </label>

                        <select
                            name="status"
                            class="w-full bg-gray-800 border border-white/10 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">

                            <option value="">
                                All Status
                            </option>

                            <option value="Pending"
                                {{ request('status') === 'Pending' ? 'selected' : '' }}>
                                Pending
                            </option>

                            <option value="Approved"
                                {{ request('status') === 'Approved' ? 'selected' : '' }}>
                                Approved
                            </option>

                            <option value="Rejected"
                                {{ request('status') === 'Rejected' ? 'selected' : '' }}>
                                Rejected
                            </option>

                            <option value="Completed"
                                {{ request('status') === 'Completed' ? 'selected' : '' }}>
                                Completed
                            </option>

                        </select>

                    </div>


                    <div class="flex items-end gap-2">

                        <button
                            type="submit"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-3 transition">

                            Filter

                        </button>


                        <a
                            href="{{ route('admin.training-applications.index') }}"
                            class="bg-gray-700 hover:bg-gray-600 text-white rounded-lg px-4 py-3 transition">

                            Reset

                        </a>

                    </div>

                </div>

            </form>

        </div>


        {{-- APPLICATION TABLE --}}

        <div class="bg-gray-900 border border-white/10 rounded-2xl overflow-hidden">

            <div class="px-6 py-5 border-b border-white/10">

                <h2 class="text-lg font-semibold text-white">
                    Training Applications
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Review and manage submitted applications.
                </p>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full">

                    <thead class="bg-gray-800/70">

                        <tr>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase">
                                Personnel
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase">
                                Type
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase">
                                Course / Training
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase">
                                Rank
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase">
                                Division / Unit
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase">
                                Provider
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase">
                                Status
                            </th>

                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-400 uppercase">
                                Applied
                            </th>

                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-400 uppercase">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-white/5">

                        @forelse ($applications as $application)

                            <tr class="hover:bg-gray-800/40 transition">

                                <td class="px-6 py-4">

                                    <div class="font-medium text-white">
                                        {{ $application->serial_number }}
                                    </div>

                                </td>


                                <td class="px-6 py-4">

                                    @if ($application->training_type === 'ACT')

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-400">
                                            ACT
                                        </span>

                                    @else

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-500/10 text-purple-400">
                                            OCT
                                        </span>

                                    @endif

                                </td>


                                <td class="px-6 py-4">

                                    <div class="text-white font-medium">
                                        {{ $application->course_training }}
                                    </div>

                                    @if ($application->year_in_grade)

                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ $application->year_in_grade }}
                                        </div>

                                    @endif

                                </td>


                                <td class="px-6 py-4 text-gray-300">
                                    {{ $application->rank ?: '—' }}
                                </td>


                                <td class="px-6 py-4 text-gray-300">
                                    {{ $application->division_unit ?: '—' }}
                                </td>


                                <td class="px-6 py-4 text-gray-300">
                                    {{ $application->provider ?: '—' }}
                                </td>


                                <td class="px-6 py-4">

                                    @if ($application->status === 'Pending')

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-500/10 text-yellow-400">
                                            Pending
                                        </span>

                                    @elseif ($application->status === 'Approved')

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-500/10 text-green-400">
                                            Approved
                                        </span>

                                    @elseif ($application->status === 'Rejected')

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400">
                                            Rejected
                                        </span>

                                    @else

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-400">
                                            Completed
                                        </span>

                                    @endif

                                </td>


                                <td class="px-6 py-4 text-gray-400 whitespace-nowrap">

                                    @if ($application->applied_at)

                                        {{ $application->applied_at->format('M d, Y') }}

                                    @else

                                        {{ $application->created_at->format('M d, Y') }}

                                    @endif

                                </td>


                                <td class="px-6 py-4 text-right">

                                    <form
                                        method="POST"
                                        action="{{ route('admin.training-applications.status', $application) }}"
                                        class="inline-flex items-center gap-2">

                                        @csrf
                                        @method('PATCH')

                                        <select
                                            name="status"
                                            onchange="this.form.submit()"
                                            class="bg-gray-800 border border-white/10 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">

                                            <option value="Pending"
                                                {{ $application->status === 'Pending' ? 'selected' : '' }}>
                                                Pending
                                            </option>

                                            <option value="Approved"
                                                {{ $application->status === 'Approved' ? 'selected' : '' }}>
                                                Approved
                                            </option>

                                            <option value="Rejected"
                                                {{ $application->status === 'Rejected' ? 'selected' : '' }}>
                                                Rejected
                                            </option>

                                            <option value="Completed"
                                                {{ $application->status === 'Completed' ? 'selected' : '' }}>
                                                Completed
                                            </option>

                                        </select>

                                    </form>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="9"
                                    class="px-6 py-16 text-center">

                                    <div class="flex flex-col items-center">

                                        <svg class="w-12 h-12 text-gray-600 mb-4"
                                             fill="none"
                                             stroke="currentColor"
                                             viewBox="0 0 24 24">

                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  stroke-width="2"
                                                  d="M9 12h6m-6 4h6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>

                                        </svg>

                                        <p class="text-gray-400">
                                            No training applications found.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>


            @if ($applications->hasPages())

                <div class="px-6 py-5 border-t border-white/10">

                    {{ $applications->links() }}

                </div>

            @endif

        </div>

    </div>

</div>


@if (session('success') || session('error'))

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

window.addEventListener('load', function () {

    Swal.fire({
        icon: @json(session('success') ? 'success' : 'warning'),
        title: @json(session('success') ? 'Success' : 'Notice'),
        text: @json(session('success') ?? session('error')),
        confirmButtonText: 'OK',
        background: '#111827',
        color: '#ffffff',
        confirmButtonColor: '#2563eb'
    });

});

</script>

@endif


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>

window.addEventListener('load', function () {

    if (typeof Chart === 'undefined') {

        console.error('Chart.js was not loaded.');

        return;

    }


    const statusCanvas = document.getElementById('statusChart');

    if (statusCanvas) {

        new Chart(statusCanvas, {

            type: 'doughnut',

            data: {

                labels: [
                    'Pending',
                    'Approved',
                    'Rejected',
                    'Completed'
                ],

                datasets: [{

                    data: [
                        {{ $pendingApplications }},
                        {{ $approvedApplications }},
                        {{ $rejectedApplications }},
                        {{ $completedApplications }}
                    ],

                    backgroundColor: [
                        '#eab308',
                        '#22c55e',
                        '#ef4444',
                        '#3b82f6'
                    ],

                    borderColor: '#111827',

                    borderWidth: 4

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                cutout: '65%',

                plugins: {

                    legend: {

                        position: 'bottom',

                        labels: {

                            color: '#d1d5db',

                            padding: 20,

                            usePointStyle: true

                        }

                    }

                }

            }

        });

    }


    const trackCanvas = document.getElementById('trackChart');

    if (trackCanvas) {

        new Chart(trackCanvas, {

            type: 'bar',

            data: {

                labels: [
                    'ACT',
                    'OCT'
                ],

                datasets: [{

                    label: 'Applications',

                    data: [
                        {{ $actApplications }},
                        {{ $octApplications }}
                    ],

                    backgroundColor: [
                        '#3b82f6',
                        '#a855f7'
                    ],

                    borderRadius: 8,

                    borderSkipped: false

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                scales: {

                    x: {

                        ticks: {
                            color: '#9ca3af'
                        },

                        grid: {
                            display: false
                        }

                    },

                    y: {

                        beginAtZero: true,

                        ticks: {

                            color: '#9ca3af',

                            precision: 0

                        },

                        grid: {

                            color: 'rgba(255,255,255,0.05)'

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

    }


    const overviewCanvas = document.getElementById('overviewChart');

    if (overviewCanvas) {

        new Chart(overviewCanvas, {

            type: 'bar',

            data: {

                labels: [
                    'Total',
                    'Pending',
                    'Approved',
                    'Rejected',
                    'Completed'
                ],

                datasets: [{

                    label: 'Applications',

                    data: [
                        {{ $totalApplications }},
                        {{ $pendingApplications }},
                        {{ $approvedApplications }},
                        {{ $rejectedApplications }},
                        {{ $completedApplications }}
                    ],

                    backgroundColor: [
                        '#6b7280',
                        '#eab308',
                        '#22c55e',
                        '#ef4444',
                        '#3b82f6'
                    ],

                    borderRadius: 8,

                    borderSkipped: false

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                scales: {

                    x: {

                        ticks: {
                            color: '#9ca3af'
                        },

                        grid: {
                            display: false
                        }

                    },

                    y: {

                        beginAtZero: true,

                        ticks: {

                            color: '#9ca3af',

                            precision: 0

                        },

                        grid: {

                            color: 'rgba(255,255,255,0.05)'

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

    }

});

</script>

@endsection