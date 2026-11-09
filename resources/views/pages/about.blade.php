@extends('layouts.landing')

@section('title', 'About MEPCOM · MEPCOM HRMIS')
@section('description', 'Who we are: the Philippine Coast Guard and its Marine Environmental Protection Command, the vision and mission, the MEPCOM magazine and the team behind MEPCOM HRMIS.')

@section('content')
    <section class="lp-pagehead" aria-labelledby="about-title">
        <div class="lp-wrap">
            <p class="lp-eyebrow">About</p>
            <h1 id="about-title">Safety at sea, a protected marine environment, and the people who serve both.</h1>
            <p class="lp-pagehead__lead">The Philippine Coast Guard is a uniformed service tasked to ensure the safety of lives and property at sea, protect the marine environment, and enforce maritime laws in Philippine waters.</p>
        </div>
    </section>

    <section class="lp-section" aria-labelledby="values-title">
        <div class="lp-wrap">
            <h2 id="values-title">What we stand for</h2>
            <p class="lp-lead">We are committed to excellence, discipline, and service to our country and the Filipino people.</p>
            <div class="lp-cols3">
                <article class="lp-col">
                    <h3>Vision</h3>
                    <p>A world-class Coast Guard dedicated to maritime safety, security, and environmental protection for a strong and resilient nation.</p>
                </article>
                <article class="lp-col">
                    <h3>Mission</h3>
                    <p>To safeguard life and property at sea, enforce maritime laws, and protect the marine environment through professional and disciplined service.</p>
                </article>
                <article class="lp-col">
                    <h3>People</h3>
                    <p>Dedicated men and women driven by duty and passion, united under the core values of Honor, Service, and Patriotism.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="lp-section lp-section--sand" aria-labelledby="mag-title">
        <div class="lp-wrap lp-split">
            <div>
                <p class="lp-eyebrow lp-eyebrow--dark">News and features</p>
                <h2 id="mag-title">The MEPCOM magazine</h2>
                <p class="lp-lead">News, features and updates about the Command's missions, events and activities, published online.</p>
            </div>
            <a class="lp-btn lp-btn--maroon" href="https://heyzine.com/flip-book/c35f81ef4f.html" target="_blank" rel="noopener">
                Read the magazine <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <section class="lp-section" aria-labelledby="dev-title">
        <div class="lp-wrap">
            <h2 id="dev-title">Who built MEPCOM HRMIS</h2>
            <p class="lp-lead">This system was developed by a dedicated team committed to digital transformation and modernizing the processes of the Philippine Coast Guard.</p>
            <ul class="lp-people">
                <li class="lp-person">
                    <button type="button" class="lp-zoom lp-person__photo" data-lp-zoom aria-label="View photo full screen: Julius B. Colminas">
                        <img src="{{ asset('images/dev-1.jpg') }}" alt="Julius B. Colminas" width="120" height="120" loading="lazy">
                    </button>
                    <div>
                        <h3>Julius B. Colminas</h3>
                        <p class="lp-person__role">Lead Developer</p>
                        <p>Full-stack developer passionate about creating efficient and secure systems to improve Coast Guard operations.</p>
                    </div>
                </li>
                <li class="lp-person">
                    <button type="button" class="lp-zoom lp-person__photo" data-lp-zoom aria-label="View photo full screen: Adnan N. Jumdail">
                        <img src="{{ asset('images/dev-2.jpg') }}" alt="Adnan N. Jumdail" width="120" height="120" loading="lazy">
                    </button>
                    <div>
                        <h3>Adnan N. Jumdail</h3>
                        <p class="lp-person__role">Frontend Developer</p>
                        <p>Focused on database management and backend logic ensuring reliability and performance across systems.</p>
                    </div>
                </li>
            </ul>
        </div>
    </section>

    <section class="lp-cta" aria-labelledby="cta-title">
        <div class="lp-wrap lp-cta__in">
            <div>
                <h2 id="cta-title">Questions about your records?</h2>
                <p>Send a message to MEPCOM HR through the contact page.</p>
            </div>
            <a class="lp-btn lp-btn--gold" href="{{ route('contact') }}">Contact MEPCOM HR</a>
        </div>
    </section>
@endsection
