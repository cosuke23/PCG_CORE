<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEPCOM HRMIS · Philippine Coast Guard</title>
    <meta name="description" content="Personnel information system of the PCG Marine Environmental Protection Command: registration, service records, document review and training.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <script src="{{ asset('js/landing.js') }}" defer></script>
</head>
<body class="lp">
    <a class="lp-skip" href="#main">Skip to content</a>

    {{-- Fixed backdrop: the boat stays put while the page scrolls over it --}}
    <div class="lp-backdrop" aria-hidden="true">
        <img src="{{ asset('images/landing/hero.webp') }}" alt="" fetchpriority="high">
    </div>

    {{-- ===================================================== header --}}
    <header class="lp-top">
        <div class="lp-wrap lp-top__in">
            <a class="lp-brand" href="{{ route('home') }}">
                <span class="lp-brand__logos">
                    <img class="lp-brand__bp" src="{{ asset('images/brand/bagong-pilipinas.webp') }}" alt="Bagong Pilipinas" width="44" height="41">
                    <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="Philippine Coast Guard" width="40" height="40">
                    <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="Marine Environmental Protection Command" width="40" height="40">
                </span>
                <span>
                    <strong>MEPCOM HRMIS</strong>
                    <span>Philippine Coast Guard · Personnel gateway</span>
                </span>
            </a>

            @php
                $links = [
                    [route('home'), 'Home', true],
                    [route('about'), 'About', false],
                    ['#services', 'Services', false],
                    ['#units', 'Units', false],
                    [route('meptdi'), 'MEPTDI', false],
                    [route('contact'), 'Contact', false],
                ];
            @endphp
            <nav class="lp-nav" aria-label="Main">
                @foreach ($links as [$href, $label, $current])
                    <a href="{{ $href }}" @if ($current) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <details class="lp-menu">
                <summary aria-label="Open menu"><i class="fa-solid fa-bars" aria-hidden="true"></i> Menu</summary>
                <nav class="lp-nav" aria-label="Main (mobile)">
                    @foreach ($links as [$href, $label, $current])
                        <a href="{{ $href }}" @if ($current) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('login') }}">Personnel sign in</a>
                </nav>
            </details>
            <a class="lp-btn lp-btn--gold lp-btn--sm lp-top__desktop-cta" href="{{ route('login') }}">Sign in</a>
        </div>
    </header>

    <main id="main">
        {{-- ===================================================== hero --}}
        <section class="lp-hero" aria-labelledby="hero-title">
            <div class="lp-wrap lp-hero__in">
                <div class="lp-hero__seal">
                    <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="Philippine Coast Guard seal">
                    <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="Marine Environmental Protection Command seal">
                </div>
                <div>
                    <h1 id="hero-title">MEPCOM AHEAD</h1>
                    <p class="lp-hero__quote">“Steering the Nation Toward Sustainable Seas”</p>
                    <a class="lp-hero__scroll" href="#band-title"><span>Scroll to explore</span></a>
                </div>
            </div>
        </section>

        {{-- ===================================================== maroon band --}}
        <section class="lp-band" aria-labelledby="band-title">
            <div class="lp-wrap lp-band__in">
                <div>
                    <p class="lp-eyebrow">Philippine Coast Guard · MEPCOM</p>
                    <h2 id="band-title" data-reveal>Protecting Our Seas. <em>Serving Our Nation.</em></h2>
                </div>
                <div>
                    <p><strong>MEPCOM HRMIS</strong> is the personnel information system of the Marine Environmental Protection Command:
                        registration, service records, document review and training, in one place.</p>
                    <div class="lp-actions">
                        <a class="lp-btn lp-btn--gold" href="{{ route('login') }}">Personnel sign in</a>
                        <a class="lp-btn lp-btn--outline" href="#join">Register as personnel</a>
                    </div>
                </div>
            </div>
            <p class="lp-wrap lp-band__credit">Photo: Philippine Coast Guard, MEPCOM</p>
        </section>


        {{-- ===================================================== services --}}
        <section class="lp-section" id="services" aria-labelledby="services-title">
            <div class="lp-wrap">
                <p class="lp-eyebrow lp-eyebrow--dark">Digital services</p>
                <h2 id="services-title" data-reveal>Find the right service, sooner.</h2>
                <p class="lp-lead">What MEPCOM personnel and units use most, gathered in one place. Access to personnel records always requires signing in.</p>

                <div class="lp-feature">
                    <article class="lp-card lp-card--navy" data-reveal>
                        <div class="lp-card__kicker"><span class="lp-card__icon"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span> Personnel portal</div>
                        <h3>Your service record, online</h3>
                        <p>Update your Statement of Information, upload required documents and 201 files, follow your document review and apply for training tracks.</p>
                        <ul class="lp-tags" aria-label="Included">
                            <li>SOI profile</li><li>Required documents</li><li>201 files</li><li>Training</li>
                        </ul>
                        <a class="lp-btn lp-btn--gold" href="{{ route('login') }}">Sign in to the portal</a>
                    </article>

                    <article class="lp-card" id="join" data-reveal>
                        <div class="lp-card__kicker"><span class="lp-card__icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span> Join the system</div>
                        <h3>New personnel registration</h3>
                        <p>Officers and non-officers of MEPCOM register once. MEPCOM HR reviews every registration before access is granted.</p>
                        <div class="lp-actions">
                            <a class="lp-btn lp-btn--maroon" href="{{ route('register', ['type' => 'officer']) }}">Officer</a>
                            <a class="lp-btn lp-btn--maroon" href="{{ route('register', ['type' => 'non_officer']) }}">Non-officer</a>
                        </div>
                        <p class="lp-note">Have your serial number ready. A live photo is taken during registration.</p>
                    </article>
                </div>

                <div class="lp-grid4" data-reveal>
                    <article class="lp-mini">
                        <span class="lp-mini__icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>
                        <h3>Unit document checkers</h3>
                        <p>Review and approve the required documents of your unit's personnel.</p>
                        <a class="lp-link" href="{{ url('/unit/login') }}">Unit sign in</a>
                    </article>
                    <article class="lp-mini">
                        <img class="lp-mini__seal" src="{{ asset('images/brand/meptdi-logo.webp') }}" alt="MEPTDI seal" width="44" height="44" loading="lazy">
                        <h3>MEPTDI</h3>
                        <p>Marine Environmental Protection Training and Doctrine Institute courses and programs.</p>
                        <a class="lp-link" href="{{ route('meptdi') }}">Visit MEPTDI</a>
                    </article>
                    <article class="lp-mini">
                        <span class="lp-mini__icon"><i class="fa-solid fa-certificate" aria-hidden="true"></i></span>
                        <h3>OSAERT certificate check</h3>
                        <p>Verify an OSAERT training certificate by its certificate number.</p>
                        <a class="lp-link" href="{{ route('osaert.verify') }}">Verify a certificate</a>
                    </article>
                    <article class="lp-mini">
                        <span class="lp-mini__icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                        <h3>HR administration</h3>
                        <p>Personnel records, registrations, 201 documents and reports for authorised HR staff.</p>
                        <a class="lp-pill" href="{{ route('admin.login') }}"><i class="fa-solid fa-lock" aria-hidden="true"></i> Authorised personnel only</a>
                    </article>
                </div>
            </div>
        </section>


        {{-- ===================================================== about --}}
        <section class="lp-section lp-about" id="about" aria-labelledby="about-title">
            <div class="lp-wrap lp-about__in">
                <figure class="lp-about__photo" data-reveal>
                    <img src="{{ asset('images/landing/personnel.webp') }}" alt="MEPCOM personnel in uniform" loading="lazy">
                </figure>
                <div>
                    <p class="lp-eyebrow">About MEPCOM</p>
                    <h2 id="about-title" data-reveal>Guardians of the Philippine marine environment.</h2>
                    <p>The Marine Environmental Protection Command is the specialised command of the Philippine Coast Guard responsible for
                        preventing, controlling and mitigating marine pollution within the maritime jurisdiction of the Philippines.</p>
                    <p>MEPCOM HRMIS keeps one accurate record for every member of the Command, so that the people who protect our seas are
                        supported by reliable personnel information.</p>

                    <h3>MEPCOM AHEAD</h3>
                    <ol class="lp-ahead" data-reveal>
                        <li><b>A</b> Accountable governance and leadership</li>
                        <li><b>H</b> Highest standard of PCG core values</li>
                        <li><b>E</b> Efficient and sound resource management</li>
                        <li><b>A</b> Agile systems and technological advancement</li>
                        <li><b>D</b> Dependable partnership and cooperation</li>
                    </ol>
                </div>
            </div>
        </section>

        {{-- ===================================================== units --}}
        <section class="lp-section" id="units" aria-labelledby="units-title">
            <div class="lp-wrap">
                <p class="lp-eyebrow lp-eyebrow--dark">Our units</p>
                <h2 id="units-title" data-reveal>One Command, many missions.</h2>
                <p class="lp-lead">MEPCOM's units cover law enforcement, oil-pollution response, marine science, regulation, training and field operations.</p>
                <ul class="lp-units" data-reveal>
                    <li><strong>MEPLEF</strong><span>Marine Environmental Protection Law Enforcement Force</span><small>Operational support</small></li>
                    <li><strong>NOCOP</strong><span>National Operations Center for Oil Pollution</span><small>Operational support</small></li>
                    <li><strong>MSFIS</strong><span>Marine Science Forensic Investigation Service</span><small>Operational support</small></li>
                    <li><strong>MEPRCGS</strong><span>Marine Environmental Protection Regulatory Compliance and Governance Service</span><small>Administrative support</small></li>
                    <li class="lp-units__with-seal"><img src="{{ asset('images/brand/meptdi-logo.webp') }}" alt="" width="56" height="56" loading="lazy"><strong>MEPTDI</strong><span>Marine Environmental Protection Training and Doctrine Institute</span><small>Administrative support</small></li>
                    <li><strong>MEPG</strong><span>Marine Environmental Protection Group</span><small>Operating unit</small></li>
                </ul>
            </div>
        </section>

        {{-- ===================================================== call to action --}}
        <section class="lp-cta" aria-labelledby="cta-title">
            <div class="lp-wrap lp-cta__in">
                <div>
                    <h2 id="cta-title">Questions about your records?</h2>
                    <p>Send a message to MEPCOM HR through the contact page.</p>
                </div>
                <a class="lp-btn lp-btn--gold" href="{{ route('contact') }}">Contact MEPCOM HR</a>
            </div>
        </section>
    </main>

    {{-- ===================================================== footer --}}
    <footer class="lp-foot">
        <div class="lp-wrap lp-foot__in">
            <div>
                <div class="lp-foot__logos">
                    <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="Philippine Coast Guard" loading="lazy">
                    <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="MEPCOM" loading="lazy">
                    <img src="{{ asset('images/brand/meptdi-logo.webp') }}" alt="MEPTDI" loading="lazy">
                    <img src="{{ asset('images/brand/bagong-pilipinas.webp') }}" alt="Bagong Pilipinas" loading="lazy">
                </div>
                <p>Marine Environmental Protection Command<br>Philippine Coast Guard</p>
            </div>
            <div>
                <h3>Services</h3>
                <ul>
                    <li><a href="{{ route('login') }}">Personnel sign in</a></li>
                    <li><a href="#join">Register</a></li>
                    <li><a href="{{ url('/unit/login') }}">Unit sign in</a></li>
                    <li><a href="{{ route('osaert.verify') }}">OSAERT certificate check</a></li>
                </ul>
            </div>
            <div>
                <h3>Command</h3>
                <ul>
                    <li><a href="{{ route('about') }}">About MEPCOM</a></li>
                    <li><a href="{{ route('meptdi') }}">MEPTDI</a></li>
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                    <li><a href="{{ route('admin.login') }}">HR administration</a></li>
                </ul>
            </div>
        </div>
        <div class="lp-wrap lp-foot__base">
            <span>© {{ now()->year }} Marine Environmental Protection Command, Philippine Coast Guard</span>
            <span>Authorised use only. Activity on this system may be logged.</span>
        </div>
    </footer>
</body>
</html>
