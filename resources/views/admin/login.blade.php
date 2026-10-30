@extends('layouts.app')

@section('title', 'Admin Login')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <div class="min-h-screen flex items-center justify-center relative overflow-hidden
    bg-cover bg-center bg-no-repeat"
    style="background-image: url('{{ asset('backgrounds/web bg profile.jpg') }}');">

        <!-- INNER ORBIT (6 LOGOS) -->
        <div class="orbit-inner animate-spin-slow">
            <img src="{{ asset('backgrounds/MEPAcCES.png') }}" class="logo inner pos1">
            <img src="{{ asset('backgrounds/MEPCOM-HSG.png') }}" class="logo inner pos2">
            <img src="{{ asset('backgrounds/MEPLEF.png') }}" class="logo inner pos3">
            <img src="{{ asset('backgrounds/MEPTI.png') }}" class="logo inner pos4">
            <img src="{{ asset('backgrounds/MSIF.png') }}" class="logo inner pos5">
            <img src="{{ asset('backgrounds/NOCOP.png') }}" class="logo inner pos6">
        </div>

        <!-- OUTER ORBIT (16 LOGOS) -->
        <div class="orbit-outer animate-spin-reverse">
            <img src="{{ asset('backgrounds/MEP ERG BARMM.png') }}" class="logo outer o1">
            <img src="{{ asset('backgrounds/MEP ERG BICOL.png') }}" class="logo outer o2">
            <img src="{{ asset('backgrounds/MEP ERG CENTRAL VISAYAS.png') }}" class="logo outer o3">
            <img src="{{ asset('backgrounds/MEP ERG EASTERN VISAYAS.png') }}" class="logo outer o4">
            <img src="{{ asset('backgrounds/MEP ERG NATIONAL CAPITAL REGION.png') }}" class="logo outer o5">
            <img src="{{ asset('backgrounds/MEP ERG NORTH EASTERN LUZON.png') }}" class="logo outer o6">
            <img src="{{ asset('backgrounds/MEP ERG NORTH WESTERN LUZON.png') }}" class="logo outer o7">
            <img src="{{ asset('backgrounds/MEP ERG NORTHEASTERN MINDANAO.png') }}" class="logo outer o8">
            <img src="{{ asset('backgrounds/MEP ERG NORTHERN MINDANAO.png') }}" class="logo outer o9">
            <img src="{{ asset('backgrounds/MEP ERG PALAWAN.png') }}" class="logo outer o10">
            <img src="{{ asset('backgrounds/MEP ERG SOUTHEASTERN MINDANAO.png') }}" class="logo outer o11">
            <img src="{{ asset('backgrounds/MEP ERG SOUTHERN MINDANAO.png') }}" class="logo outer o12">
            <img src="{{ asset('backgrounds/MEP ERG SOUTHERN TAGALOG.png') }}" class="logo outer o13">
            <img src="{{ asset('backgrounds/MEP ERG SOUTHERN VISAYAS.png') }}" class="logo outer o14">
            <img src="{{ asset('backgrounds/MEP ERG SOUTHWESTERN MINDANAO.png') }}" class="logo outer o15">
            <img src="{{ asset('backgrounds/MEP ERG WESTERN VISAYAS.png') }}" class="logo outer o16">
        </div>

        <!-- RADAR RINGS -->
        <div class="radar-ring ring1"></div>
        <div class="radar-ring ring2"></div>
        <div class="radar-ring ring3"></div>

        <!-- CENTER LOGO -->
        <div id="centerLogo" class="cursor-pointer z-20 relative flex flex-col items-center">
            <img src="{{ asset('backgrounds/mepcom_logo.png') }}" class="w-32 h-32 object-contain golden-glow">

            <p class="text-yellow-400 mt-3 text-sm tracking-widest animate-pulse">
                CLICK TO ACCESS
            </p>
        </div>

        <!-- LOGIN CARD -->
        <div id="loginCard"
            class="hidden absolute z-30 glow-card bg-white/10 backdrop-blur-xl rounded-2xl p-6 w-full max-w-sm text-white border border-white/20 shadow-2xl">

            <h2 class="text-2xl font-bold text-center mb-5 tracking-widest">
                ADMIN ACCESS
            </h2>

            <form action="{{ route('admin.login.submit') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="block mb-1 text-xs">Email</label>
                    <input type="email" name="email" required
                        class="w-full px-3 py-2 rounded-md bg-black/40 border border-blue-400/40 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-3 relative">
                    <label class="block mb-1 text-xs">Password</label>
                    <input type="password" name="password" id="password" required
                        class="w-full px-3 py-2 rounded-md bg-black/40 border border-blue-400/40 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">

                    <button type="button" id="togglePassword" class="absolute right-3 top-8 text-blue-300 text-xs">
                        Show
                    </button>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 py-2 rounded-md text-sm font-semibold">
                    LOGIN
                </button>
            </form>
        </div>

    </div>

    <!-- SCRIPTS -->
    <script>
        const centerLogo = document.getElementById('centerLogo');
        const loginCard = document.getElementById('loginCard');

        centerLogo.addEventListener('click', () => {
            centerLogo.style.display = 'none';
            loginCard.classList.remove('hidden');
        });

        // Password toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const pass = document.getElementById('password');
            const type = pass.type === 'password' ? 'text' : 'password';
            pass.type = type;
            this.textContent = type === 'password' ? 'Show' : 'Hide';
        });
    </script>

    <!-- STYLES -->
    <style>
        /* INNER ORBIT */
        .orbit-inner {
            position: absolute;
            width: 350px;
            height: 350px;
        }

        /* OUTER ORBIT */
        .orbit-outer {
            position: absolute;
            width: 600px;
            height: 600px;
        }

        /* LOGOS */
        .logo {
            position: absolute;
            object-fit: contain;
        }

        /* INNER LOGOS */
        .logo.inner {
            width: 70px;
            height: 70px;
            filter: drop-shadow(0 0 10px cyan);
        }

        /* OUTER LOGOS */
        .logo.outer {
            width: 50px;
            height: 50px;
            filter: drop-shadow(0 0 6px #00ffff);
        }

        /* INNER POSITIONS */
        .pos1 {
            top: 0%;
            left: 50%;
            transform: translate(-50%, 0);
        }

        .pos2 {
            top: 25%;
            left: 90%;
            transform: translate(-50%, -50%);
        }

        .pos3 {
            top: 75%;
            left: 90%;
            transform: translate(-50%, -50%);
        }

        .pos4 {
            top: 100%;
            left: 50%;
            transform: translate(-50%, -100%);
        }

        .pos5 {
            top: 75%;
            left: 10%;
            transform: translate(-50%, -50%);
        }

        .pos6 {
            top: 25%;
            left: 10%;
            transform: translate(-50%, -50%);
        }

        /* OUTER POSITIONS */
        .o1 {
            top: 0%;
            left: 50%;
            transform: translate(-50%, 0);
        }

        .o2 {
            top: 10%;
            left: 75%;
            transform: translate(-50%, -50%);
        }

        .o3 {
            top: 25%;
            left: 90%;
            transform: translate(-50%, -50%);
        }

        .o4 {
            top: 40%;
            left: 98%;
            transform: translate(-50%, -50%);
        }

        .o5 {
            top: 60%;
            left: 98%;
            transform: translate(-50%, -50%);
        }

        .o6 {
            top: 75%;
            left: 90%;
            transform: translate(-50%, -50%);
        }

        .o7 {
            top: 90%;
            left: 75%;
            transform: translate(-50%, -50%);
        }

        .o8 {
            top: 100%;
            left: 50%;
            transform: translate(-50%, -100%);
        }

        .o9 {
            top: 90%;
            left: 25%;
            transform: translate(-50%, -50%);
        }

        .o10 {
            top: 75%;
            left: 10%;
            transform: translate(-50%, -50%);
        }

        .o11 {
            top: 60%;
            left: 2%;
            transform: translate(-50%, -50%);
        }

        .o12 {
            top: 40%;
            left: 2%;
            transform: translate(-50%, -50%);
        }

        .o13 {
            top: 25%;
            left: 10%;
            transform: translate(-50%, -50%);
        }

        .o14 {
            top: 10%;
            left: 25%;
            transform: translate(-50%, -50%);
        }

        .o15 {
            top: 5%;
            left: 35%;
            transform: translate(-50%, -50%);
        }

        .o16 {
            top: 5%;
            left: 65%;
            transform: translate(-50%, -50%);
        }

        /* ROTATIONS */
        .animate-spin-slow {
            animation: spin 12s linear infinite;
        }

        .animate-spin-reverse {
            animation: spinReverse 25s linear infinite;
        }

        /* RADAR */
        .radar-ring {
            position: absolute;
            border: 1px solid rgba(0, 255, 255, 0.2);
            border-radius: 50%;
            animation: pulse 4s infinite;
        }

        .ring1 {
            width: 200px;
            height: 200px;
        }

        .ring2 {
            width: 300px;
            height: 300px;
            animation-delay: 1s;
        }

        .ring3 {
            width: 400px;
            height: 400px;
            animation-delay: 2s;
        }

        /* GOLD CENTER */
        .golden-glow {
            border-radius: 50%;
            padding: 10px;
            border: 2px solid gold;
            box-shadow: 0 0 15px gold, 0 0 40px goldenrod;
            animation: goldPulse 2s infinite alternate;
        }

        /* LOGIN GLOW */
        .glow-card {
            animation: glowSync 12s linear infinite;
        }

        /* ANIMATIONS */
        @keyframes spin {
            from {
                transform: rotate(0)
            }

            to {
                transform: rotate(360deg)
            }
        }

        @keyframes spinReverse {
            from {
                transform: rotate(360deg)
            }

            to {
                transform: rotate(0)
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(0.8);
                opacity: 0.2;
            }

            100% {
                transform: scale(1.2);
                opacity: 0;
            }
        }

        @keyframes goldPulse {
            from {
                box-shadow: 0 0 10px gold;
            }

            to {
                box-shadow: 0 0 40px gold, 0 0 80px orange;
            }
        }

        @keyframes glowSync {
            0% {
                box-shadow: 0 0 10px cyan;
            }

            50% {
                box-shadow: 0 0 40px cyan;
            }

            100% {
                box-shadow: 0 0 10px cyan;
            }
        }
    </style>

@endsection
