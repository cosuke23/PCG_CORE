<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MEPCOM - HRMIS</title>

    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'Nunito', sans-serif;
        }
        .hero-bg {
            /* Replace the URL below with your actual background image path */
            background: linear-gradient(rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.2)),
                        url('https://images.unsplash.com/photo-1501416353676-e970f9065983?q=80&w=2000');
            background-size: cover;
            background-position: center;
        }
        .glass-nav {
            background: rgba(10, 25, 60, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 215, 0, 0.3);
        }
    </style>
</head>
<body class="antialiased">
    <div class="relative min-h-screen hero-bg flex flex-col items-center">

        <header class="w-full max-w-7xl px-6 py-4 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center font-bold text-[10px] text-center">PCG LOGO</div>
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center font-bold text-[10px] text-center">DOTr LOGO</div>
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center font-bold text-[10px] text-center">BAGONG PH</div>
            </div>

            <nav class="glass-nav rounded-full px-2 py-1 flex items-center">
                <ul class="flex items-center text-white text-sm font-semibold">
                    <li><a href="#" class="bg-blue-600 px-6 py-2 rounded-full">Home</a></li>
                    <li><a href="#" class="px-4 py-2 hover:text-blue-400 transition">Works</a></li>
                    <li><a href="#" class="px-4 py-2 hover:text-blue-400 transition">Blogs</a></li>
                    <li><a href="#" class="px-4 py-2 hover:text-blue-400 transition">Timeline</a></li>
                    <li><a href="#" class="px-4 py-2 hover:text-blue-400 transition">Waitlist</a></li>
                    <li class="relative group">
                        <button class="px-4 py-2 flex items-center hover:text-blue-400">
                            All Pages
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </li>
                </ul>
            </nav>

            <div class="text-blue-900 font-extrabold text-xl tracking-tighter">
                WELCOME ABOARD
            </div>
        </header>

        <main class="flex-1 flex flex-col items-center justify-center text-center px-4 pb-20">
            <div class="mb-6">
                <div class="w-40 h-40 bg-white/90 rounded-full border-4 border-yellow-500 flex items-center justify-center shadow-2xl">
                    <span class="text-gray-400 font-bold">MEPCOM LOGO</span>
                </div>
            </div>

            <h1 class="text-4xl md:text-6xl font-extrabold text-blue-900 mb-2 drop-shadow-sm">
                Marine Environmental Protection Command
            </h1>
            <h2 class="text-xl md:text-2xl font-bold text-blue-800 mb-8">
                Human Resource Management Integrated System<br>
                <span class="text-lg">(HRMIS)</span>
            </h2>

            @if (Route::has('login'))
                <div class="space-y-4">
                    @auth
                        <a href="{{ url('/home') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-10 rounded-full shadow-lg transition transform hover:scale-105 inline-block">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-10 rounded-full shadow-lg transition transform hover:scale-105 inline-block">
                            Get Started
                        </a>

                        @if (Route::has('register'))
                            <p class="text-blue-900 text-sm">
                                Don't have an account? <a href="{{ route('register') }}" class="underline font-bold">Register here</a>
                            </p>
                        @endif
                    @endauth
                </div>
            @endif
        </main>

        <footer class="w-full p-6 flex justify-between items-end text-xs text-blue-900 font-bold opacity-75">
            <div>
                &copy; {{ date('Y') }} MEPCOM HRMIS
            </div>
            <div>
                Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
            </div>
        </footer>
    </div>
</body>
</html>
