<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard')</title>

    <script src="{{ asset('vendor/alpinejs/alpine-3.14.9.min.js') }}" defer></script>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('vendor/leaflet-geocoder/Control.Geocoder.css') }}" />
    <link href="{{ asset('vendor/bootstrap/bootstrap-5.3.1.min.css') }}" rel="stylesheet">
    <script src="{{ asset('vendor/bootstrap/bootstrap-5.3.1.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/inter/inter.css') }}">
    <style>
        /* Inter is self-hosted: vendor/inter/inter.css */

        body {
            font-family: 'Inter', sans-serif;
        }

        /* Floating Navbar Animation & Glass Effect */
        .glass-nav {
            background: rgba(10, 25, 60, 0.8);
            /* Dark Navy with Transparency */
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 215, 0, 0.2);
            /* Subtle Gold Border */
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-sticky {
            margin-top: 10px !important;
            width: 95% !important;
            border-radius: 9999px !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
    </style>
    <style>
        .logo-card {
            display: inline-block;
            background: transparent !important;
            padding: 0 !important;
            box-shadow: none !important;
        }

        .logo-img {
            width: 100px;
            /* or desired size */
            height: auto;
        }

        /* Hover Effect */
        .logo-card:hover .logo-img {
            transform: scale(1.3) rotate(360deg);
        }

        /* Optional: slight card lift */
        .logo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .logo-card {
            padding: 15px;
            border-radius: 16px;
            background: #f8fafc;
            transition: all 0.4s ease;
        }

        .logo-img {
            width: 110px;
            height: 110px;
            object-fit: contain;
            transition: transform 0.6s ease;
        }

        .logo-card:hover .logo-img {
            transform: scale(1.3) rotate(360deg);
        }

        .logo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pcgblue: '#0033A0',
                        mepcomGold: '#FFD700',
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
</head>

<body class="min-h-screen antialiased bg-gray-100" x-data="soiHandler()">

    <div class="fixed top-0 left-0 w-full flex justify-center z-50 pt-4 px-4 transition-all duration-300"
        id="nav-container">
        <nav id="navbar" class="w-full max-w-7xl glass-nav rounded-2xl px-6 py-2 transition-all duration-300">
            <div class="flex items-center justify-between h-14">

                <div class="flex-shrink-0 flex items-center space-x-4">

                    <!-- Logos -->
                    <div class="flex items-center space-x-2 border-r border-white/20 pr-4">
                        <img src="{{ asset('backgrounds/pcg logo.png') }}" alt="DOTr"
                            class="h-9 w-auto drop-shadow-sm">
                        <img src="{{ asset('backgrounds/DOTR.png') }}" alt="PCG" class="h-9 w-auto drop-shadow-sm">
                        <img src="{{ asset('backgrounds/bagong_pilipinas.png') }}" alt="Bagong Pilipinas"
                            class="h-9 w-auto drop-shadow-sm">
                    </div>

                    <!-- Title + Marquee -->
                    <div class="flex items-center space-x-4 overflow-hidden">

                        <!-- Logo + Title -->
                        <div class="flex items-center space-x-2">
                            <div
                                class="hidden sm:block w-8 h-8 bg-white rounded-full flex items-center justify-center p-1 shadow-inner">
                                <img src="{{ asset('backgrounds/mepcom_logo.png') }}" alt="MEPCOM Logo">
                            </div>
                            @if (session('user_id'))
                                <a href="{{ url('/dashboard') }}"
                                    class="text-white text-sm md:text-lg font-extrabold tracking-tight hover:text-mepcomGold transition">
                                    ← Back to Dashboard
                                </a>
                            @else
                                <a href="{{ route('home') }}"
                                    class="text-white text-sm md:text-lg font-extrabold tracking-tight hover:text-mepcomGold transition">
                                    MEPCOM <span class="text-mepcomGold font-light">HRMIS</span>
                                </a>
                            @endif
                        </div>

                        <!-- Marquee -->
                        <div class="relative w-48 md:w-64 overflow-hidden">
                            <div
                                class="whitespace-nowrap animate-marquee text-mepcomGold font-semibold text-sm md:text-base flex items-center gap-2">
                                <img src="/backgrounds/mini_boat.png" alt="Boat" class="w-20 h-20 inline-block">
                                Welcome Aboard!
                                <img src="/backgrounds/mini_boat.png" alt="Boat" class="w-20 h-20 inline-block">
                                Welcome Aboard!
                                <img src="/backgrounds/mini_boat.png" alt="Boat" class="w-20 h-20 inline-block">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Animation -->
                <style>
                    @keyframes marquee {
                        0% {
                            transform: translateX(100%);
                        }

                        100% {
                            transform: translateX(-100%);
                        }
                    }

                    .animate-marquee {
                        display: inline-block;
                        animation: marquee 10s linear infinite;
                    }

                    .input-modern {
                        @apply w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:outline-none;
                    }
                </style>

                <div class="hidden md:block">
                    <div class="flex items-center space-x-1">
                        @if (session('user_id'))
                            <button @click="openSOIModal = true"
                                class="p-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">

                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 4v12m0 0l-4-4m4 4l4-4" />
                                </svg>
                            </button>
                            <a href="#"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                                class="text-white hover:bg-red-600 px-4 py-2 rounded-full text-xs font-bold transition">
                                LOGOUT
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf
                            </form>
                        @else
                            <a href="{{ route('home') }}"
                                class="px-4 py-2 text-xs font-bold rounded-full transition {{ Route::is('home') ? 'bg-blue-600 text-white' : 'text-gray-200 hover:text-white hover:bg-white/10' }}">
                                HOME
                            </a>
                            <a href="{{ route('about') }}"
                                class="px-4 py-2 text-xs font-bold rounded-full transition {{ Route::is('about') ? 'bg-blue-600 text-white' : 'text-gray-200 hover:text-white hover:bg-white/10' }}">
                                ABOUT
                            </a>
                            <a href="{{ route('contact') }}"
                                class="px-4 py-2 text-xs font-bold rounded-full transition {{ Route::is('contact') ? 'bg-blue-600 text-white' : 'text-gray-200 hover:text-white hover:bg-white/10' }}">
                                CONTACT
                            </a>

                            <div class="flex items-center ml-4 space-x-2 border-l border-white/20 pl-4">
                                <a href="{{ route('login') }}"
                                    class="text-white text-xs font-bold hover:text-mepcomGold px-3 transition">LOGIN</a>
                                <button data-bs-toggle="modal" data-bs-target="#registerModal"
                                    class="bg-mepcomGold hover:bg-yellow-500 text-blue-900 px-5 py-2 rounded-full shadow-lg text-xs font-black transition transform hover:scale-105">
                                    REGISTER
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-button" class="text-white p-2">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16m-7 6h7" />
                        </svg>
                    </button>
                </div>

            </div>
        </nav>
    </div>

    <div id="mobile-menu"
        class="hidden fixed inset-0 z-40 bg-blue-900/95 backdrop-blur-lg flex flex-col items-center justify-center space-y-6 md:hidden">
        <button id="close-mobile-menu" class="absolute top-8 right-8 text-white text-3xl">&times;</button>
        <a href="{{ route('home') }}" class="text-2xl text-white font-bold">HOME</a>
        <a href="{{ route('about') }}" class="text-2xl text-white font-bold">ABOUT</a>
        <a href="{{ route('contact') }}" class="text-2xl text-white font-bold">CONTACT</a>
        <a href="{{ route('login') }}" class="text-2xl text-mepcomGold font-bold">LOGIN</a>
    </div>

    <main>
        @yield('content')
    </main>

    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-3xl overflow-hidden border-0 shadow-2xl">
                <div class="bg-blue-900 p-6 text-center">
                    <h5 class="text-white font-black text-xl">CREATE ACCOUNT</h5>
                    <p class="text-blue-200 text-xs">Select your registration category</p>
                </div>
                <div class="p-8 bg-white">
                    <div class="grid grid-cols-1 gap-4">
                        <a href="{{ route('register', ['type' => 'officer']) }}"
                            class="group p-4 border-2 border-gray-100 rounded-2xl hover:border-blue-600 transition flex items-center justify-between">
                            <div>
                                <h6 class="font-bold text-gray-800 group-hover:text-blue-600">Officer</h6>
                                <p class="text-xs text-gray-400">Commissioned Officer Program</p>
                            </div>
                            <span class="text-blue-600 opacity-0 group-hover:opacity-100 transition">→</span>
                        </a>
                        <a href="{{ route('register', ['type' => 'non_officer']) }}"
                            class="group p-4 border-2 border-gray-100 rounded-2xl hover:border-blue-600 transition flex items-center justify-between">
                            <div>
                                <h6 class="font-bold text-gray-800 group-hover:text-blue-600">Non-Officer</h6>
                                <p class="text-xs text-gray-400">Regular Enlistment Program</p>
                            </div>
                            <span class="text-blue-600 opacity-0 group-hover:opacity-100 transition">→</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ================= MODAL ================= -->
    <div x-show="openSOIModal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-50">

        <div class="bg-white rounded-xl p-6 w-96 text-center shadow-xl">

            <h2 class="text-lg font-bold mb-3">Generate SOI</h2>

            <p class="text-sm text-gray-600 mb-5">
                Do you want to generate your Statement of Intent (SOI)?
            </p>

            <div class="flex justify-center gap-3">
                <button @click="openSOIModal = false" class="px-4 py-2 bg-gray-300 rounded-lg">
                    Cancel
                </button>

                <button @click="generateSOI()" class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                    Yes, Generate
                </button>
            </div>
        </div>
    </div>

    <!-- ================= LOADER ================= -->
    <div x-show="loadingSOI" class="fixed inset-0 flex flex-col items-center justify-center bg-white/80 z-50">

        <img src="/backgrounds/mini_boat.png" class="w-20 h-20 animate-bounce mb-4">

        <p class="text-sm font-medium text-gray-700">
            Generating your SOI, please wait...
        </p>
    </div>

    <!-- ================= ALPINE ================= -->
    <script>
        function soiHandler() {

            return {

                openSOIModal: false,
                loadingSOI: false,

                generateSOI() {

                    this.loadingSOI = true;

                    setTimeout(() => {

                        this.loadingSOI = false;

                        window.open(`/generate-soi`, '_blank');

                    }, 1500);
                }
            }
        }
    </script>

    <script>
        const navbar = document.getElementById('navbar');
        const navContainer = document.getElementById('nav-container');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                navbar.classList.add('nav-sticky');
                navContainer.classList.remove('pt-4');
                navContainer.classList.add('pt-2');
            } else {
                navbar.classList.remove('nav-sticky');
                navContainer.classList.add('pt-4');
                navContainer.classList.remove('pt-2');
            }
        });

        // Mobile Menu Logic
        const btn = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');
        const close = document.getElementById('close-mobile-menu');

        btn.addEventListener('click', () => menu.classList.remove('hidden'));
        close.addEventListener('click', () => menu.classList.add('hidden'));
    </script>


</body>

</html>
