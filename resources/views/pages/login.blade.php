@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- SweetAlert Logic --}}
    @if (session('registered') || session('success') || $errors->any())
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: "{{ $errors->any() ? 'error' : 'success' }}",
                    title: "{{ $errors->any() ? 'Login Failed' : 'Success' }}",
                    text: "{{ $errors->first() ?? (session('registered') ?? session('success')) }}",
                    confirmButtonColor: "{{ $errors->any() ? '#dc2626' : '#2563eb' }}"
                });
            });
        </script>
    @endif

    <div id="page-content"
        class="relative min-h-screen w-full flex items-center justify-center px-6 md:px-20 overflow-hidden">

        <!-- VIDEO BACKGROUND -->
        <video autoplay muted loop playsinline class="absolute top-0 left-0 w-full h-full object-cover z-0">
            <source src="{{ asset('backgrounds/web bg loging vid.mp4') }}" type="video/mp4">
            Your browser does not support the video tag.
        </video>

        <!-- DARK OVERLAY -->
        <div class="absolute inset-0 bg-black/50 z-10"></div>

        <div class="relative z-20 w-full max-w-7xl flex flex-col md:flex-row items-center justify-between gap-12">

            <!-- LEFT TEXT CONTENT -->
            <div class="text-white max-w-2xl space-y-4">
                <h1 class="text-5xl md:text-7xl font-black leading-tight tracking-tighter drop-shadow-2xl">
                    Securing Marine <br>
                    Life Through <br>
                    <span class="text-mepcomGold">Action and Integrity</span>
                </h1>
                <p class="text-lg md:text-xl text-gray-200 max-w-md font-medium leading-relaxed">
                    Welcome to the MEPCOM Human Resource Management Integrated System (HRMIS).
                    Your gateway to efficient and professional service.
                </p>
                <div class="pt-4">
                    <div class="h-1 w-20 bg-mepcomGold rounded-full"></div>
                </div>
            </div>
            <!-- LOGIN CARD -->
            <div class="w-full max-w-md mt-8">

                <div
                    class="bg-white/20 backdrop-blur-lg rounded-[2.5rem] shadow-2xl p-10 transform transition-all hover:scale-[1.01] border border-white/30">

                    <!-- CARD HEADER -->
                    <div class="text-center mb-8">
                        <h2 class="text-4xl font-black text-blue-900 tracking-tight">Login</h2>
                        <p class="text-white text-sm mt-2 font-semibold uppercase tracking-widest">Portal Access</p>
                    </div>

                    <!-- LOGIN FORM -->
                    <form action="{{ route('login.store') }}" method="POST" class="space-y-4">
                        @csrf

                        <!-- Email -->
                        <div>
                            <input type="email" name="email" required
                                class="w-full px-6 py-4 rounded-xl bg-white/30 border border-white/20 focus:border-blue-600 focus:bg-white/50 focus:ring-0 text-gray-900 placeholder-gray-200 transition"
                                placeholder="Enter your valid email">
                        </div>

                        <!-- Password -->
                        <div class="relative">
                            <input type="password" name="password" id="password" required
                                class="w-full px-6 py-4 rounded-xl bg-white/30 border border-white/20 focus:border-blue-600 focus:bg-white/50 focus:ring-0 text-gray-900 placeholder-gray-200 transition"
                                placeholder="Enter your password">

                            <!-- Toggle password visibility -->
                            <button type="button" id="togglePassword"
                                class="absolute right-4 top-4 text-gray-200 hover:text-blue-900 transition">
                                <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>

                        <div class="text-right">
                            <a href="#" class="text-xs font-bold text-blue-400 hover:text-blue-600 transition">Forgot
                                Password?</a>
                        </div>

                        <!-- Submit button -->
                        <div class="pt-2">
                            <button type="submit"
                                class="w-full bg-blue-900/80 hover:bg-blue-900 text-white transition duration-300 font-black py-4 rounded-xl shadow-xl uppercase tracking-widest text-sm">
                                Login
                            </button>
                        </div>
                    </form>

                    <!-- REGISTER -->
                    <div class="mt-8 text-center border-t border-white/20 pt-6">
                        <p class="text-white text-xs font-bold">Don't have an account?</p>
                        <button data-bs-toggle="modal" data-bs-target="#registerModal"
                            class="text-blue-400 font-black text-sm hover:underline mt-1 transition">REGISTER NOW</button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- PASSWORD TOGGLE --}}
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', () => {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            eyeIcon.style.opacity = type === 'password' ? '1' : '0.5';
        });
    </script>

    {{-- BLUR BACKGROUND FOR REGISTER MODAL --}}
    <script>
        const registerModal = document.getElementById('registerModal');
        const pageContent = document.getElementById('page-content');

        registerModal.addEventListener('show.bs.modal', function() {
            pageContent.classList.add('blur-background');
        });

        registerModal.addEventListener('hidden.bs.modal', function() {
            pageContent.classList.remove('blur-background');
        });
    </script>

    <style>
        /* Blur effect for background */
        .blur-background {
            filter: blur(15px) brightness(0.6);
            transition: filter 0.3s ease;
        }
    </style>

    {{-- REGISTER MODAL --}}
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

@endsection
