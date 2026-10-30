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
            @endphp
            <nav class="pcg-nav">
                @if ($hr)
                    <div class="pcg-nav__section">Overview</div>
                    @include('layouts.partials.nav-link', ['href' => route('admin.dashboard'), 'icon' => 'gauge-high', 'label' => 'Dashboard', 'active' => request()->routeIs('admin.dashboard')])
                    @include('layouts.partials.nav-link', ['href' => route('admin.hr-analytics'), 'icon' => 'chart-line', 'label' => 'HR analytics', 'active' => request()->routeIs('admin.hr-analytics')])
                    @include('layouts.partials.nav-link', ['href' => route('admin.unit-users.index'), 'icon' => 'user-shield', 'label' => 'Unit accounts', 'active' => request()->routeIs('admin.unit-users.*')])
                    @include('layouts.partials.nav-link', ['href' => route('admin.messages.index'), 'icon' => 'envelope', 'label' => 'Messages', 'active' => request()->routeIs('admin.messages.*')])
                @endif

                <div class="pcg-nav__section">Training</div>
                @include('layouts.partials.nav-link', ['href' => route('admin.training-applications.index'), 'icon' => 'file-signature', 'label' => 'Training applications', 'active' => request()->routeIs('admin.training-applications.*')])
                @include('layouts.partials.nav-link', ['href' => route('administrative.tracks'), 'icon' => 'graduation-cap', 'label' => 'Administrative track', 'active' => request()->routeIs('administrative.tracks')])
                @include('layouts.partials.nav-link', ['href' => route('operation_tracks.index'), 'icon' => 'ship', 'label' => 'Operational track', 'active' => request()->routeIs('operation_tracks.*')])
                @include('layouts.partials.nav-link', ['href' => route('personnel.administrative.training'), 'icon' => 'user-graduate', 'label' => 'Administrative training', 'active' => request()->routeIs('personnel.administrative.training')])
                @include('layouts.partials.nav-link', ['href' => route('personnel.operational.training'), 'icon' => 'anchor', 'label' => 'Operational training', 'active' => request()->routeIs('personnel.operational.training')])

                @if ($lms)
                    <div class="pcg-nav__section">MEPTDI LMS</div>
                    @include('layouts.partials.nav-link', ['href' => route('admin.training-courses.index'), 'icon' => 'book-open', 'label' => 'Training courses', 'active' => request()->routeIs('admin.training-courses.*') || request()->routeIs('training-courses.students.*')])
                    @include('layouts.partials.nav-link', ['href' => route('admin.osaert.index'), 'icon' => 'water', 'label' => 'OSAERT trainees', 'active' => request()->routeIs('admin.osaert.index')])
                    @include('layouts.partials.nav-link', ['href' => route('admin.osaert.certificates'), 'icon' => 'certificate', 'label' => 'OSAERT certificates', 'active' => request()->routeIs('admin.osaert.certificates')])
                @endif

                @if ($super)
                    <div class="pcg-nav__section">Security</div>
                    @include('layouts.partials.nav-link', ['href' => route('admin.accounts.index'), 'icon' => 'users-gear', 'label' => 'Admin accounts', 'active' => request()->routeIs('admin.accounts.index')])
                    @include('layouts.partials.nav-link', ['href' => route('admin.accounts.audit'), 'icon' => 'clipboard-list', 'label' => 'Audit log', 'active' => request()->routeIs('admin.accounts.audit')])
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
                        · {{ str_replace('_', ' ', session('admin_role', 'admin')) }}</span>
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
