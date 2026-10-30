@extends('layouts.personnel')

@section('title', 'Dashboard')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Welcome aboard, {{ $user->name }}</h2>
                <p>{{ $user->unit_assignment ?: $user->mother_unit }} · Serial {{ $user->serial_number }}</p>
            </div>
        </div>

        @if ($rejectedCount > 0)
            <div class="pcg-alert pcg-alert--danger" role="alert">
                <strong>{{ $rejectedCount }} {{ \Illuminate\Support\Str::plural('document', $rejectedCount) }} {{ $rejectedCount === 1 ? 'needs' : 'need' }} your attention.</strong>
                See the reviewer's remarks below and upload a corrected copy under <a href="{{ route('upload.documents') }}">Required documents</a>.
            </div>
        @endif

        <div class="pcg-stats">
            <div class="pcg-stat">
                <span class="pcg-stat__label">Required documents</span>
                <span class="pcg-stat__value">{{ $documentsUploaded }} / {{ $documentsTotal }}</span>
                <span class="pcg-stat__hint">uploaded</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">Review status</span>
                <span class="pcg-stat__value">
                    @php $s = strtolower((string) $documentStatus); @endphp
                    @if ($s === 'approved')
                        <span class="pcg-badge pcg-badge--success">Approved</span>
                    @elseif ($rejectedCount > 0)
                        <span class="pcg-badge pcg-badge--danger">Action needed</span>
                    @elseif ($s === 'pending')
                        <span class="pcg-badge pcg-badge--warning">Under review</span>
                    @else
                        <span class="pcg-badge">Not submitted</span>
                    @endif
                </span>
                <span class="pcg-stat__hint">by your unit's document checker</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">MEPTDI classes</span>
                <span class="pcg-stat__value">{{ $classes->count() }}</span>
                <span class="pcg-stat__hint">enrolled</span>
            </div>
        </div>

        <div class="pcg-grid">
            <div>
                @if ($rejectedCount > 0)
                    <section class="pcg-card pcg-mb" aria-labelledby="rejected-heading">
                        <div class="pcg-card__header"><h3 id="rejected-heading">Documents returned by the reviewer</h3></div>
                        <ul class="pcg-list">
                            @foreach ($rejectedDocs as $doc)
                                <li>
                                    <strong>{{ $doc->doc_type }}</strong>
                                    <span class="pcg-table__muted">{{ $doc->comment ?: 'No comment provided' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="pcg-card" aria-labelledby="tasks-heading">
                    <div class="pcg-card__header"><h3 id="tasks-heading">What would you like to do?</h3></div>
                    <div class="pcg-card__body pcg-tiles">
                        <a class="pcg-tile" href="{{ route('profile') }}">
                            <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                            <strong>Update my SOI profile</strong>
                            <span>Personal details, education, assignments and awards.</span>
                        </a>
                        <a class="pcg-tile" href="{{ route('upload.documents') }}">
                            <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>
                            <strong>Upload required documents</strong>
                            <span>Clearances, medical, PFT and photos.</span>
                        </a>
                        <a class="pcg-tile" href="{{ route('upload.201') }}">
                            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                            <strong>Upload 201 files</strong>
                            <span>Appointment, leave, awards and other records.</span>
                        </a>
                        <a class="pcg-tile" href="{{ route('applicant.administrative.training') }}">
                            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                            <strong>Administrative training</strong>
                            <span>Courses for your rank and year in grade.</span>
                        </a>
                        <a class="pcg-tile" href="{{ route('applicant.operational.training') }}">
                            <i class="fa-solid fa-ship" aria-hidden="true"></i>
                            <strong>Operational training</strong>
                            <span>Operational career track courses.</span>
                        </a>
                        <a class="pcg-tile" href="{{ route('generate.soi') }}">
                            <i class="fa-solid fa-print" aria-hidden="true"></i>
                            <strong>Print my SOI</strong>
                            <span>Statement of Information, ready to print.</span>
                        </a>
                    </div>
                </section>
            </div>

            <section class="pcg-card" aria-labelledby="classes-heading">
                <div class="pcg-card__header"><h3 id="classes-heading">My MEPTDI classes</h3></div>
                @if ($classes->isEmpty())
                    <p class="pcg-empty">You are not enrolled in a MEPTDI class yet.</p>
                @else
                    <ul class="pcg-list">
                        @foreach ($classes as $class)
                            <li>
                                <strong>{{ $class->course_name ?? 'Course' }}</strong>
                                <span class="pcg-table__muted">
                                    {{ $class->date_attended ? \Illuminate\Support\Carbon::parse($class->date_attended)->format('M d, Y') : '—' }}
                                    @if ($class->date_ended) – {{ \Illuminate\Support\Carbon::parse($class->date_ended)->format('M d, Y') }}@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection
