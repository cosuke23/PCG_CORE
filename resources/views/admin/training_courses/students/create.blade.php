@extends('layouts.admin')

@section('title', 'Add Students')

@section('styles')

<link rel="stylesheet"
    href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

@endsection

@section('content')

<div class="flex min-h-screen bg-gray-100">

    ```
    {{-- SIDEBAR --}}
    @include('admin.partials.lms-menu')


    {{-- MAIN CONTENT --}}
    <main class="flex-1 p-10">


        {{-- HEADER --}}
        <div class="flex justify-between items-center mb-6">

            <div>

                <h2 class="text-3xl font-bold text-gray-800">

                    <i class="fas fa-user-plus text-green-600 mr-2"></i>

                    Add Students

                </h2>

                <p class="text-gray-500 mt-1">

                    Enroll personnel in the selected training course

                </p>

            </div>


            <a href="{{ route('training-courses.students.index', $course->id) }}"
                class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2.5 rounded-lg shadow transition">

                <i class="fas fa-arrow-left mr-2"></i>

                Back to Students

            </a>

        </div>


        {{-- COURSE INFORMATION --}}
        <div class="bg-white p-6 rounded-lg shadow mb-6">

            <div class="flex items-center gap-4">

                <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">

                    <i class="fas fa-graduation-cap text-2xl"></i>

                </div>


                <div>

                    <p class="text-sm text-gray-500">
                        Training Course
                    </p>

                    <h3 class="text-xl font-bold text-gray-800">

                        {{ $course->course_name }}

                    </h3>


                    @if($course->description)

                    <p class="text-sm text-gray-500 mt-1">

                        {{ $course->description }}

                    </p>

                    @endif

                </div>

            </div>

        </div>


        {{-- ADD STUDENT CARD --}}
        <div class="bg-white rounded-lg shadow">


            {{-- CARD HEADER --}}
            <div class="p-6 border-b">

                <h3 class="text-xl font-bold text-gray-800">

                    <i class="fas fa-user-plus text-green-600 mr-2"></i>

                    Enroll Student

                </h3>

                <p class="text-sm text-gray-500 mt-1">

                    Add a personnel to this training course.

                </p>

            </div>


            {{-- FORM --}}
            <div class="p-6">

                <form action="{{ route('training-courses.students.store', $course) }}"
                    method="POST">

                    @csrf


                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">


                        {{-- PERSONNEL --}}
                        <div>

                            <label class="block text-sm font-semibold text-gray-700 mb-2">

                                Personnel

                            </label>

                            <div>

                                <label class="block text-sm font-semibold text-gray-700 mb-2">

                                    Select Personnel

                                </label>


                                <select name="serial_number"
                                    id="serial_number"
                                    required
                                    class="w-full border border-gray-300 rounded-lg px-4 py-3
               focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

                                    <option value="">

                                        -- Select Personnel --

                                    </option>


                                    @foreach($personnel as $person)

                                    <option value="{{ $person->user_serial_number }}" @selected(old('serial_number') === $person->user_serial_number)>

                                        {{ $person->rank ?? '' }}

                                        {{ $person->name }}

                                        @if($person->serial_number)

                                        — {{ $person->serial_number }}

                                        @endif

                                    </option>

                                    @endforeach

                                </select>


                                @error('serial_number')

                                <p class="text-red-600 text-sm mt-1">

                                    {{ $message }}

                                </p>

                                @enderror

                            </div>


                            @error('serial_number')

                            <p class="text-red-600 text-sm mt-1">

                                {{ $message }}

                            </p>

                            @enderror

                        </div>


                        {{-- COURSE --}}
                        <div>

                            <label class="block text-sm font-semibold text-gray-700 mb-2">

                                Training Course

                            </label>


                            <input type="text"
                                value="{{ $course->course_name }}"
                                disabled
                                class="w-full bg-gray-100 border border-gray-300 rounded-lg px-4 py-3 text-gray-600">


                            <input type="hidden"
                                name="course_id"
                                value="{{ $course->id }}">

                        </div>

                    </div>


                    {{-- BUTTONS --}}
                    <div class="flex justify-end gap-2 mt-6">

                        <a href="{{ route('training-courses.students.index', $course->id) }}"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2.5 rounded-lg">

                            Cancel

                        </a>


                        <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg">

                            <i class="fas fa-user-plus mr-2"></i>

                            Enroll Student

                        </button>

                    </div>

                </form>

            </div>

        </div>


        {{-- CSV IMPORT --}}
        <div class="bg-white rounded-lg shadow mt-6">


            <div class="p-6 border-b">

                <h3 class="text-xl font-bold text-gray-800">

                    <i class="fas fa-file-csv text-blue-600 mr-2"></i>

                    Bulk Student Import

                </h3>

                <p class="text-sm text-gray-500 mt-1">

                    Import multiple students using a CSV file.

                </p>

            </div>


            <div class="p-6">

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-5">

                    <div class="flex gap-3">

                        <i class="fas fa-circle-info text-blue-600 mt-1"></i>

                        <div>

                            <p class="font-semibold text-blue-800">

                                CSV Import

                            </p>

                            <p class="text-sm text-blue-700 mt-1">

                                Download the CSV template, enter the personnel
                                information, and upload it here.

                            </p>

                        </div>

                    </div>

                </div>


                <div class="flex flex-wrap gap-3">


                    {{-- DOWNLOAD TEMPLATE --}}
                    <a href="{{ route('training-courses.students.csv-template', ['course' => $course->id]) }}"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg inline-flex items-center">

                        <i class="fas fa-download mr-2"></i>

                        Download CSV Template

                    </a>


                    {{-- UPLOAD CSV --}}
                    <button
                        type="button"
                        data-on-click="pcg.show" data-args='["#csvUploadModal"]'
                        class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg">

                        <i class="fas fa-file-import mr-2"></i>

                        Upload CSV
                    </button>

                </div>

            </div>

        </div>

    </main>
    {{-- CSV UPLOAD MODAL --}}
    <div id="csvUploadModal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden
           flex items-center justify-center z-50">

        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">

            {{-- Modal Header --}}
            <div class="flex justify-between items-center mb-5">

                <h2 class="text-xl font-bold text-gray-800">
                    Upload Students CSV
                </h2>

                <button
                    type="button"
                    data-on-click="pcg.hide" data-args='["#csvUploadModal"]'
                    class="text-gray-500 hover:text-red-500 text-2xl">

                    &times;

                </button>

            </div>

            {{-- Upload Form --}}
            <form
                action="{{ route('import-csv', ['course' => $course->id]) }}"
                method="POST"
                enctype="multipart/form-data">

                @csrf

                <div class="mb-5">

                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Select CSV File
                    </label>

                    <input
                        type="file"
                        name="csv_file"
                        accept=".csv,.txt"
                        required
                        class="block w-full text-sm text-gray-700
                   border border-gray-300 rounded-lg
                   cursor-pointer bg-gray-50
                   focus:outline-none">

                    <p class="mt-2 text-xs text-gray-500">
                        CSV format: <strong>serial_number, full_name</strong>
                    </p>

                </div>

                <div class="flex justify-end gap-3">

                    {{-- Cancel --}}
                    <button
                        type="button"
                        data-on-click="pcg.hide" data-args='["#csvUploadModal"]'
                        class="px-4 py-2 bg-gray-500 hover:bg-gray-600
                   text-white rounded-lg">

                        Cancel

                    </button>

                    {{-- Upload --}}
                    <button
                        type="submit"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700
                   text-white rounded-lg">

                        <i class="fas fa-upload mr-2"></i>

                        Import CSV

                    </button>

                </div>

            </form>
        </div>

    </div>

</div>

@endsection