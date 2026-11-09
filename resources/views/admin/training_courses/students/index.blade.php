@extends('layouts.admin')

@section('title', 'Course students')

@php
    $studentCount = $students->count();
    $courseActive = strcasecmp((string) $course->status, 'Active') === 0;
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables-2.3.2.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/training-courses.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <header class="pcg-page__header">
            <div>
                <h2>{{ $studentCount }} {{ \Illuminate\Support\Str::plural('student', $studentCount) }} in {{ $course->course_name }}</h2>
                <p class="tc-lead">
                    @if ($courseActive)
                        <span class="pcg-badge pcg-badge--success">Active course</span>
                    @else
                        <span class="pcg-badge">Inactive course</span>
                    @endif
                    {{ $course->description }}
                </p>
            </div>
            <div class="pcg-actions">
                <a href="{{ route('admin.training-courses.index') }}" class="pcg-btn pcg-btn--ghost">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All courses
                </a>
                <a href="{{ route('training-courses.students.create', $course->id) }}" class="pcg-btn pcg-btn--cta">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add students
                </a>
            </div>
        </header>

        <section class="pcg-card" aria-labelledby="students-heading">
            <div class="pcg-card__header">
                <h3 id="students-heading">Enrolled students</h3>
            </div>

            @if ($students->isEmpty())
                <p class="pcg-empty">
                    No one is enrolled in this course yet.
                    <a href="{{ route('training-courses.students.create', $course->id) }}">Add students</a> one by one or from a CSV file.
                </p>
            @else
                <div class="tc-table-body">
                    <table id="studentsTable" class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Serial number</th>
                                <th scope="col">Rank</th>
                                <th scope="col">Enrolled</th>
                                <th scope="col"><span class="pcg-visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                @php($studentName = $student->full_name ?: ($student->personnel->name ?? 'Name not recorded'))
                                <tr>
                                    <td><strong>{{ $studentName }}</strong></td>
                                    <td>
                                        @if ($student->serial_number)
                                            <span class="pcg-mono">{{ $student->serial_number }}</span>
                                        @else
                                            <span class="pcg-table__muted">Not recorded</span>
                                        @endif
                                    </td>
                                    <td>{{ $student->personnel->rank ?? '' }}</td>
                                    <td class="pcg-nowrap" data-order="{{ $student->created_at?->format('Y-m-d H:i:s') }}">
                                        @if ($student->created_at)
                                            <time datetime="{{ $student->created_at->toDateString() }}">{{ $student->created_at->format('M d, Y') }}</time>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="tc-row-actions">
                                            <form action="{{ route('training-courses.students.destroy', [$course, $student]) }}"
                                                method="POST" class="remove-student-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="pcg-btn pcg-btn--ghost pcg-btn--sm tc-remove"
                                                    title="Remove {{ $studentName }} from this course"
                                                    aria-label="Remove {{ $studentName }} from this course">
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
            if ($('#studentsTable').length) {
                $('#studentsTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [
                        [10, 25, 50, 100, -1],
                        [10, 25, 50, 100, 'All']
                    ],
                    order: [], // most recently enrolled first, as the server sends them
                    columnDefs: [{
                        targets: 4,
                        orderable: false,
                        searchable: false
                    }],
                    language: {
                        search: 'Search students:',
                        searchPlaceholder: 'Name, serial number, rank',
                        lengthMenu: 'Show _MENU_ students',
                        info: 'Showing _START_ to _END_ of _TOTAL_ students',
                        infoEmpty: 'No students to show',
                        zeroRecords: 'No student matches that search',
                        emptyTable: 'No students enrolled in this training course.'
                    },
                    responsive: true,
                    autoWidth: false
                });
            }

            // Remove student confirmation
            $('.remove-student-form').on('submit', function (e) {
                e.preventDefault();
                const form = this;
                const button = form.querySelector('[type="submit"]');

                Swal.fire({
                    title: 'Remove student?',
                    text: 'This will remove the student from this training course.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, remove',
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
