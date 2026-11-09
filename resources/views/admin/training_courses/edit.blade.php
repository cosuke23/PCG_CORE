@extends('layouts.admin')

@section('title', 'Edit training course')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/training-courses.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <header class="pcg-page__header">
            <div>
                <h2>Edit {{ $trainingCourse->course_name }}</h2>
                @php($enrolled = $trainingCourse->students()->count())
                <p><a href="{{ route('training-courses.students.index', $trainingCourse->id) }}">{{ $enrolled }} {{ \Illuminate\Support\Str::plural('student', $enrolled) }} enrolled</a></p>
            </div>
            <div class="pcg-actions">
                <a href="{{ route('admin.training-courses.index') }}" class="pcg-btn pcg-btn--ghost">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All courses
                </a>
            </div>
        </header>

        <section class="pcg-card tc-form" aria-label="Course details">
            <div class="pcg-card__body">
                <form action="{{ route('admin.training-courses.update', $trainingCourse->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="pcg-field">
                        <label for="course_name" class="pcg-label">Course name <span class="tc-required" aria-hidden="true">*</span></label>
                        <input type="text" id="course_name" name="course_name" value="{{ old('course_name', $trainingCourse->course_name) }}"
                            class="pcg-input" maxlength="255" required
                            @error('course_name') aria-invalid="true" aria-describedby="course_name-error" @enderror>
                        @error('course_name')
                            <p class="pcg-error" id="course_name-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pcg-field">
                        <label for="description" class="pcg-label">Description</label>
                        <textarea id="description" name="description" rows="5" class="pcg-input"
                            aria-describedby="description-help @error('description') description-error @enderror">{{ old('description', $trainingCourse->description) }}</textarea>
                        <p class="pcg-help" id="description-help">Optional. Shown under the course name in the course list.</p>
                        @error('description')
                            <p class="pcg-error" id="description-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pcg-field">
                        <label for="status" class="pcg-label">Status</label>
                        <select id="status" name="status" class="pcg-input" aria-describedby="status-help">
                            <option value="Active" {{ old('status', $trainingCourse->status) === 'Active' ? 'selected' : '' }}>Active</option>
                            <option value="Inactive" {{ old('status', $trainingCourse->status) === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        <p class="pcg-help" id="status-help">Setting a course inactive keeps its students enrolled; only deleting the course removes them.</p>
                        @error('status')
                            <p class="pcg-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="tc-form-actions">
                        <button type="submit" class="pcg-btn pcg-btn--cta">
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save changes
                        </button>
                        <a href="{{ route('admin.training-courses.index') }}" class="pcg-btn pcg-btn--secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
