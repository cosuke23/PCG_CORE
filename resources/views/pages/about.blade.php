@extends('layouts.app')

@section('title', 'About Us')

@section('content')
<!-- Hero Section -->
<section class="relative w-full h-[60vh] bg-cover bg-center flex items-center justify-center"
         style="background-image: url('{{ asset('images/pcg_ship.jpg') }}');">
    <div class="absolute inset-0 bg-blue-900/70"></div>
    <div class="relative z-10 text-center text-white px-6">
        <h1 class="text-5xl font-extrabold mb-4">About the Philippine Coast Guard</h1>
        <p class="text-lg max-w-2xl mx-auto">
            Upholding safety, security, and maritime excellence — serving our nation with honor and integrity.
        </p>
    </div>
</section>
<!-- News and Magazine Section -->
<section class="bg-blue-900 py-16">
    <div class="max-w-6xl mx-auto px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold text-white mb-8">News & Magazine</h2>
        <p class="text-blue-200 mb-12">
            Stay updated with the latest news, features, and publications from the Philippine Coast Guard.
        </p>

        <a href="https://heyzine.com/flip-book/c35f81ef4f.html" target="_blank"
            class="block bg-white rounded-2xl shadow-lg p-10 hover:shadow-2xl transition transform hover:-translate-y-2">
            <h3 class="text-2xl font-semibold text-blue-900 mb-4">Marine Environmental Protection Command Magazine</h3>
            <p class="text-gray-700">
                Click here to read our latest magazine online. Explore news, features, and updates about our missions, events, and activities.
            </p>
        </a>
    </div>
</section>
<!-- Content Section -->
<section class="bg-gray-100 py-16">
    <div class="max-w-6xl mx-auto px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold text-blue-900 mb-8">Who We Are</h2>
        <p class="text-gray-700 text-lg leading-relaxed mb-12">
            The <strong>Philippine Coast Guard</strong> is a uniformed service tasked to ensure the safety of lives and property at sea,
            protect the marine environment, and enforce maritime laws in Philippine waters.
            We are committed to excellence, discipline, and service to our country and the Filipino people.
        </p>

        <!-- Mission Vision Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mt-10">
            <!-- Vision -->
            <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition transform hover:-translate-y-2">
                <h3 class="text-2xl font-semibold text-blue-800 mb-4">Our Vision</h3>
                <p class="text-gray-600">
                    A world-class Coast Guard dedicated to maritime safety, security, and environmental protection for a strong and resilient nation.
                </p>
            </div>

            <!-- Mission -->
            <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition transform hover:-translate-y-2">
                <h3 class="text-2xl font-semibold text-blue-800 mb-4">Our Mission</h3>
                <p class="text-gray-600">
                    To safeguard life and property at sea, enforce maritime laws, and protect the marine environment through professional and disciplined service.
                </p>
            </div>

            <!-- Team -->
            <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition transform hover:-translate-y-2">
                <h3 class="text-2xl font-semibold text-blue-800 mb-4">Our Team</h3>
                <p class="text-gray-600">
                    Composed of dedicated men and women driven by duty and passion,
                    united under the core values of Honor, Service, and Patriotism.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Contact CTA -->
<section class="relative bg-gradient-to-r from-blue-900 to-blue-700 py-16 text-center text-white">
    <h2 class="text-4xl font-bold mb-4">Get in Touch</h2>
    <p class="text-lg mb-8">Have questions or need assistance? Reach out to us anytime.</p>
    <a href="{{ route('contact') }}"
       class="inline-block bg-orange-500 hover:bg-orange-600 text-white font-semibold px-8 py-3 rounded-full shadow-lg transition transform hover:scale-105">
       Contact Us
    </a>
</section>

<!-- 👨‍💻 Developers Section -->
<section class="bg-gray-50 py-20" x-data="{ open: false, imageSrc: '' }" x-cloak>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="max-w-6xl mx-auto px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold text-blue-900 mb-10">Meet the Developers</h2>
        <p class="text-gray-600 text-lg mb-12 max-w-3xl mx-auto">
            This system was developed by a dedicated team of innovators committed to digital transformation and modernizing the processes of the Philippine Coast Guard.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-10">
            <!-- Developer 1 -->
            <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition transform hover:-translate-y-2">
                <img
                    src="{{ asset('images/dev-1.jpg') }}"
                    alt="Developer 1"
                    class="w-32 h-32 mx-auto rounded-full object-cover border-4 border-blue-700 mb-4 cursor-pointer"
                    @click="open = true; imageSrc = '{{ asset('images/dev-1.jpg') }}'">
                <h3 class="text-xl font-semibold text-blue-800">Julius B. Colminas</h3>
                <p class="text-gray-500 mb-3">Lead Developer</p>
                <p class="text-gray-600 text-sm">
                    Full-stack developer passionate about creating efficient and secure systems to improve Coast Guard operations.
                </p>
            </div>

            <!-- Developer 2 -->
            <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-2xl transition transform hover:-translate-y-2">
                <img
                    src="{{ asset('images/dev-2.jpg') }}"
                    alt="Developer 2"
                    class="w-32 h-32 mx-auto rounded-full object-cover border-4 border-blue-700 mb-4 cursor-pointer"
                    @click="open = true; imageSrc = '{{ asset('images/dev-2.jpg') }}'">
                <h3 class="text-xl font-semibold text-blue-800">Adnan N. Jumdail</h3>
                <p class="text-gray-500 mb-3">Frontend Developer</p>
                <p class="text-gray-600 text-sm">
                    Focused on database management and backend logic ensuring reliability and performance across systems.
                </p>
            </div>
        </div>
    </div>

    <!-- Fullscreen Modal -->
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        @click.self="open = false"
        class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50">
        <img :src="imageSrc" class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl transform transition-transform duration-300 ease-out scale-100 hover:scale-105">
        <button @click="open = false" class="absolute top-6 right-6 text-white text-4xl font-bold">&times;</button>
    </div>
</section>

<!-- Alpine.js CDN -->
<script src="{{ asset('vendor/alpinejs/alpine-3.14.9.min.js') }}" defer></script>
@endsection
