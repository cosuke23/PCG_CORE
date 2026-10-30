@extends('layouts.admin')

@section('title', 'Registration ' . $reg->serial_number)

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <p><a href="{{ route('admin.registrations.index') }}">Registrations</a> / {{ $reg->serial_number }}</p>
                <h2>{{ $reg->rank?->code }} {{ $reg->fullName() }}</h2>
                <p>@include('admin.registrations.partials.status', ['status' => $reg->status])
                    @if ($reg->personnel) · <a href="{{ route('admin.personnel.show', $reg->personnel_id) }}">Open personnel record</a> @endif</p>
            </div>
            @if ($canEdit)
                <div class="pcg-actions">
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.registrations.edit', $reg->id) }}">Edit</a>
                    <form method="POST" action="{{ route('admin.registrations.submit', $reg->id) }}">
                        @csrf
                        <button type="submit" class="pcg-btn pcg-btn--primary">Submit for approval</button>
                    </form>
                </div>
            @endif
        </div>

        @foreach ($duplicates as $message)
            <div class="pcg-alert pcg-alert--warning" role="status">{{ $message }}</div>
        @endforeach

        <div class="{{ $canReview || $canDecide ? 'pcg-grid' : 'pcg-stack' }}">
            <div class="pcg-stack">
                <section class="pcg-card" aria-labelledby="d-heading">
                    <div class="pcg-card__header"><h3 id="d-heading">Details</h3></div>
                    <div class="pcg-card__body">
                        <dl class="pcg-dl">
                            <div><dt>Serial number</dt><dd>{{ $reg->serial_number }}</dd></div>
                            <div><dt>Name</dt><dd>{{ $reg->fullName() }}</dd></div>
                            <div><dt>Rank</dt><dd>{{ $reg->rank ? $reg->rank->name . ' (' . $reg->rank->code . ')' : 'Not given' }}{{ $reg->rank_date ? ', since ' . $reg->rank_date->format('d M Y') : '' }}</dd></div>
                            <div><dt>Date of birth</dt><dd>{{ $reg->date_of_birth?->format('d M Y') ?? 'Not given' }}</dd></div>
                            <div><dt>Entered service</dt><dd>{{ $reg->date_entered_service?->format('d M Y') ?? 'Not given' }}</dd></div>
                            <div><dt>Contact</dt><dd>{{ collect([$reg->email, $reg->contact_number])->filter()->implode(' · ') ?: 'Not given' }}</dd></div>
                            <div><dt>Proposed unit</dt><dd>{{ $reg->orgUnit->code }} · {{ $reg->orgUnit->name }}{{ $reg->position ? ' — ' . $reg->position : '' }}</dd></div>
                            <div><dt>Effective</dt><dd>{{ $reg->assignment_start_date?->format('d M Y') ?? 'Not given' }}{{ $reg->order_reference ? ' · ' . $reg->order_reference : '' }}</dd></div>
                            <div><dt>Duplicate check</dt><dd>{{ $reg->confirmed_not_duplicate ? 'Preparer confirmed this is a new person despite a same-name record' : 'No confirmation given (needed only when a same-name record exists)' }}</dd></div>
                            @if ($reg->decision_remarks)
                                <div><dt>Decision remarks</dt><dd>{{ $reg->decision_remarks }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </section>

                <section class="pcg-card" aria-labelledby="h-heading">
                    <div class="pcg-card__header"><h3 id="h-heading">History</h3></div>
                    <ol class="pcg-timeline">
                        @foreach ($reg->events as $e)
                            <li>
                                <strong>{{ ucfirst($e->action) }}</strong>
                                <span class="pcg-table__muted">{{ $e->created_at?->format('d M Y H:i') }} · {{ $names[$e->admin_user_id] ?? $e->admin_email ?? 'system' }}</span>
                                @if ($e->remarks) <p>{{ $e->remarks }}</p> @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <div class="pcg-stack">
                @if ($canReview || $canDecide)
                    @if ($isOwn)
                        <div class="pcg-alert pcg-alert--info" role="status">You prepared or submitted this registration, so another authorised admin must review and decide it.</div>
                    @else
                        @if ($canReview)
                            <section class="pcg-card" aria-labelledby="r-heading">
                                <div class="pcg-card__header"><h3 id="r-heading">Review</h3></div>
                                <div class="pcg-card__body">
                                    <form method="POST" action="{{ route('admin.registrations.review', $reg->id) }}">
                                        @csrf
                                        <div class="pcg-field">
                                            <label class="pcg-label" for="review_remarks">Remarks</label>
                                            <textarea class="pcg-input" id="review_remarks" name="remarks" rows="3" maxlength="2000">{{ old('remarks') }}</textarea>
                                            <p class="pcg-help">Required when returning for correction.</p>
                                        </div>
                                        <div class="pcg-actions">
                                            <button type="submit" name="action" value="endorse" class="pcg-btn pcg-btn--secondary">Endorse</button>
                                            <button type="submit" name="action" value="return" class="pcg-btn pcg-btn--secondary">Return for correction</button>
                                        </div>
                                    </form>
                                </div>
                            </section>
                        @endif
                        @if ($canDecide)
                            <section class="pcg-card" aria-labelledby="a-heading">
                                <div class="pcg-card__header"><h3 id="a-heading">Decision</h3></div>
                                <div class="pcg-card__body">
                                    <form method="POST" action="{{ route('admin.registrations.decide', $reg->id) }}">
                                        @csrf
                                        <div class="pcg-field">
                                            <label class="pcg-label" for="decision_remarks">Remarks</label>
                                            <textarea class="pcg-input" id="decision_remarks" name="remarks" rows="3" maxlength="2000"></textarea>
                                            <p class="pcg-help">Required when rejecting.</p>
                                        </div>
                                        <div class="pcg-actions">
                                            <button type="submit" name="action" value="approve" class="pcg-btn pcg-btn--primary"
                                                data-pcg-confirm="Approve and create the personnel record?">Approve</button>
                                            <button type="submit" name="action" value="reject" class="pcg-btn pcg-btn--secondary"
                                                data-pcg-confirm="Reject this registration?">Reject</button>
                                        </div>
                                    </form>
                                </div>
                            </section>
                        @endif
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
