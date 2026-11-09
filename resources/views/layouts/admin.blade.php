{{--
    Admin / MEPTDI LMS app shell (sidebar + top bar).
    Sections: title, styles (extra <head> content), content, scripts.
    Page content may still use Tailwind utilities; the shell uses pcg-ui.css.
--}}
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Admin') · MEPCOM HRMIS</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/tailwind.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/pcg-ui.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/pcg-console.css') }}">
    {{-- theme before first paint: the viewer's saved choice, dark by default --}}
    <script nonce="{{ $cspNonce ?? '' }}">
        (function () { try { var t = localStorage.getItem('pcgTheme'); if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t); if (localStorage.getItem('pcgSidebar') === 'collapsed') document.documentElement.setAttribute('data-sidebar', 'collapsed'); } catch (e) {} })();
    </script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/pcg-theme.js') }}" defer></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/pcg-ui.js') }}" defer></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/pcg-navbar.js') }}" defer></script>
    @yield('styles')
</head>

<body class="pcg pcg-app">
    <a class="pcg-skip-link" href="#content">Skip to content</a>

    <div class="pcg-console" data-nav-open="false">
        <aside class="pcg-sidebar" id="sidebar" aria-label="Admin navigation">
            <a class="pcg-sidebar__brand" href="{{ route(\App\Http\Controllers\AdminLoginController::homeRoute(session('admin_role'))) }}">
                <img src="{{ asset('images/brand/mepcom-logo.webp') }}" alt="" width="40" height="40">
                <div>
                    <span>MEPCOM HRMIS</span>
                    <strong>Administration</strong>
                </div>
            </a>

            @php
                $role = session('admin_role');
                $hr = in_array($role, ['super_admin', 'hr_admin'], true);
                $lms = in_array($role, ['super_admin', 'lms_admin'], true);
                $super = $role === 'super_admin';
                $orgWide = !session('admin_org_unit_id'); // command/unit-scoped admins see scoped modules only
                $can = fn ($c) => \App\Support\AdminPermissions::can($c);
                $link = fn ($route, $icon, $label, $pattern) => ['href' => route($route), 'icon' => $icon, 'label' => $label, 'active' => request()->routeIs($pattern)];
            @endphp
            <nav class="pcg-nav">
                @if ($can('personnel.view'))
                    <div class="pcg-nav__section">HRMIS</div>
                    @include('layouts.partials.nav-link', $link('admin.hrmis.index', 'chart-column', 'Overview', 'admin.hrmis.*'))
                    @include('layouts.partials.nav-link', $link('admin.personnel.index', 'id-card', 'Personnel', 'admin.personnel.*'))
                    @if ($can('registration.review'))
                        @include('layouts.partials.nav-link', $link('admin.registrations.index', 'user-plus', 'Registrations', 'admin.registrations.*'))
                    @endif
                    @if ($can('documents.view'))
                        @include('layouts.partials.nav-link', $link('admin.documents.index', 'folder-open', '201 documents', 'admin.documents.*'))
                    @endif
                    @include('layouts.partials.nav-link', $link('admin.reports.index', 'table-list', 'Reports', 'admin.reports.*'))
                    @include('layouts.partials.nav-link', $link('admin.organization.index', 'sitemap', 'Organization', 'admin.organization.*'))
                @endif

                @if ($can('soi.adjutant') || $can('soi.reopen'))
                    <div class="pcg-nav__section">Statement of Information</div>
                    @if ($can('soi.adjutant'))
                        @include('layouts.partials.nav-link', $link('admin.adjutant.index', 'medal', 'CG awards and schooling', 'admin.adjutant.*'))
                    @endif
                    @if ($can('soi.reopen'))
                        @include('layouts.partials.nav-link', $link('admin.soi.index', 'file-signature', 'SOI submissions', 'admin.soi.*'))
                    @endif
                @endif

                @if ($can('reenlistment.view') && $orgWide)
                    <div class="pcg-nav__section">Re-enlistment</div>
                    @include('layouts.partials.nav-link', $link('admin.reenlistment.index', 'calendar-check', 'ETE watchlist', 'admin.reenlistment.*'))
                @endif

                @if ($hr && $orgWide)
                    <div class="pcg-nav__section">MEPCOM registrations</div>
                    @include('layouts.partials.nav-link', $link('admin.dashboard', 'gauge-high', 'Self-registrations', 'admin.dashboard'))
                    @include('layouts.partials.nav-link', $link('admin.hr-analytics', 'chart-line', 'HR analytics', 'admin.hr-analytics'))
                    @include('layouts.partials.nav-link', $link('admin.unit-users.index', 'user-shield', 'Unit accounts', 'admin.unit-users.*'))
                    @include('layouts.partials.nav-link', $link('admin.messages.index', 'envelope', 'Messages', 'admin.messages.*'))
                @endif

                @if (($hr || $lms) && $orgWide)
                    <div class="pcg-nav__section">Training</div>
                    @include('layouts.partials.nav-link', $link('admin.training-applications.index', 'file-signature', 'Training applications', 'admin.training-applications.*'))
                    @include('layouts.partials.nav-link', $link('administrative.tracks', 'graduation-cap', 'Administrative track', 'administrative.tracks'))
                    @include('layouts.partials.nav-link', $link('operation_tracks.index', 'ship', 'Operational track', 'operation_tracks.*'))
                    @include('layouts.partials.nav-link', $link('personnel.administrative.training', 'user-graduate', 'Administrative training', 'personnel.administrative.training'))
                    @include('layouts.partials.nav-link', $link('personnel.operational.training', 'anchor', 'Operational training', 'personnel.operational.training'))
                @endif

                @if ($lms)
                    <div class="pcg-nav__section">MEPTDI LMS</div>
                    @include('layouts.partials.nav-link', ['href' => route('admin.training-courses.index'), 'icon' => 'book-open', 'label' => 'Training courses', 'active' => request()->routeIs('admin.training-courses.*') || request()->routeIs('training-courses.students.*')])
                    @include('layouts.partials.nav-link', $link('admin.osaert.index', 'water', 'OSAERT trainees', 'admin.osaert.index'))
                    @include('layouts.partials.nav-link', $link('admin.osaert.certificates', 'certificate', 'OSAERT certificates', 'admin.osaert.certificates'))
                @endif

                @if ($super || ($can('audit.view') && $orgWide))
                    <div class="pcg-nav__section">Security</div>
                    @if ($super)
                        @include('layouts.partials.nav-link', $link('admin.accounts.index', 'users-gear', 'Admin accounts', 'admin.accounts.index'))
                    @endif
                    @include('layouts.partials.nav-link', $link('admin.accounts.audit', 'clipboard-list', 'Audit log', 'admin.accounts.audit'))
                @endif
            </nav>
        </aside>
        <div class="pcg-backdrop" data-pcg-nav-close></div>

        <div class="pcg-console__main">
            @php
                $adminName = session('admin_name') ?: session('admin_email', 'Administrator');
                $initials = collect(preg_split('/[\s@._-]+/', (string) $adminName))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'A';
                $unitCode = session('admin_org_unit_id') ? \App\Models\OrgUnit::whereKey(session('admin_org_unit_id'))->value('code') : null;
                $pendingRegistrations = null;
                if ($hr && $orgWide) {
                    try { $pendingRegistrations = \App\Models\User::where('status', 'Pending')->count(); } catch (\Throwable $e) { $pendingRegistrations = null; }
                }
            @endphp
            <header class="pcg-topbar pcg-navbar">
                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm pcg-topbar__menu" data-pcg-nav-toggle
                    aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <button type="button" class="pcg-navbar__icon pcg-navbar__collapse" data-pcg-sidebar-toggle
                    aria-controls="sidebar" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
                    <i class="fa-solid fa-angles-left" aria-hidden="true"></i>
                </button>
                <h1 class="pcg-topbar__title">@yield('title', 'Admin')</h1>

                <div class="pcg-navbar__search" role="search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="pcg-jump" class="pcg-navbar__input" placeholder="Go to a page…" autocomplete="off"
                        role="combobox" aria-expanded="false" aria-controls="pcg-jump-list" aria-autocomplete="list" aria-label="Go to a page">
                    <kbd class="pcg-navbar__kbd" aria-hidden="true">/</kbd>
                    <ul class="pcg-navbar__results" id="pcg-jump-list" role="listbox" hidden></ul>
                </div>

                <div class="pcg-navbar__actions">
                    @if ($pendingRegistrations !== null)
                        <a href="{{ route('admin.dashboard') }}" class="pcg-navbar__icon pcg-navbar__bell"
                            aria-label="{{ $pendingRegistrations }} pending {{ $pendingRegistrations === 1 ? 'registration' : 'registrations' }}"
                            title="{{ $pendingRegistrations }} pending {{ $pendingRegistrations === 1 ? 'registration' : 'registrations' }}">
                            <i class="fa-regular fa-bell" aria-hidden="true"></i>
                            @if ($pendingRegistrations > 0)
                                <span class="pcg-navbar__badge">{{ $pendingRegistrations > 99 ? '99+' : $pendingRegistrations }}</span>
                            @endif
                        </a>
                    @endif
                    <button type="button" class="pcg-navbar__icon pcg-theme-toggle" data-pcg-theme-toggle aria-pressed="false" title="Switch theme">
                        <span class="when-dark"><i class="fa-solid fa-sun" aria-hidden="true"></i></span>
                        <span class="when-light"><i class="fa-solid fa-moon" aria-hidden="true"></i></span>
                        <span class="pcg-visually-hidden">theme</span>
                    </button>

                    <div class="pcg-navbar__user">
                        <button type="button" class="pcg-navbar__me" data-pcg-user-toggle aria-expanded="false" aria-controls="pcg-user-menu">
                            <span class="pcg-navbar__avatar" aria-hidden="true">{{ $initials }}</span>
                            <span class="pcg-navbar__who">
                                <strong>{{ $adminName }}</strong>
                                <span>{{ str_replace('_', ' ', session('admin_role', 'admin')) }}{{ $unitCode ? ' · ' . $unitCode : '' }}</span>
                            </span>
                            <i class="fa-solid fa-chevron-down pcg-navbar__chev" aria-hidden="true"></i>
                            <span class="pcg-visually-hidden">Account menu</span>
                        </button>
                        <div class="pcg-navbar__menu" id="pcg-user-menu" hidden>
                            <div class="pcg-navbar__menu-head">
                                <span class="pcg-navbar__avatar pcg-navbar__avatar--lg" aria-hidden="true">{{ $initials }}</span>
                                <div>
                                    <strong>{{ $adminName }}</strong>
                                    <span>{{ session('admin_email') }}</span>
                                    <span class="pcg-navbar__role">{{ str_replace('_', ' ', session('admin_role', 'admin')) }}{{ $unitCode ? ' · ' . $unitCode : '' }}</span>
                                </div>
                            </div>
                            <form action="{{ route('admin.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="pcg-navbar__menu-item">
                                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign out
                                </button>
                            </form>
                        </div>
                    </div>
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
