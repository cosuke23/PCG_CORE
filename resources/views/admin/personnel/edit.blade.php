@extends('layouts.admin')

@section('title', 'Edit ' . $person->full_name)

@section('content')
    @php
        $v = fn ($f) => old($f, $person->$f instanceof \DateTimeInterface ? $person->$f->format('Y-m-d') : $person->$f);
        $opt = '<span class="pcg-table__muted" style="display:inline">(optional)</span>';
    @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <p><a href="{{ route('admin.personnel.index') }}">Personnel</a> / <a href="{{ route('admin.personnel.show', $person->id) }}">{{ $person->serial_number }}</a> / Edit</p>
                <h2>Edit details</h2>
                <p>The serial number cannot be changed here. Rank changes are recorded as promotions and unit changes as assignments, so their history is kept.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.personnel.update', $person->id) }}" class="pcg-card" data-pcg-busy>
            @csrf
            <div class="pcg-card__body pcg-form-grid">
                @foreach ([['last_name', 'Last name', true], ['first_name', 'First name', true], ['middle_name', 'Middle name', false], ['suffix', 'Suffix', false]] as [$f, $label, $req])
                    <div class="pcg-field">
                        <label class="pcg-label" for="{{ $f }}">{{ $label }} {!! $req ? '' : $opt !!}</label>
                        <input class="pcg-input" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}" @if ($req) required @endif maxlength="100">
                    </div>
                @endforeach
                @foreach ([['date_of_birth', 'Date of birth'], ['date_entered_service', 'Date entered service']] as [$f, $label])
                    <div class="pcg-field">
                        <label class="pcg-label" for="{{ $f }}">{{ $label }} {!! $opt !!}</label>
                        <input class="pcg-input" type="date" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}">
                    </div>
                @endforeach
                <div class="pcg-field"><label class="pcg-label" for="email">Email {!! $opt !!}</label>
                    <input class="pcg-input" type="email" id="email" name="email" value="{{ $v('email') }}" maxlength="255"></div>
                <div class="pcg-field"><label class="pcg-label" for="contact_number">Contact number {!! $opt !!}</label>
                    <input class="pcg-input" id="contact_number" name="contact_number" value="{{ $v('contact_number') }}" maxlength="50"></div>
                <div class="pcg-field"><label class="pcg-label" for="service_status">Service status</label>
                    <select class="pcg-input" id="service_status" name="service_status">
                        @foreach (\App\Models\Personnel::STATUSES as $k => $label)<option value="{{ $k }}" @selected($v('service_status') === $k)>{{ $label }}</option>@endforeach
                    </select></div>
            </div>
            <div class="pcg-card__body">
                <div class="pcg-field">
                    <label class="pcg-label" for="change_reason">Reason for the change</label>
                    <input class="pcg-input" id="change_reason" name="change_reason" value="{{ old('change_reason') }}" required maxlength="500" placeholder="e.g. Correction per birth certificate">
                    <p class="pcg-help">Kept in the audit log with the previous values.</p>
                </div>
                <div class="pcg-actions">
                    <button type="submit" class="pcg-btn pcg-btn--primary" data-busy-label="Saving…">Save changes</button>
                    <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.personnel.show', $person->id) }}">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection
