@extends('layouts.personnel')

@section('title', 'Recall')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <section class="pcg-hero rc-hero" aria-labelledby="rc-h">
            <p class="pcg-hero__eyebrow"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Emergency recall · {{ $r->unit_name ?? 'every unit' }}</p>
            <h2 id="rc-h">{{ $r->title }}</h2>
            <p class="rc-msg">{{ $r->message }}</p>
            @if ($r->report_to)<p><strong>Report to:</strong> {{ $r->report_to }}</p>@endif
            <p class="lc-muted">Sent {{ \Illuminate\Support\Carbon::parse($r->created_at)->timezone('Asia/Manila')->format('M j, Y g:i A') }} by {{ $r->sent_by_name }}</p>
        </section>

        @if ($resp->answer)
            <p class="pcg-alert pcg-alert--{{ $resp->answer === 'coming' ? 'success' : 'info' }}">You answered: <strong>{{ \App\Support\Recalls::ANSWERS[$resp->answer] }}</strong>@if ($resp->note) ({{ $resp->note }})@endif. {{ $r->closed_at ? '' : 'You can change it below.' }}</p>
        @endif

        @if ($r->closed_at)
            <p class="pcg-alert pcg-alert--info">This recall is closed.</p>
        @else
            <section class="pcg-card" aria-labelledby="rc-a">
                <div class="pcg-card__header"><h3 id="rc-a">Your answer</h3></div>
                <div class="pcg-card__body">
                    <form method="POST" action="{{ route('recall.answer', $r->id) }}" class="lc-form">
                        @csrf
                        <div class="pcg-field">
                            <label class="pcg-label" for="rc-note">Note <span class="lc-muted">(needed if you cannot come)</span></label>
                            <input class="pcg-input" id="rc-note" name="note" maxlength="300" value="{{ old('note', $resp->note) }}" placeholder="e.g. arriving 1900H, or on approved leave in Cebu">
                        </div>
                        <div class="rc-answers">
                            <button type="submit" name="answer" value="coming" class="pcg-btn pcg-btn--cta"><i class="fa-solid fa-person-running" aria-hidden="true"></i> On my way</button>
                            <button type="submit" name="answer" value="cannot" class="pcg-btn pcg-btn--secondary">Cannot come</button>
                        </div>
                    </form>
                </div>
            </section>
        @endif
    </div>
@endsection
