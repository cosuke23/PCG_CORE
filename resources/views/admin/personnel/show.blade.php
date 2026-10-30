@extends('layouts.admin')

@section('title', $person->full_name)

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <p><a href="{{ route('admin.personnel.index') }}">Personnel</a> / {{ $person->serial_number }}</p>
                <h2>{{ $person->rank?->code }} {{ $person->full_name }}</h2>
            </div>
        </div>

        <div class="pcg-grid">
            <div class="pcg-stack">
                <section class="pcg-card" aria-labelledby="info-heading">
                    <div class="pcg-card__header"><h3 id="info-heading">Personnel record</h3></div>
                    <div class="pcg-card__body">
                        <dl class="pcg-dl">
                            <div><dt>Serial number</dt><dd>{{ $person->serial_number }}</dd></div>
                            <div><dt>Name</dt><dd>{{ $person->full_name }}</dd></div>
                            <div><dt>Rank</dt><dd>{{ $person->rank ? $person->rank->name . ' (' . $person->rank->code . ')' : 'Not recorded' }}{{ $person->rank_date ? ', since ' . $person->rank_date->format('d M Y') : '' }}</dd></div>
                            <div><dt>Classification</dt><dd>{{ \App\Models\Rank::CLASSIFICATIONS[$person->classification] ?? 'Not recorded' }}</dd></div>
                            <div><dt>Date of birth</dt><dd>{{ $person->date_of_birth?->format('d M Y') ?? 'Not recorded' }}</dd></div>
                            <div><dt>Entered service</dt><dd>{{ $person->date_entered_service?->format('d M Y') ?? 'Not recorded' }}</dd></div>
                            <div><dt>Contact</dt><dd>{{ collect([$person->email, $person->contact_number])->filter()->implode(' · ') ?: 'Not recorded' }}</dd></div>
                            <div><dt>Status</dt><dd>{{ \App\Models\Personnel::STATUSES[$person->service_status] ?? $person->service_status }}</dd></div>
                            <div><dt>Personnel login</dt><dd>{{ $person->user ? $person->user->email . ' (' . $person->user->status . ')' : 'None linked' }}</dd></div>
                            <div><dt>Record source</dt><dd>{{ $person->source === 'legacy_users' ? 'Copied from registration' : 'Created by HR' }}</dd></div>
                        </dl>
                    </div>
                </section>

                <section class="pcg-card" aria-labelledby="history-heading">
                    <div class="pcg-card__header"><h3 id="history-heading">Assignment history</h3></div>
                    @if ($person->assignments->isEmpty())
                        <p class="pcg-empty">No assignments recorded yet.</p>
                    @else
                        <div class="pcg-table-wrap">
                            <table class="pcg-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Unit</th>
                                        <th scope="col">Position</th>
                                        <th scope="col">Period</th>
                                        <th scope="col">Order / reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($person->assignments as $a)
                                        <tr>
                                            <td>
                                                <strong>{{ $a->orgUnit->code }}</strong>
                                                <span class="pcg-table__muted">{{ $a->orgUnit->parent?->code }}</span>
                                            </td>
                                            <td>{{ $a->position ?: '—' }}</td>
                                            <td>
                                                {{ $a->start_date->format('d M Y') }} – {{ $a->end_date?->format('d M Y') ?? 'present' }}
                                                @if (!$a->end_date) <span class="pcg-badge pcg-badge--success">Current</span> @endif
                                            </td>
                                            <td>{{ $a->order_reference ?: '—' }}@if ($a->remarks)<span class="pcg-table__muted">{{ $a->remarks }}</span>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <section class="pcg-card" aria-labelledby="assign-heading">
                <div class="pcg-card__header"><h3 id="assign-heading">{{ $person->assignments->firstWhere('end_date', null) ? 'Transfer / reassign' : 'Assign' }}</h3></div>
                <div class="pcg-card__body">
                    @if ($units->isEmpty())
                        <p class="pcg-empty">No active units in your scope.</p>
                    @else
                        <form method="POST" action="{{ route('admin.personnel.assign', $person->id) }}" data-pcg-busy>
                            @csrf
                            <div class="pcg-field">
                                <label class="pcg-label" for="org_unit_id">New unit</label>
                                <select class="pcg-input" id="org_unit_id" name="org_unit_id" required>
                                    <option value="">Select a unit</option>
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
                                <input class="pcg-input" type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                                <p class="pcg-help">The current assignment ends the day before.</p>
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="order_reference">Order / reference no. <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                                <input class="pcg-input" id="order_reference" name="order_reference" value="{{ old('order_reference') }}" maxlength="255">
                            </div>
                            <div class="pcg-field">
                                <label class="pcg-label" for="remarks">Remarks <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                                <textarea class="pcg-input" id="remarks" name="remarks" rows="2" maxlength="2000">{{ old('remarks') }}</textarea>
                            </div>
                            <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Saving…"
                                data-pcg-confirm="Record this assignment? The current assignment will be closed (kept in the history).">Record assignment</button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
