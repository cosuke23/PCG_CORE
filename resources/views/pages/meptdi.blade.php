@extends('layouts.landing')

@section('title', 'MEPTDI · MEPCOM HRMIS')
@section('description', 'The MEP Training and Doctrine Institute trains MEPCOM personnel in marine environmental protection and keeps its doctrine current.')

@section('content')
    <section class="lp-pagehead" aria-labelledby="meptdi-title">
        <div class="lp-wrap lp-pagehead__row">
            <img class="lp-pagehead__seal" src="{{ asset('images/brand/meptdi-logo.webp') }}" alt="" width="96" height="96">
            <div>
                <p class="lp-eyebrow">MEP Training and Doctrine Institute</p>
                <h1 id="meptdi-title">Training every MEPCOM member to protect the marine environment.</h1>
                <p class="lp-pagehead__lead">MEPTDI educates and trains MEPCOM personnel in marine environmental knowledge and skills for the protection, preservation and conservation of the marine environment.</p>
            </div>
        </div>
    </section>

    <section class="lp-section" aria-labelledby="mission-title">
        <div class="lp-wrap lp-split lp-split--top">
            <div>
                <h2 id="mission-title">Mission</h2>
                <p class="lp-prose">To ensure that all MEPCOM personnel are properly trained, educated, and indoctrinated in marine environmental protection principles, enhancing operational readiness and environmental stewardship.</p>
                <p class="lp-prose">As an administrative support unit of MEPCOM, MEPTDI shapes the professional development of personnel through structured training programs, policy development, and continuous doctrinal improvement.</p>
            </div>
            <div>
                <h2>Core functions</h2>
                <ul class="lp-checks">
                    <li>Conduct education and training for MEPCOM personnel</li>
                    <li>Develop marine environmental protection competencies</li>
                    <li>Formulate, review, and update doctrinal policies</li>
                    <li>Conduct doctrinal research and studies</li>
                    <li>Support environmental preservation and conservation initiatives</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="lp-section lp-section--sand" aria-labelledby="pillars-title">
        <div class="lp-wrap">
            <h2 id="pillars-title">Education, training and doctrine</h2>
            <div class="lp-cols3">
                <article class="lp-col">
                    <h3>Education</h3>
                    <p>Builds foundational environmental knowledge among personnel.</p>
                </article>
                <article class="lp-col">
                    <h3>Training</h3>
                    <p>Enhances operational readiness through structured programs.</p>
                </article>
                <article class="lp-col">
                    <h3>Doctrine</h3>
                    <p>Keeps policies updated and aligned with environmental standards.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="lp-cta" aria-labelledby="cta-title">
        <div class="lp-wrap lp-cta__in">
            <div>
                <h2 id="cta-title">Apply for a training track</h2>
                <p>Sign in to see the administrative and operational tracks for your rank and apply.</p>
            </div>
            <a class="lp-btn lp-btn--gold" href="{{ route('login') }}">Personnel sign in</a>
        </div>
    </section>
@endsection
