{{--
    PCG HRMIS: the main landing page (/). Design follows the PCG-CORE public site
    (crimson / azure / sand, the official three-commands banner as hero, "Digital
    services" first) with its own stylesheet css/pcg-home.css (no build step).
    MEPCOM HRMIS, the command system that is live today, is at /mepcom.
--}}
@php
    // PCG organizational structure (official PCG chart), top to bottom.
    $pcgGroups = [
        ['Functional Commands', 'fa-compass', [
            ['Maritime Safety Services Command', 'MSSC', 'images/pcg/mssc-logo.jpg'],
            ['Marine Environmental Protection Command', 'MEPCOM', 'images/brand/mepcom-logo.webp'],
            ['Maritime Security and Law Enforcement Command', 'MARSLEC', 'images/pcg/marslec-logo.jpg'],
        ]],
        ['Admin Support Commands', 'fa-briefcase', [
            'Coast Guard Human Resource Management Command',
            'Coast Guard Finance Service',
            'CG Education, Training and Doctrine Command',
            'Coast Guard Logistics Systems Command',
        ]],
        ['Operational Support Commands', 'fa-anchor', [
            'Coast Guard Special Operations Force',
            'Coast Guard Aviation Command',
            'Coast Guard Cyber Command',
            'Coast Guard Fleet',
            'Coast Guard Community Relations Command',
            'CG Weapons, Communications, Electronics and Information Systems Command',
        ]],
        ['Operating Commands', 'fa-map-location-dot', [
            '17 Coast Guard Districts',
        ]],
        ['Special Service Commands', 'fa-shield-halved', [
            'Coast Guard Strategic Studies and International Affairs Center',
            'Coast Guard Internal Audit',
            'Coast Guard Inspector General and Internal Affairs Service',
            'Coast Guard Intelligence Force',
        ]],
        ['Technical Service Commands', 'fa-stethoscope', [
            'Coast Guard Legal Service',
            'Coast Guard Medical Service',
            'Coast Guard Nursing Service',
            'Coast Guard Dental Service',
            'Coast Guard Veterinary Service',
            'Coast Guard Ecumenical Service',
        ]],
    ];
    $nav = [
        ['#top', 'Home'], ['#services', 'Digital services'], ['#structure', 'Organizational structure'],
        [route('mepcom'), 'MEPCOM HRMIS'], [route('contact'), 'Contact'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PCG HRMIS · Philippine Coast Guard</title>
    <meta name="description" content="PCG HRMIS: the human resource gateway of the Philippine Coast Guard. Personnel records, registration, document review and training, command by command.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @include('partials.share-meta', ['shareTitle' => 'PCG HRMIS · Philippine Coast Guard'])
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/pcg-home.css') }}">
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/pcg-home.js') }}" defer></script>
</head>
<body class="ph">
    <a class="ph-skip" href="#main">Skip to content</a>

    {{-- institutional strip --}}
    <div class="ph-strip">
        <div class="ph-wrap ph-strip__in">
            <p><strong>Republic of the Philippines</strong><span aria-hidden="true">|</span>Philippine Coast Guard</p>
            <a href="https://coastguard.gov.ph" rel="external noopener" target="_blank">Official PCG website <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="ph-sr">(coastguard.gov.ph, opens in a new tab)</span></a>
        </div>
    </div>

    {{-- header --}}
    <header class="ph-top" data-ph-header>
        <div class="ph-wrap ph-top__in">
            <a class="ph-brand" href="{{ route('home') }}">
                <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="" width="48" height="48">
                <span>
                    <strong>PCG HRMIS</strong>
                    <span>Philippine Coast Guard · Human resources</span>
                </span>
            </a>
            <nav class="ph-nav" aria-label="Primary">
                @foreach ($nav as [$href, $label])
                    <a href="{{ $href }}" @if ($loop->first) aria-current="location" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <button type="button" class="ph-menu" data-ph-menu aria-expanded="false" aria-controls="ph-mobile-nav">
                <i class="fa-solid fa-bars" aria-hidden="true"></i> <span>Menu</span>
            </button>
        </div>
        <nav class="ph-mobile" id="ph-mobile-nav" aria-label="Primary">
            <ul class="ph-wrap">
                @foreach ($nav as [$href, $label])
                    <li><a href="{{ $href }}">{{ $label }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></li>
                @endforeach
            </ul>
        </nav>
    </header>

    <main id="main">
        {{-- hero: the official banner shown whole, the headline in the band below --}}
        <section class="ph-hero" id="top" aria-labelledby="hero-title">
            {{-- The official banner, shown whole. Each command's panel is a layer cut from the
                 same picture (css/pcg-home.css "command panels"): on hover or focus it zooms,
                 the other two dim, its title lifts with a glow and a light sweep, and a link
                 slides up. Without CSS the picture alone remains. --}}
            <div class="ph-hero__banner">
                <div class="ph-cmds">
                    <img src="{{ asset('images/pcg/commands-banner-2400.jpg') }}"
                         srcset="{{ asset('images/pcg/commands-banner-1200.jpg') }} 1200w, {{ asset('images/pcg/commands-banner-2400.jpg') }} 2400w" sizes="100vw"
                         width="2400" height="1200" fetchpriority="high"
                         alt="Official Philippine Coast Guard banner of its three functional commands: MSSC, MEPCOM and MARSLEC">
                    @foreach ([
                        ['mssc', 'MSSC', 'Maritime Safety Services Command', '#structure', 'View in the structure'],
                        ['mepcom', 'MEPCOM', 'Marine Environmental Protection Command', route('mepcom'), 'Open MEPCOM HRMIS'],
                        ['marslec', 'MARSLEC', 'Maritime Security and Law Enforcement Command', '#structure', 'View in the structure'],
                    ] as [$key, $code, $name, $href, $action])
                        <a class="ph-cmd ph-cmd--{{ $key }}" href="{{ $href }}" aria-label="{{ $name }} ({{ $code }}): {{ $action }}">
                            <span class="ph-cmd__img" aria-hidden="true"></span>
                            <span class="ph-cmd__title" aria-hidden="true"></span>
                            <span class="ph-cmd__chip" aria-hidden="true"><strong>{{ $code }}</strong> {{ $action }} <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="ph-hero__band">
                <div class="ph-wrap ph-hero__in" data-ph-reveal="hero">
                    <div>
                        <p class="ph-eyebrow ph-eyebrow--gold">Philippine Coast Guard · PCG HRMIS</p>
                        <h1 id="hero-title" data-ph-words>Guarding Our Seas.<span class="ph-hero__gold">Serving Our Nation.</span></h1>
                    </div>
                    <div>
                        <p class="ph-hero__lead">PCG HRMIS is the human resource gateway of the Philippine Coast Guard: personnel records,
                            registration, document review and training, brought together command by command.</p>
                        <div class="ph-actions">
                            <a class="ph-btn ph-btn--gold" href="#services">Explore digital services</a>
                            <a class="ph-btn ph-btn--outline" href="{{ route('mepcom') }}">Open MEPCOM HRMIS</a>
                        </div>
                    </div>
                </div>
                <p class="ph-wrap ph-credit">Banner: ASN Francis Xavier Bucoy II, Philippine Coast Guard. Official material, used with permission.</p>
            </div>
        </section>

        {{-- digital services --}}
        <section class="ph-section ph-services" id="services" aria-labelledby="services-title">
            <div class="ph-wrap">
                <p class="ph-eyebrow">Digital services</p>
                <h2 id="services-title" data-ph-reveal data-ph-words>Find the right service, sooner.</h2>
                <p class="ph-lead">MEPCOM HRMIS is the first command system on PCG HRMIS. Access to personnel records always requires signing in.</p>

                <div class="ph-feature" data-ph-reveal data-ph-stagger>
                    <article class="ph-card ph-card--azure">
                        <div class="ph-card__kicker"><span class="ph-card__icon"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span> Available now</div>
                        <h3>MEPCOM HRMIS</h3>
                        <p>Personnel information system of the Marine Environmental Protection Command: the Statement of Information,
                            required documents and 201 files, document review by unit checkers, and training tracks.</p>
                        <ul class="ph-tags" aria-label="Included">
                            <li>SOI profile</li><li>Required documents</li><li>201 files</li><li>Training</li>
                        </ul>
                        <a class="ph-btn ph-btn--gold" href="{{ route('mepcom') }}">Open MEPCOM HRMIS</a>
                    </article>

                    <article class="ph-card ph-card--light">
                        <div class="ph-card__kicker ph-card__kicker--gold"><span class="ph-card__icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></span> Join the system</div>
                        <h3>New personnel registration</h3>
                        <p>Officers and non-officers of MEPCOM register once. MEPCOM HR reviews every registration before access is granted.</p>
                        <div class="ph-actions">
                            <a class="ph-btn ph-btn--crimson" href="{{ route('register', ['type' => 'officer']) }}">Officer</a>
                            <a class="ph-btn ph-btn--crimson" href="{{ route('register', ['type' => 'non_officer']) }}">Non-officer</a>
                        </div>
                        <p class="ph-note">Have your serial number ready. A live photo is taken during registration.</p>
                    </article>
                </div>

                <ul class="ph-directory" data-ph-reveal data-ph-stagger>
                    <li>
                        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                        <h3>Personnel sign in</h3>
                        <p>Your SOI, documents, 201 files and training.</p>
                        <a href="{{ route('login') }}">Sign in <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </li>
                    <li>
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                        <h3>Unit document checkers</h3>
                        <p>Review the documents and 201 files of your unit's personnel.</p>
                        <a href="{{ url('/unit/login') }}">Unit sign in <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </li>
                    <li>
                        <i class="fa-solid fa-certificate" aria-hidden="true"></i>
                        <h3>OSAERT certificate check</h3>
                        <p>Verify an OSAERT training certificate by its number.</p>
                        <a href="{{ route('osaert.verify') }}">Verify a certificate <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </li>
                    <li>
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <h3>HR administration</h3>
                        <p>Records, registrations, reports and re-enlistment for authorised HR staff.</p>
                        <a href="{{ route('admin.login') }}">Authorised personnel only <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </li>
                </ul>
            </div>
        </section>

        {{-- PCG organizational structure --}}
        <section class="ph-section ph-org" id="structure" aria-labelledby="structure-title">
            <div class="ph-wrap">
                <p class="ph-eyebrow ph-eyebrow--gold">PCG organizational structure</p>
                <h2 id="structure-title" data-ph-reveal data-ph-words>One Coast Guard, six groups of commands.</h2>
                <p class="ph-lead ph-lead--inverse">National Headquarters leads every command of the Philippine Coast Guard. MEPCOM, one of the three functional commands, runs MEPCOM HRMIS.</p>

                <div class="ph-org__top" data-ph-reveal>
                    <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="" width="60" height="60" loading="lazy">
                    <div>
                        <strong>NHQ-PCG</strong>
                        <span>National Headquarters, Philippine Coast Guard</span>
                    </div>
                </div>

                <ol class="ph-org__groups" data-ph-reveal="line">
                    @foreach ($pcgGroups as $i => [$group, $icon, $commands])
                        <li class="ph-org__group @if ($i === 0) ph-org__group--wide @elseif (count($commands) === 1) ph-org__group--band @endif" data-ph-reveal>
                            <h3>
                                <span class="ph-org__icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span>
                                {{ $group }}
                                <span class="ph-org__count">{{ count($commands) }}</span>
                            </h3>
                            <ul data-ph-stagger>
                                @foreach ($commands as $command)
                                    @if (is_array($command))
                                        @php([$name, $code, $seal] = $command)
                                        <li class="ph-org__cmd @if ($code === 'MEPCOM') ph-org__cmd--here @endif">
                                            <img src="{{ asset($seal) }}" alt="" width="44" height="44" loading="lazy">
                                            <span><strong>{{ $code }}</strong>{{ $name }}</span>
                                            @if ($code === 'MEPCOM')
                                                <a href="{{ route('mepcom') }}">MEPCOM HRMIS <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                                            @endif
                                        </li>
                                    @else
                                        <li><span>{{ $command }}</span></li>
                                    @endif
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- contact --}}
        <section class="ph-cta" aria-labelledby="cta-title">
            <div class="ph-wrap ph-cta__in" data-ph-reveal>
                <div>
                    <p class="ph-eyebrow ph-eyebrow--gold">Contact</p>
                    <h2 id="cta-title" data-ph-words>Questions about your records?</h2>
                    <p>Send a message to the HR office of your command through the contact page.</p>
                </div>
                <a class="ph-btn ph-btn--gold" href="{{ route('contact') }}">Contact HR</a>
            </div>
        </section>
    </main>

    <footer class="ph-foot">
        <div class="ph-wrap ph-foot__in">
            <div class="ph-foot__brand">
                <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="" width="56" height="56" loading="lazy">
                <span><strong>PCG HRMIS</strong>Philippine Coast Guard</span>
            </div>
            <ul>
                <li><a href="{{ route('mepcom') }}">MEPCOM HRMIS</a></li>
                <li><a href="{{ route('login') }}">Personnel sign in</a></li>
                <li><a href="{{ route('osaert.verify') }}">OSAERT certificate check</a></li>
                <li><a href="{{ route('contact') }}">Contact</a></li>
            </ul>
        </div>
        <div class="ph-wrap ph-foot__base">
            <span>© {{ now()->year }} Philippine Coast Guard</span>
            <span>Authorised use only. Activity on this system may be logged.</span>
        </div>
    </footer>
</body>
</html>
