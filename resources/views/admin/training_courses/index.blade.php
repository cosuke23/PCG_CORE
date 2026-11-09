@extends('layouts.admin')

@section('title', 'Training courses')

@php
    $courseCount = $trainingCourses->count();
    $activeCount = $trainingCourses->filter(fn ($c) => strcasecmp((string) $c->status, 'Active') === 0)->count();
    $studentCount = (int) $trainingCourses->sum('students_count');
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/training-courses.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <header class="pcg-page__header">
            <div>
                <h2>{{ $courseCount }} {{ \Illuminate\Support\Str::plural('training course', $courseCount) }}, {{ $activeCount }} active</h2>
                <p>{{ number_format($studentCount) }} {{ \Illuminate\Support\Str::plural('student', $studentCount) }} enrolled across all courses. Open a course to see and add its students.</p>
            </div>
            <div class="pcg-actions">
                <a href="{{ route('admin.training-courses.create') }}" class="pcg-btn pcg-btn--cta">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add training course
                </a>
            </div>
        </header>

        <section class="pcg-card" aria-labelledby="courses-heading">
            <div class="pcg-card__header">
                <h3 id="courses-heading">Courses</h3>
            </div>

            @if ($trainingCourses->isEmpty())
                <p class="pcg-empty">
                    No training courses yet.
                    <a href="{{ route('admin.training-courses.create') }}">Add the first course</a>, then enrol students in it.
                </p>
            @else
                <div class="tc-table-body">
                    <table id="trainingCoursesTable" class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">Course</th>
                                <th scope="col">Students</th>
                                <th scope="col">Status</th>
                                <th scope="col">Created</th>
                                <th scope="col"><span class="pcg-visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trainingCourses as $course)
                                <tr>
                                    <td>
                                        <a class="tc-course-link" href="{{ route('training-courses.students.index', $course->id) }}">{{ $course->course_name }}</a>
                                        @if ($course->description)
                                            <span class="pcg-table__muted tc-desc">{{ $course->description }}</span>
                                        @endif
                                    </td>
                                    <td class="pcg-num" data-order="{{ (int) $course->students_count }}">{{ $course->students_count }}</td>
                                    <td>
                                        @if (strcasecmp((string) $course->status, 'Active') === 0)
                                            <span class="pcg-badge pcg-badge--success">Active</span>
                                        @else
                                            <span class="pcg-badge">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="pcg-nowrap" data-order="{{ $course->created_at?->format('Y-m-d H:i:s') }}">
                                        @if ($course->created_at)
                                            <time datetime="{{ $course->created_at->toDateString() }}">{{ $course->created_at->format('M d, Y') }}</time>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="tc-row-actions">
                                            <a href="{{ route('training-courses.students.index', $course->id) }}"
                                                class="pcg-btn pcg-btn--secondary pcg-btn--sm">
                                                <i class="fa-solid fa-users" aria-hidden="true"></i> Students
                                            </a>
                                            <a href="{{ route('admin.training-courses.edit', $course->id) }}"
                                                class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                                title="Edit {{ $course->course_name }}" aria-label="Edit {{ $course->course_name }}">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            </a>
                                            <form action="{{ route('admin.training-courses.destroy', $course->id) }}"
                                                method="POST" class="delete-course-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="pcg-btn pcg-btn--ghost pcg-btn--sm tc-remove"
                                                    title="Delete {{ $course->course_name }}" aria-label="Delete {{ $course->course_name }}">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/datatables/dataTables-2.3.2.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}">
        $(document).ready(function () {
            if ($('#trainingCoursesTable').length) {
                $('#trainingCoursesTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, 'All']
                    ],
                    order: [], // newest first, as the server sends them
                    columnDefs: [{
                        targets: 4,
                        orderable: false,
                        searchable: false
                    }],
                    language: {
                        search: 'Search courses:',
                        searchPlaceholder: 'Course name or description',
                        lengthMenu: 'Show _MENU_ courses',
                        info: 'Showing _START_ to _END_ of _TOTAL_ courses',
                        infoEmpty: 'No courses to show',
                        zeroRecords: 'No course matches that search',
                        emptyTable: 'No training courses yet'
                    },
                    responsive: true,
                    autoWidth: false
                });
            }

            // Delete confirmation
            $('.delete-course-form').on('submit', function (e) {
                e.preventDefault();
                const form = this;
                const button = form.querySelector('[type="submit"]');

                Swal.fire({
                    title: 'Delete training course?',
                    text: 'This will also delete all students associated with this course.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (button && window.pcgBusy) window.pcgBusy(button, true);
                        form.submit();
                    }
                });
            });
        });
    </script>
@endsection
