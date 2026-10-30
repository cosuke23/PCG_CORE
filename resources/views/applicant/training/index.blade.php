@extends('layouts.app')

@section('content')

    <div class="min-h-screen bg-gray-950 text-white py-8">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- HEADER -->
            <div class="mb-8">

                <div class="flex items-center gap-3 mb-2">

                    <a href="{{ url()->previous() }}" class="text-gray-400 hover:text-white transition">
                        ←
                    </a>

                    <h1 class="text-3xl font-bold">
                        {{ $title }}
                    </h1>

                </div>

                <p class="text-gray-400">
                    Training courses applicable to your current rank and selected year in grade.
                </p>

            </div>

            <!-- APPLICANT INFORMATION -->
            <div class="bg-gray-900 border border-white/10 rounded-2xl p-6 mb-8">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- CURRENT RANK -->
                    <div>
                        <p class="text-gray-400 text-sm">
                            Current Rank
                        </p>

                        <p class="text-xl font-semibold mt-1">
                            {{ $rank }}
                        </p>
                    </div>

                    <!-- EQUIVALENT RANK -->
                    <div>
                        <p class="text-gray-400 text-sm">
                            Equivalent Track Rank
                        </p>

                        <p class="text-xl font-semibold text-blue-400 mt-1">
                            {{ $equivalentRank ?? 'Not Defined' }}
                        </p>
                    </div>

                    <!-- YEAR FILTER -->
                    <div>
                        <label for="yearInGrade" class="text-gray-400 text-sm block mb-2">
                            Training Year
                        </label>

                        <select id="yearInGrade" name="year_in_grade" onchange="filterTraining()"
                            class="w-full bg-gray-800 border border-white/10
                                   rounded-xl px-4 py-2.5 text-white
                                   focus:outline-none focus:ring-2
                                   focus:ring-blue-500">

                            <option value="1" {{ $yearFilter == '1' ? 'selected' : '' }}>
                                1 Year
                            </option>

                            <option value="2" {{ $yearFilter == '2' ? 'selected' : '' }}>
                                2 Years
                            </option>

                            <option value="more_than_2" {{ $yearFilter == 'more_than_2' ? 'selected' : '' }}>
                                More Than 2 Years
                            </option>

                        </select>
                    </div>

                </div>

            </div>
            @if (session('success'))
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Application Submitted',
                            text: @js(session('success')),
                            confirmButtonText: 'OK'
                        });
                    });
                </script>
            @endif

            @if (session('error'))
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Already Applied',
                            text: @js(session('error')),
                            confirmButtonText: 'OK'
                        });
                    });
                </script>
            @endif
            <!-- RANK NOT DEFINED -->
            @if (!$equivalentRank)
                <div class="bg-yellow-900/30 border border-yellow-500/30
                            rounded-2xl p-6">

                    <h3 class="text-lg font-semibold text-yellow-400 mb-2">
                        Rank Equivalency Not Defined
                    </h3>

                    <p class="text-gray-300 text-sm">
                        Your current rank does not have an equivalent
                        ACT/OCT training rank configured.
                    </p>

                </div>

                <!-- NO TRAINING -->
            @elseif ($tracks->isEmpty())
                <div class="bg-gray-900 border border-white/10
                            rounded-2xl p-8 text-center">

                    <div
                        class="mx-auto w-14 h-14 rounded-full
                                bg-gray-800 flex items-center justify-center mb-4">

                        <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86
                                                                c1.54 0 2.5-1.67 1.73-3L13.73 4
                                                                c-.77-1.33-2.69-1.33-3.46 0L3.34
                                                                16c-.77 1.33.19 3 1.73 3z" />

                        </svg>

                    </div>

                    <h3 class="text-xl font-semibold mb-2">
                        No Training Courses Available
                    </h3>

                    <p class="text-gray-400 text-sm">
                        There are currently no {{ $type }} training courses
                        configured for your equivalent rank and selected
                        year in grade.
                    </p>

                </div>

                <!-- TRAINING COURSES -->
            @else
                <div x-data="{
                    showModal: false,
                    selectedTrack: {}
                }" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                    @foreach ($tracks as $track)
                        @php
                            $status = $track->application_status;

                            $cardClass = match ($status) {
                                'Pending' => 'bg-yellow-950/30 border-yellow-500/40 hover:bg-yellow-950/50',
                                'Approved' => 'bg-green-950/30 border-green-500/40 hover:bg-green-950/50',
                                'Rejected' => 'bg-red-950/30 border-red-500/40 hover:bg-red-950/50',
                                'Completed' => 'bg-purple-950/30 border-purple-500/40 hover:bg-purple-950/50',
                                default => 'bg-gray-900 border-white/10 hover:bg-gray-800',
                            };

                            $iconClass = match ($status) {
                                'Pending' => 'bg-yellow-600/20 text-yellow-400',
                                'Approved' => 'bg-green-600/20 text-green-400',
                                'Rejected' => 'bg-red-600/20 text-red-400',
                                'Completed' => 'bg-purple-600/20 text-purple-400',
                                default => 'bg-blue-600/20 text-blue-400',
                            };

                            $statusClass = match ($status) {
                                'Pending' => 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30',
                                'Approved' => 'bg-green-500/20 text-green-400 border-green-500/30',
                                'Rejected' => 'bg-red-500/20 text-red-400 border-red-500/30',
                                'Completed' => 'bg-purple-500/20 text-purple-400 border-purple-500/30',
                                default => '',
                            };
                        @endphp

                        <div @click="
                    selectedTrack = {
                        id: @js($track->id),
                        type: @js($type),
                        rank: @js($track->rank),
                        course: @js($track->course_training),
                        division: @js(str_replace('?', '/', $track->division_unit)),
                        year: @js($track->year_in_grade),
                        provider: @js($track->provider),
                        remarks: @js($track->remarks),
                        status: @js($track->application_status)
                    };
                    showModal = true;
                "
                            class="{{ $cardClass }}
                    border
                    rounded-2xl
                    p-6
                    transition
                    duration-200
                    cursor-pointer">

                            <div class="flex items-center justify-between mb-4">

                                <div
                                    class="w-12 h-12 rounded-xl {{ $iconClass }}
                        flex items-center justify-center">

                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>

                                </div>


                                <div class="text-right">

                                    <span class="text-blue-400 text-sm font-medium">
                                        {{ $track->rank }}
                                    </span>

                                    @if ($status)
                                        <div class="mt-2">

                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold {{ $statusClass }}">
                                                {{ $status }}
                                            </span>

                                        </div>
                                    @endif

                                </div>

                            </div>


                            <h3 class="text-lg font-semibold text-white">
                                {{ $track->course_training }}
                            </h3>


                            <div class="mt-4 space-y-2 text-sm">

                                <p class="text-gray-400">

                                    <span class="text-gray-300">
                                        Division / Unit:
                                    </span>

                                    {{ str_replace('?', '/', $track->division_unit) }}

                                </p>


                                <p class="text-gray-400">

                                    <span class="text-gray-300">
                                        Year in Grade:
                                    </span>

                                    {{ $track->year_in_grade }}

                                </p>


                                @if ($track->provider)
                                    <p class="text-gray-400">

                                        <span class="text-gray-300">
                                            Provider:
                                        </span>

                                        {{ $track->provider }}

                                    </p>
                                @endif

                            </div>


                            @if ($track->remarks)
                                <div class="mt-4 pt-4 border-t border-white/10">

                                    <p class="text-gray-500 text-xs">
                                        {{ $track->remarks }}
                                    </p>

                                </div>
                            @endif


                            @if ($status === 'Pending')
                                <div class="mt-5 text-yellow-400 text-sm font-medium">
                                    Application Pending
                                </div>
                            @elseif ($status === 'Approved')
                                <div class="mt-5 text-green-400 text-sm font-medium">
                                    Training Application Approved
                                </div>
                            @elseif ($status === 'Rejected')
                                <div class="mt-5 text-red-400 text-sm font-medium">
                                    Training Application Rejected
                                </div>
                            @elseif ($status === 'Completed')
                                <div class="mt-5 text-purple-400 text-sm font-medium">
                                    Training Completed
                                </div>
                            @else
                                <div class="mt-5 text-blue-400 text-sm font-medium">
                                    Click to view training details →
                                </div>
                            @endif

                        </div>
                    @endforeach


                    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4"
                        @keydown.escape.window="showModal = false">

                        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showModal = false"></div>


                        <div class="relative w-full max-w-lg bg-gray-900 border border-white/10 rounded-2xl shadow-2xl p-6"
                            @click.stop>

                            <div class="flex items-center justify-between mb-6">

                                <div>

                                    <p class="text-blue-400 text-sm font-medium">

                                        <span x-text="selectedTrack.type"></span>
                                        Training

                                    </p>

                                    <h2 class="text-xl font-semibold text-white mt-1" x-text="selectedTrack.course"></h2>

                                </div>


                                <button type="button" @click="showModal = false"
                                    class="text-gray-400 hover:text-white text-2xl">
                                    &times;
                                </button>

                            </div>


                            <div class="space-y-4">

                                <div>

                                    <p class="text-xs text-gray-500 uppercase">
                                        Rank
                                    </p>

                                    <p class="text-white mt-1" x-text="selectedTrack.rank"></p>

                                </div>


                                <div>

                                    <p class="text-xs text-gray-500 uppercase">
                                        Division / Unit
                                    </p>

                                    <p class="text-white mt-1" x-text="selectedTrack.division"></p>

                                </div>


                                <div>

                                    <p class="text-xs text-gray-500 uppercase">
                                        Year in Grade
                                    </p>

                                    <p class="text-white mt-1" x-text="selectedTrack.year"></p>

                                </div>


                                <div x-show="selectedTrack.provider">

                                    <p class="text-xs text-gray-500 uppercase">
                                        Provider
                                    </p>

                                    <p class="text-white mt-1" x-text="selectedTrack.provider"></p>

                                </div>


                                <div x-show="selectedTrack.remarks">

                                    <p class="text-xs text-gray-500 uppercase">
                                        Remarks
                                    </p>

                                    <p class="text-gray-300 mt-1" x-text="selectedTrack.remarks"></p>

                                </div>


                                <div x-show="selectedTrack.status">

                                    <p class="text-xs text-gray-500 uppercase">
                                        Application Status
                                    </p>

                                    <p class="mt-1 font-semibold"
                                        :class="{
                                            'text-yellow-400': selectedTrack.status === 'Pending',
                                            'text-green-400': selectedTrack.status === 'Approved',
                                            'text-red-400': selectedTrack.status === 'Rejected',
                                            'text-purple-400': selectedTrack.status === 'Completed'
                                        }"
                                        x-text="selectedTrack.status"></p>

                                </div>

                            </div>


                            <div class="mt-7 flex justify-end gap-3">

                                <button type="button" @click="showModal = false"
                                    class="px-5 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium">
                                    Close
                                </button>


                                <form method="POST" action="{{ route('applicant.training.store') }}"
                                    x-show="!selectedTrack.status">

                                    @csrf

                                    <input type="hidden" name="track_id" :value="selectedTrack.id">

                                    <input type="hidden" name="type" :value="selectedTrack.type">

                                    <button type="submit"
                                        class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
                                        Apply for Training
                                    </button>

                                </form>


                                <div x-show="selectedTrack.status === 'Pending'"
                                    class="px-5 py-2.5 rounded-lg bg-yellow-600/20 text-yellow-400 text-sm font-medium">
                                    Application Pending
                                </div>


                                <div x-show="selectedTrack.status === 'Approved'"
                                    class="px-5 py-2.5 rounded-lg bg-green-600/20 text-green-400 text-sm font-medium">
                                    Approved
                                </div>


                                <div x-show="selectedTrack.status === 'Rejected'"
                                    class="px-5 py-2.5 rounded-lg bg-red-600/20 text-red-400 text-sm font-medium">
                                    Rejected
                                </div>


                                <div x-show="selectedTrack.status === 'Completed'"
                                    class="px-5 py-2.5 rounded-lg bg-purple-600/20 text-purple-400 text-sm font-medium">
                                    Completed
                                </div>

                            </div>

                        </div>

                    </div>

                </div>
            @endif

        </div>

    </div>

    <script>
        function filterTraining() {
            const year = document.getElementById('yearInGrade').value;

            const url = new URL(window.location.href);

            url.searchParams.set('year_in_grade', year);

            window.location.href = url.toString();
        }
    </script>

@endsection
