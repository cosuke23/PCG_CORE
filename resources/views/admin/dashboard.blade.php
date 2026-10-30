@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">

    <script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>

    <script src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
@endsection

@section('content')

    <script src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>

    <div class="flex min-h-screen bg-gray-100">

        @include('admin.partials.lms-menu')

        <!-- ================= MAIN ================= -->
        <main class="flex-1 p-10">
            <div id="unitAccountModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">

                <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl">

                    <div class="flex justify-between items-center border-b p-4">
                        <h2 class="font-bold text-lg">Add Unit Account</h2>
                        <button id="closeUnitModal" class="text-red-600 text-xl">✕</button>
                    </div>

                    <form action="{{ route('unit-users.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-2 gap-4 p-6">

                            <input name="personnel_id" class="border p-2 rounded" placeholder="Personnel ID">
                            <input name="serial_number" class="border p-2 rounded" placeholder="Serial Number">

                            <input name="full_name" class="border p-2 rounded" placeholder="Full Name">
                            <input name="email" class="border p-2 rounded" placeholder="Email">

                            <input name="username" class="border p-2 rounded" placeholder="Username">
                            <input type="password" name="password" class="border p-2 rounded" placeholder="Password">
                            <select name="role" class="border p-2 rounded w-full">

                                <option value="">-- Select Unit --</option>

                                <option value="MEPLEF">
                                    Marine Environmental Protection Law Enforcement Force (MEPLEF)
                                </option>

                                <option value="NOCOP">
                                    National Operations Center for Oil Pollution (NOCOP)
                                </option>

                                <option value="MSFIS">
                                    Marine Science Forensic Investigation Service (MSFIS)
                                </option>

                                <option value="MEPRCGS">
                                    Marine Environmental Protection Regulatory Compliance and Governance Service (MEPRCGS)
                                </option>

                                <option value="MEPTDI">
                                    Marine Environmental Protection Training and Doctrine Institute (MEPTDI)
                                </option>

                                <option value="MEPG">
                                    Marine Environmental Protection Group (MEPG)
                                </option>

                            </select>

                            <input name="unit_code" value="1" class="border p-2 rounded" placeholder="Unit Code"
                                hidden>

                            <div class="col-span-2">
                                <input name="unit_name" class="border p-2 rounded w-full" placeholder="Unit Name">
                            </div>

                            <div class="col-span-2">
                                <textarea name="permissions" value="1" class="border p-2 rounded w-full" placeholder='["view","edit"]' hidden></textarea>
                            </div>

                            <select name="is_active" class="border p-2 rounded">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>

                        </div>

                        <div class="border-t p-4 flex justify-end">
                            <button class="bg-green-600 text-white px-6 py-2 rounded">
                                Save Account
                            </button>
                        </div>

                    </form>

                </div>
            </div>
            <h2 class="text-3xl font-bold mb-6 text-center">Admin Dashboard</h2>

            <!-- SUMMARY -->
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-600 text-white p-4 rounded">Total<br>{{ $totalUsers }}</div>
                <div class="bg-yellow-500 text-white p-4 rounded">Pending<br>{{ $pendingUsers->count() }}</div>
                <div class="bg-green-600 text-white p-4 rounded">Approved<br>{{ $approvedUsers }}</div>
                <div class="bg-red-600 text-white p-4 rounded">Rejected<br>{{ $rejectedUsers }}</div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                <!-- PCG Course Chart -->
                <div class="bg-white rounded-lg shadow p-5">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        PCG Personnel by Course
                    </h3>

                    <div style="height: 350px;">
                        <canvas id="courseChart"></canvas>
                    </div>
                </div>

                <!-- PCG Unit Chart -->
                <div class="bg-white rounded-lg shadow p-5">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        PCG Personnel by Unit
                    </h3>

                    <div style="height: 350px;">
                        <canvas id="unitChart"></canvas>
                    </div>
                </div>

            </div>
            <!-- TABLE -->
            <div class="bg-white p-6 rounded shadow">

                <h3 class="mb-3 font-bold">PCG Personels</h3>

                <table id="personnelTable" class="w-full">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Unit Assignment</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($usersWithCourse as $user)
                            <tr data-status="{{ $user->status }}" data-user='@json($user, JSON_HEX_APOS | JSON_HEX_QUOT)'>

                                <td>
                                    @if ($user->selfie_image)
                                        <img src="{{ \App\Support\Uploads::url($user->selfie_image) }}"
                                            class="w-10 h-10 rounded object-cover">
                                    @else
                                        <img src="{{ asset('images/no-photo.svg') }}"
                                            class="w-10 h-10 rounded object-cover">
                                    @endif
                                </td>

                                <td>{{ $user->name }}</td>

                                <td>{{ $user->course ?? 'N/A' }}</td>

                                <td>{{ $user->unit_assignment ?? 'N/A' }}</td>

                                <td>{{ $user->email }}</td>

                                <td class="font-semibold">
                                    {{ $user->status }}
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- ================= MODAL ================= -->
    <div id="userModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center">

        <div class="bg-white p-6 rounded w-96 relative">

            <button class="closeModal absolute right-3 top-3 text-red-600">✕</button>

            <img id="modalPhoto" class="w-24 h-24 rounded-full mx-auto mb-3">

            <h2 id="modalName" class="text-center font-bold"></h2>
            <p id="modalEmail" class="text-center text-sm text-gray-500"></p>

            <div class="mt-4 text-sm">
                <p><b>Course:</b> <span id="modalCourse"></span></p>
                <p><b>Unit:</b> <span id="modalUnit"></span></p>
                <p><b>Serial:</b> <span id="modalSerial"></span></p>
            </div>

        </div>
    </div>
    <script src="{{ asset('vendor/chartjs/chart-4.4.4.umd.min.js') }}"></script>
    <script>
        const courseLabels = @json($courseCounts->keys()->values());
        const courseData = @json($courseCounts->values());

        const unitLabels = @json($unitCounts->keys()->values());
        const unitData = @json($unitCounts->values());

        // PCG Course Chart
        new Chart(document.getElementById('courseChart'), {
            type: 'doughnut',
            data: {
                labels: courseLabels,
                datasets: [{
                    label: 'Personnel',
                    data: courseData,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // PCG Unit Chart
        new Chart(document.getElementById('unitChart'), {
            type: 'bar',
            data: {
                labels: unitLabels,
                datasets: [{
                    label: 'Personnel',
                    data: unitData,
                    borderWidth: 1
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
    </script>
    <script>
        @if (session('swal_success'))
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: "{{ session('swal_success') }}",
                confirmButtonColor: '#16a34a'
            });
        @endif
    </script>

    <!-- ================= SCRIPTS ================= -->
    <script>
        const FILES_URL = @json(\App\Support\Uploads::baseUrl());
        const NO_PHOTO_URL = @json(asset('images/no-photo.svg'));

        // Registration data is user-supplied: escape before inserting as HTML.
        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            })[c]);
        }

        $(document).ready(function() {

            // ================= DATA TABLE =================

            const table = $('#personnelTable').DataTable({
                pageLength: 10,

                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, 'All']
                ],

                order: [
                    [1, 'asc']
                ],

                columnDefs: [{
                    targets: 0,
                    orderable: false,
                    searchable: false
                }],

                language: {
                    search: "Search Personnel:",
                    searchPlaceholder: "Name, course, unit, email...",
                    lengthMenu: "Show _MENU_ personnel",
                    info: "Showing _START_ to _END_ of _TOTAL_ personnel",
                    emptyTable: "No personnel found"
                }
            });


            // ================= DOUBLE CLICK =================

            $('#personnelTable tbody').on('dblclick', 'tr', function() {

                let user = $(this).attr('data-user');

                if (!user) {
                    return;
                }

                try {
                    user = JSON.parse(user);
                } catch (error) {
                    console.error('Invalid user JSON:', error);
                    return;
                }


                // ================= APPROVE PENDING USER =================

                if (user.status === 'Pending') {

                    let photo = user.selfie_image ?
                        FILES_URL + user.selfie_image :
                        NO_PHOTO_URL;

                    Swal.fire({
                        title: 'Approve this user?',
                        html: `
                        <div style="text-align:center">

                            <img src="${escapeHtml(photo)}"
                                style="
                                    width:90px;
                                    height:90px;
                                    border-radius:50%;
                                    object-fit:cover;
                                    margin:10px auto;
                                    border:2px solid #16a34a;
                                ">

                            <h3 style="
                                margin-top:10px;
                                font-size:18px;
                                font-weight:bold;
                            ">
                                ${escapeHtml(user.name)}
                            </h3>

                            <p style="margin:2px 0;">
                                <b>Serial:</b>
                                ${escapeHtml(user.serial_number ?? 'N/A')}
                            </p>

                            <p style="margin:2px 0;">
                                <b>Unit:</b>
                                ${escapeHtml(user.unit_assignment ?? 'N/A')}
                            </p>

                            <p style="margin:2px 0;">
                                <b>Email:</b>
                                ${escapeHtml(user.email ?? 'N/A')}
                            </p>

                        </div>
                    `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#16a34a',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Approve'
                    }).then(function(result) {

                        if (result.isConfirmed) {

                            $.ajax({
                                url: `/admin/approve/${user.id}`,
                                type: 'POST',

                                data: {
                                    _token: '{{ csrf_token() }}'
                                },

                                success: function() {

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Approved!',
                                        text: 'User has been approved successfully.'
                                    }).then(function() {
                                        location.reload();
                                    });

                                },

                                error: function(xhr) {

                                    console.error(xhr);

                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'Unable to approve the user.'
                                    });

                                }
                            });

                        }

                    });

                    return;
                }


                // ================= SHOW USER MODAL =================

                $('#modalPhoto').attr(
                    'src',
                    user.selfie_image ?
                    FILES_URL + user.selfie_image :
                    NO_PHOTO_URL
                );

                $('#modalName').text(user.name || 'N/A');
                $('#modalEmail').text(user.email || 'N/A');
                $('#modalCourse').text(user.course || 'N/A');
                $('#modalUnit').text(user.unit_assignment || 'N/A');
                $('#modalSerial').text(user.serial_number || 'N/A');

                $('#userModal').removeClass('hidden');

            });


            // ================= CLOSE USER MODAL =================

            $('.closeModal').click(function() {
                $('#userModal').addClass('hidden');
            });


            // ================= OPEN UNIT MODAL =================

            $('#openUnitModalBtn').click(function() {
                $('#unitAccountModal').removeClass('hidden');
            });


            // ================= CLOSE UNIT MODAL =================

            $('#closeUnitModal').click(function() {
                $('#unitAccountModal').addClass('hidden');
            });


            // ================= CLOSE MODAL WHEN CLICKING OUTSIDE =================

            $('#unitAccountModal').click(function(e) {

                if (e.target === this) {
                    $('#unitAccountModal').addClass('hidden');
                }

            });

        });
    </script>

@endsection
