@extends('layouts.admin')

@section('title', 'Adjutant')

@section('content')
    @php($date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->timezone('Asia/Manila')->format('d M Y') : '')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ trim(($member->rank ? $member->rank . ' ' : '') . $member->name) }}</h2>
                <p>Serial number {{ $member->serial_number }}{{ $member->mother_unit ? ' · ' . $member->mother_unit : '' }}{{ $member->unit_assignment ? ' · ' . $member->unit_assignment : '' }}</p>
            </div>
            <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.adjutant.index') }}">Back to members</a>
        </div>

        <p class="pcg-help">What you add here appears in the member's SOI right away: awards under <strong>1. Coast Guard, a. Awards and Decorations</strong>, schooling under <strong>3. Trainings / Seminars, a. Coast Guard</strong>. Members cannot change these entries.</p>

        @if ($errors->any())
            <div class="pcg-alert pcg-alert--danger" role="alert">
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="pcg-grid pcg-grid--even">
            {{-- 1.a Coast Guard Awards and Decorations --}}
            <section class="pcg-card" aria-labelledby="aw-h">
                <div class="pcg-card__header"><h3 id="aw-h">Awards and decorations</h3></div>
                <div class="pcg-card__body">
                    <form method="POST" action="{{ route('admin.adjutant.awards.store', $member->id) }}" class="pcg-stack">
                        @csrf
                        <div class="pcg-field">
                            <label class="pcg-label" for="aw-type">Award or decoration</label>
                            <select class="pcg-input" id="aw-type" name="type" required>
                                @include('applicant.partials.cg-award-options')
                            </select>
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="aw-auth">Authority</label>
                            <input class="pcg-input" id="aw-auth" name="authority" value="{{ old('authority') }}" maxlength="255" required placeholder="e.g. General Orders No. 123, s. 2026">
                        </div>
                        <button type="submit" class="pcg-btn pcg-btn--primary">Add award</button>
                    </form>
                </div>
                @if ($awards->isEmpty())
                    <p class="pcg-empty">No Coast Guard awards recorded yet.</p>
                @else
                    <div class="pcg-table-wrap">
                        <table class="pcg-table">
                            <thead><tr><th scope="col">Award</th><th scope="col">Authority</th><th scope="col">Recorded</th><th scope="col"><span class="pcg-visually-hidden">Actions</span></th></tr></thead>
                            <tbody>
                                @foreach ($awards as $a)
                                    <tr>
                                        <td>{{ $a->type ?: '—' }}</td>
                                        <td>{{ $a->authority ?: '—' }}</td>
                                        <td>{{ isset($a->added_by) && $a->added_by ? 'Adjutant, ' . $date($a->created_at) : 'By the member (before this module)' }}</td>
                                        <td>
                                            @if (isset($a->added_by) && $a->added_by)
                                                <form method="POST" action="{{ route('admin.adjutant.awards.destroy', [$member->id, $a->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="pcg-btn pcg-btn--ghost pcg-btn--sm" data-pcg-confirm="Remove this award from the SOI?">Remove</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- 3.a Trainings / Seminars: Coast Guard --}}
            <section class="pcg-card" aria-labelledby="tr-h">
                <div class="pcg-card__header"><h3 id="tr-h">Coast Guard schooling</h3></div>
                <div class="pcg-card__body">
                    <form method="POST" action="{{ route('admin.adjutant.trainings.store', $member->id) }}" class="pcg-stack">
                        @csrf
                        <div class="pcg-form-grid">
                            <div class="pcg-field">
                                <label class="pcg-label" for="tr-type">Training type</label>
                                <select class="pcg-input" id="tr-type" name="type" required>
                                    <option value="">Select a type</option>
                                    @foreach ($trainingTypes as $t)
                                        <option @selected(old('type') === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="tr-course">Course</label>
                                <input class="pcg-input" id="tr-course" name="course" value="{{ old('course') }}" maxlength="255" required>
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="tr-auth">Authority (order)</label>
                                <input class="pcg-input" id="tr-auth" name="authority" value="{{ old('authority') }}" maxlength="255">
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="tr-school">School / provider</label>
                                <input class="pcg-input" id="tr-school" name="school" value="{{ old('school') }}" maxlength="255">
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="tr-period">Period</label>
                                <input class="pcg-input" id="tr-period" name="period" value="{{ old('period') }}" maxlength="100" placeholder="e.g. 06 Jan – 28 Feb 2026">
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="tr-remarks">Remarks</label>
                                <input class="pcg-input" id="tr-remarks" name="remarks" value="{{ old('remarks') }}" maxlength="1000">
                            </div>
                        </div>
                        <button type="submit" class="pcg-btn pcg-btn--primary">Add schooling</button>
                    </form>
                </div>
                @if ($trainings->isEmpty())
                    <p class="pcg-empty">No Coast Guard schooling recorded yet.</p>
                @else
                    <div class="pcg-table-wrap">
                        <table class="pcg-table">
                            <thead><tr><th scope="col">Course</th><th scope="col">Type</th><th scope="col">Authority</th><th scope="col">School</th><th scope="col">Period</th><th scope="col">Recorded</th><th scope="col"><span class="pcg-visually-hidden">Actions</span></th></tr></thead>
                            <tbody>
                                @foreach ($trainings as $t)
                                    <tr>
                                        <td>{{ $t->course ?: '—' }}@if ($t->remarks)<span class="pcg-table__muted">{{ $t->remarks }}</span>@endif</td>
                                        <td>{{ $t->type ?: '—' }}</td>
                                        <td>{{ $t->authority ?: '—' }}</td>
                                        <td>{{ $t->school ?: '—' }}</td>
                                        <td>{{ $t->period ?: '—' }}</td>
                                        <td>{{ isset($t->added_by) && $t->added_by ? 'Adjutant, ' . $date($t->created_at) : 'By the member (before this module)' }}</td>
                                        <td>
                                            @if (isset($t->added_by) && $t->added_by)
                                                <form method="POST" action="{{ route('admin.adjutant.trainings.destroy', [$member->id, $t->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="pcg-btn pcg-btn--ghost pcg-btn--sm" data-pcg-confirm="Remove this schooling from the SOI?">Remove</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
