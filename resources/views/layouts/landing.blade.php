{{--
    Public pages (home, about, MEPTDI): header, footer and the landing design (css/landing.css).
    Sections: title, description, backdrop (optional), content.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'MEPCOM HRMIS · Philippine Coast Guard')</title>
    <meta name="description" content="@yield('description', 'Personnel information system of the PCG Marine Environmental Protection Command: registration, service records, document review and training.')">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @include('partials.share-meta')
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/landing.css') }}">
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/landing.js') }}" defer></script>
</head>
<body class="lp @yield('body_class')">
    <a class="lp-skip" href="#main">Skip to content</a>
    @yield('backdrop')

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
                    [route('home'), 'Home', request()->routeIs('home')],
                    [route('about'), 'About', request()->routeIs('about')],
                    [route('home') . '#services', 'Services', false],
                    [route('home') . '#units', 'Units', false],
                    [route('meptdi'), 'MEPTDI', request()->routeIs('meptdi')],
                    [route('contact'), 'Contact', request()->routeIs('contact')],
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
        @yield('content')
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
                    <li><a href="{{ route('home') }}#join">Register</a></li>
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
