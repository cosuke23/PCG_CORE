@extends('layouts.admin')

@section('title', $person->full_name)

@section('content')
    @php
        $canEdit = \App\Support\AdminPermissions::can('personnel.edit');
        $canUpload = \App\Support\AdminPermissions::can('documents.upload');
        $canVerify = \App\Support\AdminPermissions::can('documents.verify');
        $opt = '<span class="pcg-table__muted" style="display:inline">(optional)</span>';
        $current = $person->assignments->firstWhere('end_date', null);
    @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <p><a href="{{ route('admin.personnel.index') }}">Personnel</a> / {{ $person->serial_number }}</p>
                <h2>{{ $person->rank?->code }} {{ $person->full_name }}</h2>
                <p>{{ $current ? 'Assigned to ' . $current->orgUnit->code . ($current->position ? ' — ' . $current->position : '') : 'No current assignment' }}</p>
            </div>
            @if ($canEdit)
                <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.personnel.edit', $person->id) }}">Edit details</a>
            @endif
        </div>

        <nav class="pcg-tabs" aria-label="Sections">
            <a href="#record">Record</a><a href="#assignments">Assignments</a><a href="#ranks">Rank history</a>
            <a href="#trainings">Training</a><a href="#documents">201 documents</a>
        </nav>

        <div class="{{ $canEdit || $canUpload ? 'pcg-grid' : 'pcg-stack' }} pcg-mt">
            <div class="pcg-stack">
                <section class="pcg-card" id="record" aria-labelledby="info-heading">
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
                            <div><dt>Record source</dt><dd>{{ ['legacy_users' => 'Copied from self-registration', 'registration' => 'Approved HR registration', 'manual' => 'Created by HR'][$person->source] ?? $person->source }}</dd></div>
                        </dl>
                    </div>
                </section>

                <section class="pcg-card" id="assignments" aria-labelledby="history-heading">
                    <div class="pcg-card__header"><h3 id="history-heading">Assignment history</h3></div>
                    @if ($person->assignments->isEmpty())
                        <p class="pcg-empty">No assignments recorded yet.</p>
                    @else
                        <div class="pcg-table-wrap">
                            <table class="pcg-table">
                                <thead><tr><th scope="col">Unit</th><th scope="col">Position</th><th scope="col">Period</th><th scope="col">Order / reference</th></tr></thead>
                                <tbody>
                                    @foreach ($person->assignments as $a)
                                        <tr>
                                            <td><strong>{{ $a->orgUnit->code }}</strong><span class="pcg-table__muted">{{ $a->orgUnit->parent?->code }}</span></td>
                                            <td>{{ $a->position ?: '—' }}</td>
                                            <td>{{ $a->start_date->format('d M Y') }} – {{ $a->end_date?->format('d M Y') ?? 'present' }}
                                                @if (!$a->end_date) <span class="pcg-badge pcg-badge--success">Current</span> @endif</td>
                                            <td>{{ $a->order_reference ?: '—' }}@if ($a->remarks)<span class="pcg-table__muted">{{ $a->remarks }}</span>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="pcg-card" id="ranks" aria-labelledby="rank-heading">
                    <div class="pcg-card__header"><h3 id="rank-heading">Rank history</h3></div>
                    @if ($person->promotions->isEmpty())
                        <p class="pcg-empty">No promotions recorded. Current rank: {{ $person->rank?->code ?? 'not recorded' }}.</p>
                    @else
                        <div class="pcg-table-wrap">
                            <table class="pcg-table">
                                <thead><tr><th scope="col">Effective</th><th scope="col">From</th><th scope="col">To</th><th scope="col">Order / reference</th></tr></thead>
                                <tbody>
                                    @foreach ($person->promotions as $pr)
                                        <tr>
                                            <td>{{ $pr->effective_date->format('d M Y') }}</td>
                                            <td>{{ $pr->fromRank?->code ?? '—' }}</td>
                                            <td><strong>{{ $pr->toRank->code }}</strong></td>
                                            <td>{{ $pr->order_reference ?: '—' }}@if ($pr->remarks)<span class="pcg-table__muted">{{ $pr->remarks }}</span>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="pcg-card" id="trainings" aria-labelledby="tr-heading">
                    <div class="pcg-card__header"><h3 id="tr-heading">Training and qualifications</h3></div>
                    @if ($person->trainings->isEmpty())
                        <p class="pcg-empty">No training or qualifications recorded.</p>
                    @else
                        <div class="pcg-table-wrap">
                            <table class="pcg-table">
                                <thead><tr><th scope="col">Title</th><th scope="col">Provider</th><th scope="col">Dates</th><th scope="col">Valid until</th></tr></thead>
                                <tbody>
                                    @foreach ($person->trainings as $t)
                                        <tr>
                                            <td><strong>{{ $t->title }}</strong><span class="pcg-table__muted">{{ \App\Models\PersonnelTraining::KINDS[$t->kind] ?? $t->kind }}{{ $t->certificate_number ? ' · ' . $t->certificate_number : '' }}</span></td>
                                            <td>{{ $t->provider ?: '—' }}</td>
                                            <td>{{ $t->start_date?->format('d M Y') ?? '—' }} – {{ $t->end_date?->format('d M Y') ?? '—' }}</td>
                                            <td>
                                                @if ($t->valid_until)
                                                    {{ $t->valid_until->format('d M Y') }}
                                                    @if ($t->valid_until->isPast()) <span class="pcg-badge pcg-badge--danger">Expired</span>
                                                    @elseif ($t->valid_until->lte(now()->addDays(60))) <span class="pcg-badge pcg-badge--warning">Expires soon</span> @endif
                                                @else — @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="pcg-card" id="documents" aria-labelledby="doc-heading">
                    <div class="pcg-card__header"><h3 id="doc-heading">201 documents</h3></div>
                    @if ($hiddenDocuments)
                        <p class="pcg-help pcg-card__body">{{ $hiddenDocuments }} restricted {{ \Illuminate\Support\Str::plural('document', $hiddenDocuments) }} not shown (needs the restricted-documents permission).</p>
                    @endif
                    @if ($documents->isEmpty())
                        <p class="pcg-empty">No documents uploaded.</p>
                    @else
                        <div class="pcg-table-wrap">
                            <table class="pcg-table">
                                <thead><tr><th scope="col">Document</th><th scope="col">Status</th><th scope="col">Dates</th><th scope="col">Actions</th></tr></thead>
                                <tbody>
                                    @foreach ($documents as $d)
                                        <tr @unless ($d->is_current) class="is-muted" @endunless>
                                            <td>
                                                <strong>{{ $d->title }}</strong>
                                                <span class="pcg-table__muted">{{ $d->category->name }} · v{{ $d->version }}{{ $d->is_current ? '' : ' (superseded)' }} · {{ $d->size_bytes < 1024 ? '<1' : number_format($d->size_bytes / 1024) }} KB</span>
                                            </td>
                                            <td>
                                                @include('admin.documents.partials.status', ['doc' => $d])
                                                @if ($d->review_remarks)<span class="pcg-table__muted">{{ $d->review_remarks }}</span>@endif
                                            </td>
                                            <td>
                                                <span class="pcg-table__muted">Uploaded {{ $d->created_at->format('d M Y') }}</span>
                                                @if ($d->expires_on)<span class="pcg-table__muted">Expires {{ $d->expires_on->format('d M Y') }}</span>@endif
                                            </td>
                                            <td>
                                                <div class="pcg-actions">
                                                    <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('admin.documents.download', $d->id) }}">Download</a>
                                                    @if ($canVerify && $d->is_current && $d->status === 'pending')
                                                        @include('admin.documents.partials.verify', ['doc' => $d])
                                                    @endif
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

            <div class="pcg-stack">
                @if ($canEdit)
                    <section class="pcg-card" aria-labelledby="assign-heading">
                        <div class="pcg-card__header"><h3 id="assign-heading">{{ $current ? 'Transfer / reassign' : 'Assign' }}</h3></div>
                        <div class="pcg-card__body">
                            <details class="pcg-disclosure" @if ($errors->hasAny(['org_unit_id', 'start_date'])) open @endif>
                                <summary class="pcg-btn pcg-btn--secondary pcg-btn--block">Record an assignment</summary>
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
                                        <div class="pcg-field"><label class="pcg-label" for="position">Position / designation {!! $opt !!}</label>
                                            <input class="pcg-input" id="position" name="position" value="{{ old('position') }}" maxlength="255"></div>
                                        <div class="pcg-field"><label class="pcg-label" for="start_date">Effective date</label>
                                            <input class="pcg-input" type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                                            <p class="pcg-help">The current assignment ends the day before.</p></div>
                                        <div class="pcg-field"><label class="pcg-label" for="order_reference">Order / reference no. {!! $opt !!}</label>
                                            <input class="pcg-input" id="order_reference" name="order_reference" value="{{ old('order_reference') }}" maxlength="255"></div>
                                        <div class="pcg-field"><label class="pcg-label" for="remarks">Remarks {!! $opt !!}</label>
                                            <textarea class="pcg-input" id="remarks" name="remarks" rows="2" maxlength="2000">{{ old('remarks') }}</textarea></div>
                                        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Saving…"
                                            data-pcg-confirm="Record this assignment? The current assignment will be closed (kept in the history).">Record assignment</button>
                                    </form>
                                @endif
                            </details>
                        </div>
                    </section>

                    <section class="pcg-card" aria-labelledby="promo-heading">
                        <div class="pcg-card__header"><h3 id="promo-heading">Promotion</h3></div>
                        <div class="pcg-card__body">
                            <details class="pcg-disclosure" @if ($errors->hasAny(['to_rank_id', 'effective_date'])) open @endif>
                                <summary class="pcg-btn pcg-btn--secondary pcg-btn--block">Record a promotion</summary>
                                <form method="POST" action="{{ route('admin.personnel.promote', $person->id) }}" data-pcg-busy>
                                    @csrf
                                    <div class="pcg-field"><label class="pcg-label" for="to_rank_id">New rank</label>
                                        <select class="pcg-input" id="to_rank_id" name="to_rank_id" required>
                                            <option value="">Select a rank</option>
                                            @foreach ($ranks->groupBy('classification') as $class => $group)
                                                <optgroup label="{{ \App\Models\Rank::CLASSIFICATIONS[$class] ?? $class }}">
                                                    @foreach ($group as $rank)
                                                        <option value="{{ $rank->id }}" @disabled($rank->id === $person->rank_id)>{{ $rank->name }} ({{ $rank->code }})</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select></div>
                                    <div class="pcg-field"><label class="pcg-label" for="effective_date">Effective date</label>
                                        <input class="pcg-input" type="date" id="effective_date" name="effective_date" value="{{ old('effective_date') }}" required></div>
                                    <div class="pcg-field"><label class="pcg-label" for="promo_order">Promotion order no. {!! $opt !!}</label>
                                        <input class="pcg-input" id="promo_order" name="order_reference" maxlength="255"></div>
                                    <p class="pcg-help">Records the order only; no eligibility rules are applied. Upload the order under 201 documents.</p>
                                    <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-pcg-confirm="Record this promotion and change the current rank?">Record promotion</button>
                                </form>
                            </details>
                        </div>
                    </section>

                    <section class="pcg-card" aria-labelledby="addtr-heading">
                        <div class="pcg-card__header"><h3 id="addtr-heading">Training / qualification</h3></div>
                        <div class="pcg-card__body">
                            <details class="pcg-disclosure" @if ($errors->hasAny(['title', 'kind', 'end_date', 'valid_until'])) open @endif>
                                <summary class="pcg-btn pcg-btn--secondary pcg-btn--block">Add training or qualification</summary>
                                <form method="POST" action="{{ route('admin.personnel.trainings.store', $person->id) }}" data-pcg-busy>
                                    @csrf
                                    <div class="pcg-field"><label class="pcg-label" for="tr_title">Title</label>
                                        <input class="pcg-input" id="tr_title" name="title" required maxlength="255" value="{{ old('title') }}"></div>
                                    <div class="pcg-field"><label class="pcg-label" for="tr_kind">Type</label>
                                        <select class="pcg-input" id="tr_kind" name="kind">
                                            @foreach (\App\Models\PersonnelTraining::KINDS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                        </select></div>
                                    <div class="pcg-field"><label class="pcg-label" for="tr_provider">Provider {!! $opt !!}</label>
                                        <input class="pcg-input" id="tr_provider" name="provider" maxlength="255"></div>
                                    <div class="pcg-form-grid">
                                        <div class="pcg-field"><label class="pcg-label" for="tr_start">From {!! $opt !!}</label><input class="pcg-input" type="date" id="tr_start" name="start_date"></div>
                                        <div class="pcg-field"><label class="pcg-label" for="tr_end">To {!! $opt !!}</label><input class="pcg-input" type="date" id="tr_end" name="end_date"></div>
                                    </div>
                                    <div class="pcg-field"><label class="pcg-label" for="tr_cert">Certificate no. {!! $opt !!}</label>
                                        <input class="pcg-input" id="tr_cert" name="certificate_number" maxlength="100"></div>
                                    <div class="pcg-field"><label class="pcg-label" for="tr_valid">Valid until {!! $opt !!}</label>
                                        <input class="pcg-input" type="date" id="tr_valid" name="valid_until"></div>
                                    <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block">Add</button>
                                </form>
                            </details>
                        </div>
                    </section>
                @endif

                @if ($canUpload)
                    <section class="pcg-card" aria-labelledby="up-heading">
                        <div class="pcg-card__header"><h3 id="up-heading">Upload a 201 document</h3></div>
                        <div class="pcg-card__body">
                            <details class="pcg-disclosure" @if ($errors->hasAny(['file', 'category_id', 'replaces_id'])) open @endif>
                                <summary class="pcg-btn pcg-btn--secondary pcg-btn--block">Upload document</summary>
                                <form method="POST" action="{{ route('admin.personnel.documents.store', $person->id) }}" enctype="multipart/form-data" data-pcg-busy>
                                    @csrf
                                    <div class="pcg-field"><label class="pcg-label" for="category_id">Category</label>
                                        <select class="pcg-input" id="category_id" name="category_id" required>
                                            @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                        </select></div>
                                    <div class="pcg-field"><label class="pcg-label" for="doc_title">Title</label>
                                        <input class="pcg-input" id="doc_title" name="title" required maxlength="255" placeholder="e.g. Promotion order GO-123"></div>
                                    <div class="pcg-field"><label class="pcg-label" for="doc_file">File</label>
                                        <input class="pcg-input" type="file" id="doc_file" name="file" required accept="application/pdf,image/jpeg,image/png">
                                        <p class="pcg-help">PDF, JPG or PNG, up to {{ round(config('pcg.document_max_kb') / 1024) }} MB. Stored privately.</p></div>
                                    <div class="pcg-form-grid">
                                        <div class="pcg-field"><label class="pcg-label" for="issued_on">Issued {!! $opt !!}</label><input class="pcg-input" type="date" id="issued_on" name="issued_on"></div>
                                        <div class="pcg-field"><label class="pcg-label" for="expires_on">Expires {!! $opt !!}</label><input class="pcg-input" type="date" id="expires_on" name="expires_on"></div>
                                    </div>
                                    @php $currentDocs = $documents->where('is_current', true); @endphp
                                    @if ($currentDocs->isNotEmpty())
                                        <div class="pcg-field"><label class="pcg-label" for="replaces_id">Replaces {!! $opt !!}</label>
                                            <select class="pcg-input" id="replaces_id" name="replaces_id">
                                                <option value="">Nothing (new document)</option>
                                                @foreach ($currentDocs as $d)<option value="{{ $d->id }}">{{ $d->category->name }}: {{ $d->title }} (v{{ $d->version }})</option>@endforeach
                                            </select>
                                            <p class="pcg-help">A replacement becomes a new version; the old version is kept.</p></div>
                                    @endif
                                    <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Uploading…">Upload</button>
                                </form>
                            </details>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endsection
