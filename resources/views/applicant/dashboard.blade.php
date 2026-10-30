@extends('layouts.app')

@section('title', 'PCG Dashboard')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ================= BACKGROUND ================= -->
    <div class="relative min-h-screen text-white overflow-hidden"
        style="background-image: url('{{ asset('backgrounds/web bg profile.jpg') }}');
    background-size: cover; background-position: center; background-repeat: no-repeat;">

        <br>
        <br>
        <br>
        <br>
        <div class="absolute inset-0 bg-black/40"></div>

        <!-- ================= FLOATING NOTIFICATION ================= -->
        @if (($rejectedCount ?? 0) > 0)
            <div id="rejectNotif" class="fixed top-5 right-5 z-50">

                <div onclick="openRejectedModal()"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-xl shadow-lg cursor-pointer flex items-center gap-3 animate-bounce">

                    <span class="font-bold">⚠ Rejected Docs</span>

                    <span class="bg-white text-red-600 font-bold px-2 py-0.5 rounded-full text-sm">
                        {{ $rejectedCount }}
                    </span>

                </div>
            </div>
        @endif

        <!-- ================= MAIN CONTENT ================= -->
        <div class="relative z-10 flex flex-col items-center justify-center min-h-[80vh] px-4">

            <div
                class="bg-white/10 backdrop-blur-md rounded-3xl shadow-2xl p-10 w-full max-w-3xl text-center border border-white/20">

                <h2 class="text-4xl font-bold mb-4">
                    Welcome Aboard, {{ $user->name ?? 'User' }}!
                </h2>

                <p class="text-gray-200 text-lg mb-8">
                    Navigate below to manage your profile and documents.
                </p>

                <!-- ================= GRID ================= -->
                <div class="grid md:grid-cols-2 gap-6">

                    <!-- PROFILE -->
                    <div class="bg-blue-900/30 hover:bg-blue-900/50 rounded-2xl p-6 border border-white/20">
                        <h3 class="text-2xl font-semibold mb-2">Profile</h3>
                        <p class="text-gray-300 text-sm mb-3">
                            View and edit your personal information.
                        </p>

                        <a href="{{ route('profile') }}" class="text-blue-400 hover:text-yellow-400 text-sm font-medium">
                            Go to Profile →
                        </a>
                    </div>

                    <!-- UPLOAD -->
                    <div class="bg-blue-900/30 hover:bg-blue-900/50 rounded-2xl p-6 border border-white/20">
                        <h3 class="text-2xl font-semibold mb-2">Upload Documents</h3>
                        <p class="text-gray-300 text-sm mb-3">
                            Submit your requirements securely.
                        </p>

                        <a href="{{ route('upload.documents') }}"
                            class="text-blue-400 hover:text-yellow-400 text-sm font-medium">
                            Go to Uploads →
                        </a>
                    </div>

                    <div class="bg-blue-900/30 hover:bg-blue-900/50 rounded-2xl p-6 border border-white/20">
                        <h3 class="text-2xl font-semibold mb-2">Upload 201 Files</h3>
                        <p class="text-gray-300 text-sm mb-3">
                            Submit your 201 Files securely.
                        </p>

                        <a href="{{ route('upload.201') }}" class="text-blue-400 hover:text-yellow-400 text-sm font-medium">
                            Go to Uploads →
                        </a>
                    </div>

                    <!-- REJECTED CARD -->
                    <div class="bg-red-900/30 hover:bg-red-900/50 rounded-2xl p-6 border border-white/20">

                        <h3 class="text-2xl font-semibold mb-2">
                            Rejected Documents
                        </h3>

                        <p class="text-gray-300 text-sm mb-3">
                            View reviewer remarks and missing requirements.
                        </p>

                        <button type="button" onclick="openRejectedModal()"
                            class="text-red-400 hover:text-yellow-300 text-sm font-medium">
                            View Rejected Docs →
                        </button>

                    </div>
                    <!-- TRAINING COURSE CARD -->
                    <div
                        class="bg-blue-900/30 hover:bg-blue-900/50 rounded-2xl p-6 border border-white/20 transition duration-200">

                        <h3 class="text-2xl font-semibold mb-2">
                            Training Courses
                        </h3>

                        <p class="text-gray-300 text-sm mb-3">
                            View available Administrative and Operational training courses.
                        </p>

                        <button type="button" onclick="openTrainingCourseModal()"
                            class="text-blue-400 hover:text-yellow-300 text-sm font-medium">
                            View Training Courses →
                        </button>

                    </div>


                    <!-- TRAINING COURSE MODAL -->
                    <div id="trainingCourseModal"
                        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 backdrop-blur-sm p-4">

                        <div
                            class="bg-gray-900 rounded-2xl shadow-2xl border border-white/20
                w-full max-w-2xl p-6 relative">

                            <!-- CLOSE BUTTON -->
                            <button type="button" onclick="closeTrainingCourseModal()"
                                class="absolute top-4 right-5 text-gray-400 hover:text-white text-2xl">
                                &times;
                            </button>

                            <!-- TITLE -->
                            <div class="mb-6">
                                <h2 class="text-2xl font-bold text-white">
                                    Training Courses
                                </h2>

                                <p class="text-gray-400 text-sm mt-1">
                                    Select a training track to continue.
                                </p>
                            </div>


                            <!-- ACT / OCT CARDS -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                                <!-- ACT CARD -->
                                <a href="{{ route('applicant.administrative.training') }}"
                                    class="group bg-blue-900/30 hover:bg-blue-900/50
        rounded-2xl p-6 border border-white/20
        transition duration-200">

                                    <div class="flex items-center justify-between mb-4">

                                        <div
                                            class="w-12 h-12 rounded-xl bg-blue-600/20
                flex items-center justify-center">

                                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">

                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477
                            9.246 5 7.5 5S4.168 5.477 3
                            6.253v13C4.168 18.477 5.754
                            18 7.5 18s3.332.477 4.5
                            1.253m0-13C13.168 5.477
                            14.754 5 16.5 5c1.746 0
                            3.332.477 4.5 1.253v13
                            C19.832 18.477 18.246 18
                            16.5 18c-1.746 0-3.332.477-4.5
                            1.253" />

                                            </svg>

                                        </div>

                                        <span class="text-blue-400 text-xl">
                                            →
                                        </span>

                                    </div>

                                    <h3 class="text-xl font-semibold text-white">
                                        ACT
                                    </h3>

                                    <p class="text-gray-400 text-sm mt-2">
                                        Administrative Career and Training Track
                                    </p>

                                </a>


                                <!-- OCT CARD -->
                                <a href="{{ route('applicant.operational.training') }}"
                                    class="group bg-green-900/30 hover:bg-green-900/50
        rounded-2xl p-6 border border-white/20
        transition duration-200">

                                    <div class="flex items-center justify-between mb-4">

                                        <div
                                            class="w-12 h-12 rounded-xl bg-green-600/20
                flex items-center justify-center">

                                            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">

                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M5 7v10a2 2 0
                            002 2h10a2 2 0 002-2V7
                            M8 7V5a2 2 0 012-2h4a2
                            2 0 012 2v2" />

                                            </svg>

                                        </div>

                                        <span class="text-green-400 text-xl">
                                            →
                                        </span>

                                    </div>

                                    <h3 class="text-xl font-semibold text-white">
                                        OCT
                                    </h3>

                                    <p class="text-gray-400 text-sm mt-2">
                                        Operational Career and Training Track
                                    </p>

                                </a>

                            </div>


                            <!-- CANCEL -->
                            <div class="mt-6 text-right">

                                <button type="button" onclick="closeTrainingCourseModal()"
                                    class="px-5 py-2 rounded-xl bg-gray-700
                       hover:bg-gray-600 text-gray-200 text-sm">

                                    Close

                                </button>

                            </div>

                        </div>

                    </div>


                    <script>
                        function openTrainingCourseModal() {
                            const modal = document.getElementById('trainingCourseModal');

                            modal.classList.remove('hidden');
                            modal.classList.add('flex');
                        }

                        function closeTrainingCourseModal() {
                            const modal = document.getElementById('trainingCourseModal');

                            modal.classList.add('hidden');
                            modal.classList.remove('flex');
                        }

                        document.addEventListener('keydown', function(event) {
                            if (event.key === 'Escape') {
                                closeTrainingCourseModal();
                            }
                        });
                    </script>

                    @if ($hasClass)
                        <!-- MEPTDI LMS CARD -->
                        <div
                            class="bg-blue-900/30 hover:bg-blue-900/50 rounded-2xl p-6 border border-white/20 transition duration-300">

                            <div class="flex items-center justify-between mb-3">

                                <div>
                                    <h3 class="text-2xl font-semibold">
                                        MEPTDI LMS
                                    </h3>

                                    <p class="text-gray-300 text-sm mt-2">
                                        Access your enrolled courses, training materials, and learning activities.
                                    </p>
                                </div>

                                <div class="text-4xl">
                                    🎓
                                </div>

                            </div>

                            <a href="" class="inline-block text-blue-400 hover:text-yellow-300 text-sm font-medium">

                                Open MEPTDI LMS →

                            </a>

                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL ================= -->
    <div id="rejectedModal" class="fixed inset-0 hidden items-center justify-center bg-black/70 z-50">

        <div class="bg-gray-900 text-white w-full max-w-2xl rounded-xl p-6 border border-white/20">

            <!-- HEADER -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Rejected Documents</h2>

                <button onclick="closeRejectedModal()" class="text-red-400 text-xl">
                    ✖
                </button>
            </div>

            <!-- CONTENT -->
            <div id="rejectedList" class="space-y-3 max-h-[400px] overflow-y-auto"></div>

        </div>
    </div>

@endsection

<!-- ================= JS ================= -->
<script>
        // Escape server data before inserting it with innerHTML.
        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            })[c]);
        }
    const rejectedDocs = @json($rejectedDocs ?? []);

    function openRejectedModal() {

        let html = '';

        if (!rejectedDocs || rejectedDocs.length === 0) {
            html = `<p class="text-gray-400">No rejected documents found.</p>`;
        } else {
            rejectedDocs.forEach(doc => {
                html += `
                    <div class="p-3 bg-red-900/30 border border-red-500/30 rounded-lg">
                        <p class="font-semibold text-red-300">
                            ${escapeHtml(doc.doc_type)}
                        </p>
                        <p class="text-sm text-gray-300 mt-1">
                            ${escapeHtml(doc.comment ?? 'No comment provided')}
                        </p>
                    </div>
                `;
            });
        }

        document.getElementById('rejectedList').innerHTML = html;

        document.getElementById('rejectedModal').classList.remove('hidden');
        document.getElementById('rejectedModal').classList.add('flex');
    }

    function closeRejectedModal() {
        document.getElementById('rejectedModal').classList.add('hidden');
        document.getElementById('rejectedModal').classList.remove('flex');
    }
</script>
