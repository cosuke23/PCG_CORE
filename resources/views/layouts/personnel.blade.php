{{--
    Personnel (applicant) app shell: sidebar + top bar.
    Sections: title, styles, content, scripts.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Dashboard') · MEPCOM HRMIS</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pcg-ui.css') }}">
    <script src="{{ asset('js/pcg-ui.js') }}" defer></script>
    @yield('styles')
</head>

<body class="pcg">
    <a class="pcg-skip-link" href="#content">Skip to content</a>

    <div class="pcg-console" data-nav-open="false">
        <aside class="pcg-sidebar" id="sidebar" aria-label="Personnel navigation">
            <a class="pcg-sidebar__brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="" width="40" height="40">
                <div>
                    <span>MEPCOM HRMIS</span>
                    <strong>Personnel</strong>
                </div>
            </a>

            <nav class="pcg-nav">
                <div class="pcg-nav__section">My records</div>
                @include('layouts.partials.nav-link', ['href' => route('dashboard'), 'icon' => 'house', 'label' => 'Dashboard', 'active' => request()->routeIs('dashboard')])
                @include('layouts.partials.nav-link', ['href' => route('profile'), 'icon' => 'id-card', 'label' => 'SOI profile', 'active' => request()->routeIs('profile')])
                @include('layouts.partials.nav-link', ['href' => route('upload.documents'), 'icon' => 'file-arrow-up', 'label' => 'Required documents', 'active' => request()->routeIs('upload.documents')])
                @include('layouts.partials.nav-link', ['href' => route('upload.201'), 'icon' => 'folder-open', 'label' => '201 files', 'active' => request()->routeIs('upload.201')])
                @include('layouts.partials.nav-link', ['href' => route('generate.soi'), 'icon' => 'print', 'label' => 'Print my SOI', 'active' => false])

                <div class="pcg-nav__section">Training</div>
                @include('layouts.partials.nav-link', ['href' => route('applicant.administrative.training'), 'icon' => 'graduation-cap', 'label' => 'Administrative track', 'active' => request()->routeIs('applicant.administrative.training')])
                @include('layouts.partials.nav-link', ['href' => route('applicant.operational.training'), 'icon' => 'ship', 'label' => 'Operational track', 'active' => request()->routeIs('applicant.operational.training')])
            </nav>
        </aside>
        <div class="pcg-backdrop" data-pcg-nav-close></div>

        <div class="pcg-console__main">
            <header class="pcg-topbar">
                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm pcg-topbar__menu" data-pcg-nav-toggle
                    aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <h1 class="pcg-topbar__title">@yield('title', 'Dashboard')</h1>
                <div class="pcg-topbar__user">
                    <span><strong>{{ auth()->user()->name }}</strong> · {{ auth()->user()->rank }} {{ auth()->user()->serial_number }}</span>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign out
                        </button>
                    </form>
                </div>
            </header>

            @include('layouts.partials.flash')

            <main class="pcg-console__content" id="content">
                @yield('content')
            </main>
        </div>
    </div>

    @yield('scripts')
</body>

</html>
