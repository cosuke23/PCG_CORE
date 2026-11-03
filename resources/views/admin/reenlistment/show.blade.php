@extends('layouts.admin')

@section('title', 'Re-enlistment · ' . $person->name)

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <p><a href="{{ url()->previous() !== url()->current() && str_contains(url()->previous(), '/admin/reenlistment') ? url()->previous() : route('admin.reenlistment.index') }}">← Back to the ETE watchlist</a></p>
                <h2>{{ $person->rank }} {{ $person->name }}</h2>
                <p>Serial number {{ $person->serial_number ?: '—' }} · non-officer ·
                    <span class="pcg-badge {{ $person->ete_tone ? 'pcg-badge--' . $person->ete_tone : '' }}">{{ $person->ete_label }}</span></p>
            </div>
            <div class="pcg-actions pcg-no-print">
                @if ($personnelId && \App\Support\AdminPermissions::can('personnel.view'))
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.personnel.show', $personnelId) }}">Open personnel record</a>
                @endif
                <button type="button" class="pcg-btn pcg-btn--secondary" data-on-click="pcg.print">Print</button>
            </div>
        </div>

        <div class="pcg-grid">
            <section class="pcg-card" aria-labelledby="term-heading">
                <div class="pcg-card__header"><h3 id="term-heading">Term of enlistment</h3></div>
                <div class="pcg-card__body">
                    <dl class="pcg-dl">
                        <div><dt>Date of appointment</dt><dd>{{ $person->date_of_appointment ? \Carbon\Carbon::parse($person->date_of_appointment)->format('d M Y') : 'Not recorded' }}</dd></div>
                        <div><dt>ETE (end of term of enlistment)</dt><dd>{{ $person->ete ? \Carbon\Carbon::parse($person->ete)->format('d M Y') : 'Not recorded' }}</dd></div>
                        <div><dt>Time to ETE</dt><dd>{{ $person->ete_label }} (as of {{ $today->format('d M Y') }})</dd></div>
                        <div><dt>Command / unit</dt><dd>{{ $person->mother_unit ?: 'Not recorded' }}</dd></div>
                        <div><dt>Unit assignment</dt><dd>{{ $person->unit_assignment ?: 'Not recorded' }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="pcg-card" aria-labelledby="account-heading">
                <div class="pcg-card__header"><h3 id="account-heading">Account and re-enlistment form</h3></div>
                <div class="pcg-card__body">
                    <dl class="pcg-dl">
                        <div><dt>Account status</dt><dd>{{ ucfirst(strtolower((string) $person->status)) ?: 'Not recorded' }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $person->email }}</dd></div>
                        <div><dt>Registered</dt><dd>{{ $person->created_at ? \Carbon\Carbon::parse($person->created_at)->format('d M Y') : '—' }}</dd></div>
                        <div><dt>Re-enlistment form</dt><dd>
                            @if ($form)
                                <span class="pcg-badge pcg-badge--success">Submitted</span>
                                {{ $form->updated_at ? 'last saved ' . \Carbon\Carbon::parse($form->updated_at)->format('d M Y') : '' }}
                            @else
                                <span class="pcg-badge">Not yet submitted</span>
                            @endif
                        </dd></div>
                        @if ($form)
                            <div><dt>Name on the form</dt><dd>{{ trim(($form->rank ?? '') . ' ' . ($form->first_name ?? '') . ' ' . ($form->middle_name ?? '') . ' ' . ($form->last_name ?? '') . ' ' . ($form->extension_name ?? '')) ?: '—' }}</dd></div>
                        @endif
                        @if ($personal)
                            <div><dt>Contact number</dt><dd>{{ $personal->contact_number ?: '—' }}</dd></div>
                            <div><dt>Civil status</dt><dd>{{ $personal->civil_status ?: '—' }}</dd></div>
                        @endif
                    </dl>
                </div>
            </section>
        </div>
    </div>
@endsection
