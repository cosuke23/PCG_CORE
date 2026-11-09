@extends('layouts.landing')

@section('title', 'Contact MEPCOM HR · MEPCOM HRMIS')
@section('description', 'Send a message to the MEPCOM HRMIS administrators about registration, documents or training.')

@section('content')
    <section class="lp-pagehead lp-pagehead--short" aria-labelledby="contact-title">
        <div class="lp-wrap">
            <p class="lp-eyebrow">Contact</p>
            <h1 id="contact-title">Write to the MEPCOM HRMIS team.</h1>
            <p class="lp-pagehead__lead">Questions about registration, your documents or training. Your message goes to the system administrators, who answer by email.</p>
        </div>
    </section>

    <section class="lp-section lp-section--tight" aria-label="Message form">
        <div class="lp-wrap lp-contact">
            <form class="lp-form" method="POST" action="{{ route('contact.store') }}">
                @csrf
                @if (session('success'))
                    <p class="lp-note lp-note--ok" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</p>
                @endif
                @if ($errors->any())
                    <div class="lp-note lp-note--bad" role="alert" id="form-errors">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="lp-form__row">
                    <div class="lp-field">
                        <label for="name">Full name</label>
                        <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
                    </div>
                    <div class="lp-field">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                    </div>
                </div>
                <div class="lp-field">
                    <label for="subject">Subject <span>optional</span></label>
                    <input id="subject" name="subject" value="{{ old('subject') }}" maxlength="150">
                </div>
                <div class="lp-field">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="7" required minlength="10" maxlength="5000">{{ old('message') }}</textarea>
                </div>
                {{-- Leave empty: catches automated spam --}}
                <div class="lp-hp" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="lp-form__foot">
                    <p>Do not send passwords or clearance documents by message.</p>
                    <button type="submit" class="lp-btn lp-btn--maroon">Send message</button>
                </div>
            </form>

            <aside class="lp-contact__aside">
                <h2>Before you write</h2>
                <ul class="lp-checks">
                    <li>Forgot your password or locked out: say which email you registered with.</li>
                    <li>A document was returned: read your unit checker's comment on your dashboard first.</li>
                    <li>Registration still pending: MEPCOM HR reviews every registration before access is granted.</li>
                </ul>
            </aside>
        </div>
    </section>
@endsection
