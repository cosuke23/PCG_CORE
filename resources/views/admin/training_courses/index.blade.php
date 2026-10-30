@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('styles')

<link rel="stylesheet"
    href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

<link rel="stylesheet"
    href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">

<script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>

<script src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
```

@endsection


@section('content')

<script src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>

<div class="flex min-h-screen bg-gray-100">

    @include('admin.partials.lms-menu')

    <main class="flex-1 p-10">

        <div class="flex justify-between items-center mb-6">

            <div>
                <h2 class="text-3xl font-bold text-gray-800">
                    Training Courses
                </h2>

                <p class="text-gray-500 mt-1">
                    Manage MEPCOM-HRMIS training courses
                </p>
            </div>

            <a href="{{ route('admin.training-courses.create') }}"
                class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow transition">

                <i class="fas fa-plus mr-2"></i>
                Add Training Course

            </a>

        </div>


        @if(session('success'))
        <div class="mb-6 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg">

            <i class="fas fa-check-circle mr-2"></i>

            {{ session('success') }}

        </div>
        @endif


        {{-- ================= VALIDATION ERRORS ================= --}}
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


        {{-- ================= SUMMARY ================= --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

            {{-- Total Courses --}}
            <div class="bg-blue-600 text-white p-5 rounded-lg shadow">

                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm opacity-80">
                            Total Courses
                        </p>

                        <p class="text-3xl font-bold mt-1">
                            {{ $trainingCourses->count() }}
                        </p>
                    </div>

                    <i class="fas fa-graduation-cap text-3xl opacity-80"></i>

                </div>

            </div>


            {{-- Active Courses --}}
            <div class="bg-green-600 text-white p-5 rounded-lg shadow">

                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm opacity-80">
                            Active Courses
                        </p>

                        <p class="text-3xl font-bold mt-1">
                            {{ $trainingCourses->where('status', 'Active')->count() }}
                        </p>
                    </div>

                    <i class="fas fa-check-circle text-3xl opacity-80"></i>

                </div>

            </div>


            {{-- Total Students --}}
            <div class="bg-purple-600 text-white p-5 rounded-lg shadow">

                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm opacity-80">
                            Total Students
                        </p>

                        <p class="text-3xl font-bold mt-1">
                            {{ $trainingCourses->sum('students_count') }}
                        </p>
                    </div>

                    <i class="fas fa-users text-3xl opacity-80"></i>

                </div>

            </div>

        </div>


        {{-- ================= TRAINING COURSE TABLE ================= --}}
        <div class="bg-white p-6 rounded-lg shadow">

            <div class="flex justify-between items-center mb-4">

                <div>
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>
                        Training Course List
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        List of registered MEPCOM-HRMIS training courses
                    </p>
                </div>

                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">
                    {{ $trainingCourses->count() }} Courses
                </span>

            </div>


            <div class="overflow-x-auto">

                <table id="trainingCoursesTable"
                    class="w-full">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Training Course</th>
                            <th>Description</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>

                    </thead>


                    <tbody>

                        @forelse($trainingCourses as $course)

                        <tr>

                            {{-- Number --}}
                            <td>
                                {{ $loop->iteration }}
                            </td>


                            {{-- Course --}}
                            <td>

                                <div class="font-semibold text-gray-800">
                                    {{ $course->course_name }}
                                </div>

                            </td>


                            {{-- Description --}}
                            <td>

                                <div class="max-w-md text-gray-600">

                                    {{ $course->description ?: 'No description available' }}

                                </div>

                            </td>


                            {{-- Students --}}
                            <td>

                                <span class="inline-flex items-center bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">

                                    <i class="fas fa-users mr-2"></i>

                                    {{ $course->students_count }}

                                </span>

                            </td>


                            {{-- Status --}}
                            <td>

                                @if($course->status === 'Active')

                                <span class="inline-flex items-center bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">

                                    <i class="fas fa-check-circle mr-2"></i>

                                    Active

                                </span>

                                @else

                                <span class="inline-flex items-center bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-semibold">

                                    <i class="fas fa-ban mr-2"></i>

                                    Inactive

                                </span>

                                @endif

                            </td>


                            {{-- Created --}}
                            <td>

                                {{ $course->created_at->format('M d, Y') }}

                            </td>


                            {{-- Actions --}}
                            <td>

                                <div class="flex items-center gap-1">

                                    {{-- View Students --}}
                                    <a href="{{ route('training-courses.students.index', $course->id) }}"
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded inline-flex items-center justify-center"
                                        title="View Students">

                                        <i class="fa-solid fa-users"></i>
                                    </a>

                                    <a href="{{ route('admin.training-courses.edit', $course->id) }}"
                                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded"
                                        title="Edit">

                                        <i class="fa-solid fa-pen-to-square"></i>

                                    </a>

                                    <form action="{{ route('admin.training-courses.destroy', $course->id) }}"
                                        method="POST"
                                        class="delete-course-form">

                                        @csrf
                                        @method('DELETE')

                                        <button type="submit"
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded"
                                            title="Delete">

                                            <i class="fa-solid fa-trash"></i>

                                        </button>

                                    </form>

                                </div>


                            </td>

                        </tr>

                        @empty

                        <tr>

                            <td colspan="7"
                                class="text-center py-8 text-gray-500">

                                <i class="fas fa-graduation-cap text-4xl mb-3 block text-gray-300"></i>

                                No training courses found.

                            </td>

                        </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>


{{-- ================= SCRIPTS ================= --}}
<script>
    $(document).ready(function() {

        $('#trainingCoursesTable').DataTable({

            pageLength: 10,

            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ],

            order: [
                [0, 'asc']
            ],

            columnDefs: [{
                targets: 6,
                orderable: false,
                searchable: false
            }],

            language: {
                search: 'Search Courses:',
                searchPlaceholder: 'Course name, description...',
                lengthMenu: 'Show _MENU_ courses',
                info: 'Showing _START_ to _END_ of _TOTAL_ courses',
                emptyTable: 'No training courses found'
            },

            responsive: true,
            autoWidth: false

        });


        // Delete confirmation
        $('.delete-course-form').on('submit', function(e) {

            e.preventDefault();

            const form = this;

            Swal.fire({

                title: 'Delete Training Course?',

                text: 'This will also delete all students associated with this course.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonColor: '#dc2626',

                cancelButtonColor: '#6b7280',

                confirmButtonText: 'Yes, Delete',

                cancelButtonText: 'Cancel'

            }).then((result) => {

                if (result.isConfirmed) {

                    form.submit();

                }

            });

        });

    });
</script>

@endsection