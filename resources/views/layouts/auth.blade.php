{{--
    Sign-in shell for applicant, admin and unit-checker logins.
    Uses public/css/pcg-ui.css only (no CDN). Sections:
      title, eyebrow, headline, intro, content
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Sign in') · MEPCOM HRMIS</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/pcg-ui.css') }}">
    <script src="{{ asset('js/pcg-ui.js') }}" defer></script>
</head>

<body class="pcg">
    <a class="pcg-skip-link" href="#main">Skip to sign-in form</a>

    <div class="pcg-auth">
        <aside class="pcg-auth__brand" style="--pcg-auth-image: url('{{ asset('images/brand/login-hero.webp') }}')">
            <div class="pcg-auth__seals">
                <img src="{{ asset('images/brand/pcg-logo.webp') }}" alt="Philippine Coast Guard seal" width="64" height="64">
                <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="Marine Environmental Protection Command seal" width="64" height="64">
                <div class="pcg-auth__org">
                    <span>Philippine Coast Guard</span>
                    <strong>Marine Environmental Protection Command</strong>
                </div>
            </div>

            <div class="pcg-auth__headline">
                <p class="pcg-auth__eyebrow">@yield('eyebrow', 'MEPCOM HRMIS')</p>
                <h1>@yield('headline', 'Human Resource Management Integrated System')</h1>
                <p>@yield('intro', 'Sign in to manage your personnel records, documents and training.')</p>
                <div class="pcg-auth__rule" aria-hidden="true"></div>
            </div>

            <p class="pcg-auth__footnote">Authorized personnel only. Activity on this system may be logged.</p>
        </aside>

        <main class="pcg-auth__main" id="main">
            <nav class="pcg-auth__topbar" aria-label="Site">
                <a href="{{ route('home') }}">Home</a>
                <a href="{{ route('about') }}">About</a>
                <a href="{{ route('contact') }}">Contact</a>
            </nav>

            <div class="pcg-auth__panel">
                <div class="pcg-auth__card">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>
</body>

</html>
