{{--
    Admin / MEPTDI LMS app shell (sidebar + top bar).
    Sections: title, styles (extra <head> content), content, scripts.
    Page content may still use Tailwind utilities; the shell uses pcg-ui.css.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Admin') · MEPCOM HRMIS</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pcg-ui.css') }}">
    <script src="{{ asset('js/pcg-ui.js') }}" defer></script>
    @yield('styles')
</head>

<body class="pcg">
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
            <header class="pcg-topbar">
                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm pcg-topbar__menu" data-pcg-nav-toggle
                    aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>
                <h1 class="pcg-topbar__title">@yield('title', 'Admin')</h1>
                <div class="pcg-topbar__user">
                    <span>Signed in as <strong>{{ session('admin_name') ?: session('admin_email', 'Administrator') }}</strong>
                        · {{ str_replace('_', ' ', session('admin_role', 'admin')) }}{{ session('admin_org_unit_id') ? ' · ' . \App\Models\OrgUnit::whereKey(session('admin_org_unit_id'))->value('code') : '' }}</span>
                    <form action="{{ route('admin.logout') }}" method="POST">
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
