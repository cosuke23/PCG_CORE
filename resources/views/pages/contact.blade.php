@extends('layouts.app')

@section('title', 'Contact Us')

@section('content')
    <div class="pcg pcg-public">
        <div class="pcg-public__inner">
            <section class="pcg-card" aria-labelledby="contact-heading">
                <div class="pcg-card__header">
                    <h1 id="contact-heading" class="pcg-public__title">Contact the MEPCOM HRMIS team</h1>
                    <p class="pcg-help">Questions about registration, your documents or training. Your message goes to the system administrators.</p>
                </div>
                <div class="pcg-card__body">
                    @if (session('success'))
                        <div class="pcg-alert pcg-alert--success" role="status">{{ session('success') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="pcg-alert pcg-alert--danger" role="alert" id="form-errors">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}" data-pcg-busy>
                        @csrf
                        <div class="pcg-field">
                            <label class="pcg-label" for="name">Full name</label>
                            <input class="pcg-input" id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="email">Email address</label>
                            <input class="pcg-input" type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="subject">Subject <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                            <input class="pcg-input" id="subject" name="subject" value="{{ old('subject') }}" maxlength="150">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="message">Message</label>
                            <textarea class="pcg-input" id="message" name="message" rows="6" required minlength="10" maxlength="5000">{{ old('message') }}</textarea>
                        </div>
                        {{-- Leave empty: catches automated spam --}}
                        <div class="pcg-visually-hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <p class="pcg-help">Do not send passwords or clearance documents by message.</p>
                        <button type="submit" class="pcg-btn pcg-btn--primary" data-busy-label="Sending…">Send message</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
