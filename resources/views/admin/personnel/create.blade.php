@extends('layouts.admin')

@section('title', 'Add personnel')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <p><a href="{{ route('admin.personnel.index') }}">Personnel</a> / New record</p>
                <h2>Add personnel</h2>
                <p>Check first that the person has no record yet: the serial number must be unique.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.personnel.store') }}" data-pcg-busy class="pcg-grid">
            @csrf
            <section class="pcg-card" aria-labelledby="basic-heading">
                <div class="pcg-card__header"><h3 id="basic-heading">Identity and service</h3></div>
                <div class="pcg-card__body pcg-form-grid">
                    <div class="pcg-field">
                        <label class="pcg-label" for="serial_number">Serial number</label>
                        <input class="pcg-input" id="serial_number" name="serial_number" value="{{ old('serial_number') }}" required maxlength="100" autocomplete="off">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="rank_id">Rank</label>
                        <select class="pcg-input" id="rank_id" name="rank_id" required>
                            <option value="">Select a rank</option>
                            @foreach ($ranks->groupBy('classification') as $class => $group)
                                <optgroup label="{{ \App\Models\Rank::CLASSIFICATIONS[$class] ?? $class }}">
                                    @foreach ($group as $rank)
                                        <option value="{{ $rank->id }}" @selected((string) old('rank_id') === (string) $rank->id)>{{ $rank->name }} ({{ $rank->code }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="last_name">Last name</label>
                        <input class="pcg-input" id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="100">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="first_name">First name</label>
                        <input class="pcg-input" id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="100">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="middle_name">Middle name <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                        <input class="pcg-input" id="middle_name" name="middle_name" value="{{ old('middle_name') }}" maxlength="100">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="suffix">Suffix <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                        <input class="pcg-input" id="suffix" name="suffix" value="{{ old('suffix') }}" maxlength="20">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="date_of_birth">Date of birth <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                        <input class="pcg-input" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="date_entered_service">Date entered service <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                        <input class="pcg-input" type="date" id="date_entered_service" name="date_entered_service" value="{{ old('date_entered_service') }}">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="rank_date">Date of rank <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                        <input class="pcg-input" type="date" id="rank_date" name="rank_date" value="{{ old('rank_date') }}">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="email">Email <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                        <input class="pcg-input" type="email" id="email" name="email" value="{{ old('email') }}" maxlength="255">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="contact_number">Contact number <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                        <input class="pcg-input" id="contact_number" name="contact_number" value="{{ old('contact_number') }}" maxlength="50">
                    </div>
                </div>
            </section>

            <div class="pcg-stack">
                <section class="pcg-card" aria-labelledby="assign-heading">
                    <div class="pcg-card__header"><h3 id="assign-heading">Initial assignment{{ $orgWide ? ' (optional)' : '' }}</h3></div>
                    <div class="pcg-card__body">
                        <div class="pcg-field">
                            <label class="pcg-label" for="org_unit_id">Unit</label>
                            <select class="pcg-input" id="org_unit_id" name="org_unit_id" @unless ($orgWide) required @endunless>
                                <option value="">{{ $orgWide ? 'Not yet assigned' : 'Select a unit' }}</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}" @selected((string) old('org_unit_id') === (string) $u->id)>{{ str_repeat('— ', $u->depth) }}{{ $u->code }} · {{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="position">Position / designation <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                            <input class="pcg-input" id="position" name="position" value="{{ old('position') }}" maxlength="255">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="start_date">Effective date</label>
                            <input class="pcg-input" type="date" id="start_date" name="start_date" value="{{ old('start_date') }}">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="order_reference">Order / reference no. <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                            <input class="pcg-input" id="order_reference" name="order_reference" value="{{ old('order_reference') }}" maxlength="255">
                        </div>
                    </div>
                </section>

                <section class="pcg-card">
                    <div class="pcg-card__body">
                        @if (session('possible_duplicate'))
                            <div class="pcg-field">
                                <label class="pcg-check">
                                    <input type="checkbox" name="confirm_not_duplicate" value="1">
                                    I checked: this is a different person from the existing record(s) with the same name.
                                </label>
                            </div>
                        @endif
                        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Saving…">Create record</button>
                    </div>
                </section>
            </div>
        </form>
    </div>
@endsection
