{{--
    Unit checker app shell (own-unit and M1 checkers): sidebar + top bar on the
    pcg-ui design system, like layouts/personnel. Sections: title, styles, content, scripts.
--}}
@php
    $isM1 = session('unit_role') === \App\Models\UnitUser::ROLE_M1;
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Document review') · MEPCOM HRMIS</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/pcg-ui.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/unit-checker.css') }}">
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/pcg-ui.js') }}" defer></script>
    @yield('styles')
</head>

<body class="pcg">
    <a class="pcg-skip-link" href="#content">Skip to content</a>

    <div class="pcg-console" data-nav-open="false">
        <aside class="pcg-sidebar" id="sidebar" aria-label="Unit checker navigation">
            <a class="pcg-sidebar__brand" href="{{ $isM1 ? route('unit.dashboard.m1') : route('unit.dashboard') }}">
                <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="" width="40" height="40">
                <div>
                    <span>MEPCOM HRMIS</span>
                    <strong>{{ $isM1 ? 'M1 checker' : 'Unit checker' }}</strong>
                </div>
            </a>

            <nav class="pcg-nav">
                <div class="pcg-nav__section">{{ $isM1 ? 'All units' : session('unit_name') }}</div>
                @if ($isM1)
                    @include('layouts.partials.nav-link', ['href' => route('unit.dashboard.m1'), 'icon' => 'clipboard-check', 'label' => 'Document review', 'active' => request()->routeIs('unit.dashboard.m1')])
                    @include('layouts.partials.nav-link', ['href' => route('unit.201files'), 'icon' => 'folder-open', 'label' => '201 file review', 'active' => request()->routeIs('unit.201files')])
                @else
                    @include('layouts.partials.nav-link', ['href' => route('unit.dashboard'), 'icon' => 'clipboard-check', 'label' => 'Document review', 'active' => request()->routeIs('unit.dashboard')])
                    @include('layouts.partials.nav-link', ['href' => route('unit.201files'), 'icon' => 'folder-open', 'label' => '201 file review', 'active' => request()->routeIs('unit.201files')])
                @endif
            </nav>
        </aside>
        <div class="pcg-backdrop" data-pcg-nav-close></div>

        <div class="pcg-console__main">
            <header class="pcg-topbar">
                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm pcg-topbar__menu" data-pcg-nav-toggle
                    aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <h1 class="pcg-topbar__title">@yield('title', 'Document review')</h1>
                <div class="pcg-topbar__user">
                    <span><strong>{{ session('unit_full_name') }}</strong> · {{ $isM1 ? 'M1, all units' : session('unit_name') }}</span>
                    <form action="{{ route('unit.logout') }}" method="POST">
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
