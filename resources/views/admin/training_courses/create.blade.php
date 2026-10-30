@extends('layouts.admin')

@section('title', 'Add Training Course')

@section('content')

    <div class="flex min-h-screen bg-gray-100">

        ```
        @include('admin.partials.lms-menu')

        <main class="flex-1 p-10">

            <div class="mb-6">

                <h2 class="text-3xl font-bold text-gray-800">
                    Add Training Course
                </h2>

                <p class="text-gray-500 mt-1">
                    Create a new MEPCOM-HRMIS training course
                </p>

            </div>

            @if ($errors->any())

                <div class="mb-6 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg">

                    <div class="font-semibold mb-2">
                        Please check the following:
                    </div>

                    <ul class="list-disc list-inside text-sm">

                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach

                    </ul>

                </div>

            @endif

            <div class="bg-white rounded-lg shadow">

                <div class="bg-blue-600 text-white px-6 py-4 rounded-t-lg">

                    <h3 class="text-lg font-semibold">
                        <i class="fas fa-graduation-cap mr-2"></i>
                        Training Course Information
                    </h3>

                </div>

                <div class="p-6">

                    <form action="{{ route('admin.training-courses.store') }}" method="POST">

                        @csrf

                        <div class="mb-5">

                            <label for="course_name" class="block text-sm font-semibold text-gray-700 mb-2">

                                Training Course Name
                                <span class="text-red-600">*</span>

                            </label>

                            <input type="text" id="course_name" name="course_name" value="{{ old('course_name') }}"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('course_name') border-red-500 @enderror"
                                placeholder="Enter training course name" required>

                            @error('course_name')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        <div class="mb-5">

                            <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">

                                Description

                            </label>

                            <textarea id="description" name="description" rows="5"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter course description">{{ old('description') }}</textarea>

                            @error('description')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        <div class="mb-6">

                            <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">

                                Status

                            </label>

                            <select id="status" name="status"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

                                <option value="Active" {{ old('status', 'Active') === 'Active' ? 'selected' : '' }}>
                                    Active
                                </option>

                                <option value="Inactive" {{ old('status') === 'Inactive' ? 'selected' : '' }}>
                                    Inactive
                                </option>

                            </select>

                            @error('status')
                                <p class="mt-1 text-sm text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror

                        </div>

                        <div class="flex items-center gap-3 pt-4 border-t">

                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg shadow transition">

                                <i class="fas fa-save mr-2"></i>
                                Save Training Course

                            </button>

                            <a href="{{ route('admin.training-courses.index') }}"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2.5 rounded-lg shadow transition">

                                <i class="fas fa-arrow-left mr-2"></i>
                                Cancel

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </main>
        ```

    </div>

@endsection
