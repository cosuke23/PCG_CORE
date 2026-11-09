@extends('layouts.admin')

@section('title', 'Add students')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/training-courses.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <header class="pcg-page__header">
            <div>
                <h2>Enrol students in {{ $course->course_name }}</h2>
                <p>Pick one person from the list, or import many at once from a CSV file.</p>
            </div>
            <div class="pcg-actions">
                <a href="{{ route('training-courses.students.index', $course->id) }}" class="pcg-btn pcg-btn--ghost">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Course students
                </a>
            </div>
        </header>

        <div class="pcg-grid pcg-grid--even">
            {{-- one person --}}
            <section class="pcg-card" aria-labelledby="enrol-heading">
                <div class="pcg-card__header">
                    <h3 id="enrol-heading">Enrol one person</h3>
                </div>
                <div class="pcg-card__body">
                    <form action="{{ route('training-courses.students.store', $course) }}" method="POST">
                        @csrf
                        <input type="hidden" name="course_id" value="{{ $course->id }}">

                        <div class="pcg-field">
                            <label for="serial_number" class="pcg-label">Personnel</label>
                            <select name="serial_number" id="serial_number" required class="pcg-input"
                                aria-describedby="serial_number-help @error('serial_number') serial_number-error @enderror">
                                <option value="">Select a person</option>
                                @foreach ($personnel as $person)
                                    <option value="{{ $person->user_serial_number }}" @selected(old('serial_number') === $person->user_serial_number)>{{ trim(($person->rank ?? '') . ' ' . $person->name) }}@if ($person->serial_number) — {{ $person->serial_number }}@endif</option>
                                @endforeach
                            </select>
                            <p class="pcg-help" id="serial_number-help">Listed as rank, name and serial number. Only members with an approved account can be enrolled.</p>
                            @error('serial_number')
                                <p class="pcg-error" id="serial_number-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="tc-form-actions">
                            <button type="submit" class="pcg-btn pcg-btn--cta">
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Enrol student
                            </button>
                            <a href="{{ route('training-courses.students.index', $course->id) }}" class="pcg-btn pcg-btn--secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </section>

            {{-- many people from a CSV file --}}
            <section class="pcg-card" aria-labelledby="import-heading">
                <div class="pcg-card__header">
                    <h3 id="import-heading">Import from a CSV file</h3>
                </div>
                <div class="pcg-card__body">
                    <ol class="tc-steps">
                        <li>Download the template.</li>
                        <li>Fill in one person per row: <code>serial_number</code>, <code>full_name</code>.</li>
                        <li>Upload the file.</li>
                    </ol>
                    <div class="pcg-actions">
                        <a href="{{ route('training-courses.students.csv-template', ['course' => $course->id]) }}"
                            class="pcg-btn pcg-btn--secondary">
                            <i class="fa-solid fa-download" aria-hidden="true"></i> Download CSV template
                        </a>
                        <button type="button" class="pcg-btn pcg-btn--secondary"
                            data-on-click="pcg.show" data-args='["#csvUploadModal"]'
                            aria-haspopup="dialog" aria-controls="csvUploadModal">
                            <i class="fa-solid fa-file-import" aria-hidden="true"></i> Upload CSV
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    {{-- CSV upload dialog --}}
    <div id="csvUploadModal" class="tc-modal hidden" role="dialog" aria-modal="true" aria-labelledby="csv-upload-heading">
        <div class="pcg-card tc-modal__panel">
            <div class="pcg-card__header tc-modal__head">
                <h3 id="csv-upload-heading">Upload students CSV</h3>
                <button type="button" class="pcg-btn pcg-btn--ghost tc-modal__close"
                    data-on-click="pcg.hide" data-args='["#csvUploadModal"]' aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="pcg-card__body">
                <form action="{{ route('import-csv', ['course' => $course->id]) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="pcg-field">
                        <label for="csv_file" class="pcg-label">CSV file</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" required class="pcg-input"
                            aria-describedby="csv_file-help">
                        <p class="pcg-help" id="csv_file-help">Columns: <code>serial_number</code>, <code>full_name</code>.</p>
                    </div>
                    <div class="tc-form-actions">
                        <button type="submit" class="pcg-btn pcg-btn--primary">
                            <i class="fa-solid fa-upload" aria-hidden="true"></i> Import CSV
                        </button>
                        <button type="button" class="pcg-btn pcg-btn--secondary"
                            data-on-click="pcg.hide" data-args='["#csvUploadModal"]'>Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
