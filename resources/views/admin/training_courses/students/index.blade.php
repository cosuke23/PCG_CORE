@extends('layouts.admin')

@section('title', 'Training Course Students')

@section('styles')

<link rel="stylesheet"
    href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

<link rel="stylesheet"
    href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">

<script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>

<script src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>

@endsection

@section('content')

<script src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>

<div class="flex min-h-screen bg-gray-100">

    @include('admin.partials.lms-menu')


    <main class="flex-1 p-10">


        <div class="flex justify-between items-center mb-6">

            <div>

                <h2 class="text-3xl font-bold text-gray-800">

                    <i class="fas fa-users text-blue-600 mr-2"></i>

                    Training Course Students

                </h2>


                <p class="text-gray-500 mt-1">

                    Manage students enrolled in this training course

                </p>

            </div>


            <div class="flex items-center gap-2">


                {{-- Back to Training Courses --}}
                <a href="{{ route('admin.training-courses.index') }}"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2.5 rounded-lg shadow transition">

                    <i class="fas fa-arrow-left mr-2"></i>

                    Back to Courses

                </a>



                {{-- Add Student --}}
                <a href="{{ route('training-courses.students.create', $course->id) }}"
                    class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg shadow transition">

                    <i class="fas fa-user-plus mr-2"></i>

                    Add Student

                </a>

            </div>

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

                <i class="fas fa-exclamation-triangle mr-2"></i>

                Please check the following:

            </div>


            <ul class="list-disc list-inside text-sm">

                @foreach($errors->all() as $error)

                <li>

                    {{ $error }}

                </li>

                @endforeach

            </ul>

        </div>

        @endif

        <div class="bg-white p-6 rounded-lg shadow mb-6">


            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5">

                <div class="flex-1">


                    <div class="flex items-center gap-3">


                        {{-- Course Icon --}}
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">

                            <i class="fas fa-graduation-cap text-xl"></i>

                        </div>



                        {{-- Course Name --}}
                        <div>

                            <h3 class="text-xl font-bold text-gray-800">

                                {{ $course->course_name }}

                            </h3>


                            <p class="text-sm text-gray-500 mt-1">

                                Training Course

                            </p>

                        </div>

                    </div>



                    {{-- Description --}}
                    @if($course->description)

                    <p class="text-gray-600 text-sm mt-4 max-w-3xl">

                        {{ $course->description }}

                    </p>

                    @else

                    <p class="text-gray-400 text-sm mt-4">

                        No description available.

                    </p>

                    @endif

                </div>


                <div class="bg-purple-100 rounded-lg px-6 py-4 min-w-[180px]">


                    <div class="flex items-center justify-between gap-5">


                        <div>

                            <p class="text-sm text-purple-600">

                                Total Students

                            </p>


                            <p class="text-3xl font-bold text-purple-700 mt-1">

                                {{ $students->count() }}

                            </p>

                        </div>


                        <i class="fas fa-users text-3xl text-purple-500"></i>

                    </div>

                </div>

            </div>

        </div>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

            <div class="bg-blue-600 text-white p-5 rounded-lg shadow">


                <div class="flex items-center justify-between">


                    <div>

                        <p class="text-sm opacity-80">

                            Total Students

                        </p>


                        <p class="text-3xl font-bold mt-1">

                            {{ $students->count() }}

                        </p>

                    </div>


                    <i class="fas fa-users text-3xl opacity-80"></i>

                </div>

            </div>


            <div class="bg-green-600 text-white p-5 rounded-lg shadow">


                <div class="flex items-center justify-between">


                    <div>

                        <p class="text-sm opacity-80">

                            Enrolled

                        </p>


                        <p class="text-3xl font-bold mt-1">

                            {{ $students->count() }}

                        </p>

                    </div>


                    <i class="fas fa-user-check text-3xl opacity-80"></i>

                </div>

            </div>


            <div class="bg-purple-600 text-white p-5 rounded-lg shadow">


                <div class="flex items-center justify-between">


                    <div>

                        <p class="text-sm opacity-80">

                            Course Status

                        </p>


                        <p class="text-xl font-bold mt-2">

                            {{ $course->status }}

                        </p>

                    </div>


                    <i class="fas fa-certificate text-3xl opacity-80"></i>

                </div>

            </div>

        </div>



        <div class="bg-white p-6 rounded-lg shadow">


            <div class="flex justify-between items-center mb-4">


                <div>

                    <h3 class="text-xl font-bold text-gray-800">

                        <i class="fas fa-users mr-2 text-blue-600"></i>

                        Enrolled Students

                    </h3>


                    <p class="text-sm text-gray-500 mt-1">

                        List of personnel enrolled in

                        <strong>

                            {{ $course->course_name }}

                        </strong>

                    </p>

                </div>



                {{-- Student Count --}}
                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">

                    {{ $students->count() }} Students

                </span>

            </div>


            <div class="overflow-x-auto">


                <table id="studentsTable"
                    class="w-full">


                    <thead>

                        <tr>

                            <th>#</th>

                            <th>
                                Personnel ID
                            </th>

                            <th>
                                Name
                            </th>

                            <th>
                                Rank
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Enrolled
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        @foreach($students as $student)


                        <tr>

                            <td>

                                {{ $loop->iteration }}

                            </td>


                            <td>

                                <span class="font-medium text-gray-700">

                                    {{ $student->serial_number ?: 'N/A' }}

                                </span>

                            </td>

                            <td>

                                <div class="font-semibold text-gray-800">

                                    {{ $student->full_name ?: ($student->personnel->name ?? 'N/A') }}

                                </div>

                            </td>


                            <td>

                                {{ $student->personnel->rank ?? 'N/A' }}

                            </td>

                            <td>


                                <span class="inline-flex items-center bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">


                                    <i class="fas fa-check-circle mr-2"></i>


                                    Enrolled


                                </span>


                            </td>


                            <td>

                                {{ $student->created_at
                                ? $student->created_at->format('M d, Y')
                                : 'N/A'
                            }}

                            </td>


                            <td>


                                <div class="flex items-center gap-1">



                                    <form action="{{ route('training-courses.students.destroy', [$course, $student]) }}"
                                        method="POST"
                                        class="remove-student-form">


                                        @csrf

                                        @method('DELETE')


                                        <button type="submit"
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded"
                                            title="Remove Student">


                                            <i class="fa-solid fa-trash"></i>


                                        </button>


                                    </form>


                                </div>


                            </td>


                        </tr>


                        @endforeach


                    </tbody>


                </table>


            </div>


        </div>


    </main>
    ```

</div>


<script nonce="{{ $cspNonce ?? '' }}">
    $(document).ready(function() {


        /*
        |--------------------------------------------------------------------------
        | Students DataTable
        |--------------------------------------------------------------------------
        */

        $('#studentsTable').DataTable({


            pageLength: 10,


            lengthMenu: [

                [10, 25, 50, 100, -1],

                [10, 25, 50, 100, 'All']

            ],


            order: [

                [0, 'asc']

            ],


            columnDefs: [

                {

                    targets: 6,

                    orderable: false,

                    searchable: false

                }

            ],


            language: {


                search: 'Search Students:',


                searchPlaceholder: 'Personnel ID, name, rank...',


                lengthMenu: 'Show _MENU_ students',


                info: 'Showing _START_ to _END_ of _TOTAL_ students',


                infoEmpty: 'Showing 0 to 0 of 0 students',


                zeroRecords: 'No matching students found',


                emptyTable: 'No students enrolled in this training course.'

            },


            responsive: true,


            autoWidth: false

        });



        /*
        |--------------------------------------------------------------------------
        | Remove Student Confirmation
        |--------------------------------------------------------------------------
        */

        $('.remove-student-form').on('submit', function(e) {


            e.preventDefault();


            const form = this;


            Swal.fire({


                title: 'Remove Student?',


                text: 'This will remove the student from this training course.',


                icon: 'warning',


                showCancelButton: true,


                confirmButtonColor: '#dc2626',


                cancelButtonColor: '#6b7280',


                confirmButtonText: 'Yes, Remove',


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