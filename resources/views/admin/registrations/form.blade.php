@extends('layouts.admin')

@section('title', $registration ? 'Edit registration' : 'New registration')

@section('content')
    @php
        $v = fn ($f) => old($f, $registration ? ($registration->$f instanceof \DateTimeInterface ? $registration->$f->format('Y-m-d') : $registration->$f) : null);
        $opt = '<span class="pcg-table__muted" style="display:inline">(optional)</span>';
    @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <p><a href="{{ route('admin.registrations.index') }}">Registrations</a> / {{ $registration ? $registration->serial_number : 'New' }}</p>
                <h2>{{ $registration ? 'Edit registration' : 'New personnel registration' }}</h2>
                <p>Save as a draft at any time. Submitting checks for duplicates and sends it for approval.</p>
            </div>
        </div>

        <form method="POST" action="{{ $registration ? route('admin.registrations.update', $registration->id) : route('admin.registrations.store') }}" class="pcg-grid" data-pcg-busy>
            @csrf
            <section class="pcg-card" aria-labelledby="id-heading">
                <div class="pcg-card__header"><h3 id="id-heading">Identity and service</h3></div>
                <div class="pcg-card__body pcg-form-grid">
                    <div class="pcg-field">
                        <label class="pcg-label" for="serial_number">Serial number</label>
                        <input class="pcg-input" id="serial_number" name="serial_number" value="{{ $v('serial_number') }}" required maxlength="100" autocomplete="off">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="rank_id">Rank</label>
                        <select class="pcg-input" id="rank_id" name="rank_id">
                            <option value="">Select a rank</option>
                            @foreach ($ranks->groupBy('classification') as $class => $group)
                                <optgroup label="{{ \App\Models\Rank::CLASSIFICATIONS[$class] ?? $class }}">
                                    @foreach ($group as $rank)
                                        <option value="{{ $rank->id }}" @selected((string) $v('rank_id') === (string) $rank->id)>{{ $rank->name }} ({{ $rank->code }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    @foreach ([['last_name', 'Last name', true], ['first_name', 'First name', true], ['middle_name', 'Middle name', false], ['suffix', 'Suffix', false]] as [$f, $label, $req])
                        <div class="pcg-field">
                            <label class="pcg-label" for="{{ $f }}">{{ $label }} {!! $req ? '' : $opt !!}</label>
                            <input class="pcg-input" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}" @if ($req) required @endif maxlength="100">
                        </div>
                    @endforeach
                    @foreach ([['date_of_birth', 'Date of birth'], ['date_entered_service', 'Date entered service'], ['rank_date', 'Date of rank']] as [$f, $label])
                        <div class="pcg-field">
                            <label class="pcg-label" for="{{ $f }}">{{ $label }} {!! $opt !!}</label>
                            <input class="pcg-input" type="date" id="{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}">
                        </div>
                    @endforeach
                    <div class="pcg-field">
                        <label class="pcg-label" for="email">Email {!! $opt !!}</label>
                        <input class="pcg-input" type="email" id="email" name="email" value="{{ $v('email') }}" maxlength="255">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="contact_number">Contact number {!! $opt !!}</label>
                        <input class="pcg-input" id="contact_number" name="contact_number" value="{{ $v('contact_number') }}" maxlength="50">
                    </div>
                </div>
            </section>

            <div class="pcg-stack">
                <section class="pcg-card" aria-labelledby="as-heading">
                    <div class="pcg-card__header"><h3 id="as-heading">Proposed assignment</h3></div>
                    <div class="pcg-card__body">
                        <div class="pcg-field">
                            <label class="pcg-label" for="org_unit_id">Unit</label>
                            <select class="pcg-input" id="org_unit_id" name="org_unit_id" required>
                                <option value="">Select a unit</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}" @selected((string) $v('org_unit_id') === (string) $u->id)>{{ str_repeat('— ', $u->depth) }}{{ $u->code }} · {{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="position">Position / designation {!! $opt !!}</label>
                            <input class="pcg-input" id="position" name="position" value="{{ $v('position') }}" maxlength="255">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="assignment_start_date">Effective date</label>
                            <input class="pcg-input" type="date" id="assignment_start_date" name="assignment_start_date" value="{{ $v('assignment_start_date') }}">
                            <p class="pcg-help">Required to submit.</p>
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="order_reference">Order / reference no. {!! $opt !!}</label>
                            <input class="pcg-input" id="order_reference" name="order_reference" value="{{ $v('order_reference') }}" maxlength="255">
                        </div>
                    </div>
                </section>
                <section class="pcg-card">
                    <div class="pcg-card__body">
                        <div class="pcg-field">
                            <label class="pcg-check">
                                <input type="hidden" name="confirmed_not_duplicate" value="0">
                                <input type="checkbox" name="confirmed_not_duplicate" value="1" @checked($v('confirmed_not_duplicate'))>
                                I checked that this person has no record yet, even if someone with the same name exists.
                            </label>
                        </div>
                        <div class="pcg-actions">
                            <button type="submit" name="intent" value="draft" class="pcg-btn pcg-btn--secondary" data-busy-label="Saving…">Save draft</button>
                            <button type="submit" name="intent" value="submit" class="pcg-btn pcg-btn--primary" data-busy-label="Submitting…">Save and submit</button>
                        </div>
                    </div>
                </section>
            </div>
        </form>
    </div>
@endsection
