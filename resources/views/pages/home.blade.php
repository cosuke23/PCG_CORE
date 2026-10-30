@extends('layouts.app')

@section('title', 'MEPCOM - HRMIS')

@section('content')

    {{-- <section class="relative w-full h-screen flex flex-col items-center justify-center text-center overflow-hidden"
        style="background-image: linear-gradient(rgba(30, 58, 138, 0.3), rgba(30, 58, 138, 0.3)), url('{{ asset('backgrounds/bg1.png') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;"> --}}
    <section class="relative w-full h-screen flex flex-col items-center justify-center text-center overflow-hidden">

        <!-- VIDEO BACKGROUND -->
        <video autoplay muted loop playsinline class="absolute top-0 left-0 w-full h-full object-cover z-0">
            <source src="{{ asset('backgrounds/0406.mp4') }}" type="video/mp4">
            Your browser does not support the video tag.
        </video>

        <!-- OVERLAY (optional, for darkening the video) -->
        <div class="absolute top-0 left-0 w-full h-full bg-blue-900/30 z-10"></div>

        <div class="relative z-10 px-6 max-w-4xl flex flex-col items-center text-center">

            <!-- LOGO with glow -->
            <div class="relative mb-6">
                <div class="absolute inset-0 rounded-full blur-2xl bg-blue-500/40 animate-pulse"></div>
                <div class="p-1 bg-gradient-to-tr from-yellow-400 to-yellow-600 rounded-full shadow-2xl relative">
                    <img src="{{ asset('backgrounds/mepcom_logo.png') }}" alt="MEPCOM Logo"
                        class="w-32 h-32 md:w-40 md:h-40 rounded-full border-4 border-white object-contain bg-white">
                </div>
            </div>

            <!-- TITLE -->
            <h1
                class="text-[clamp(18px,3.5vw,64px)] font-extrabold whitespace-nowrap mb-2
        text-transparent bg-clip-text bg-gradient-to-r from-blue-300 via-cyan-300 to-blue-700
        animate-gradient drop-shadow-lg">
                Marine Environmental Protection Command
            </h1>

            <!-- SUBTITLE -->
            <h2 class="text-lg md:text-2xl font-semibold text-blue-100 mb-8 leading-tight">
                Human Resource Management Information System<br>
                <span class="text-sm opacity-80">(HRMIS)</span>
            </h2>

            <!-- BUTTON -->
            <div class="flex justify-center gap-4 flex-wrap">
                <a href="{{ route('login') }}"
                    class="relative px-10 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full font-bold shadow-xl transition transform hover:scale-105 overflow-hidden">
                    <span class="absolute inset-0 bg-blue-400/30 blur-xl opacity-50"></span>
                    <span class="relative z-10">Access System</span>
                </a>
            </div>

        </div>

        <!-- ANIMATION -->
        <style>
            @keyframes gradientMove {
                0% {
                    background-position: 0%
                }

                100% {
                    background-position: 200%
                }
            }

            .animate-gradient {
                background-size: 200% auto;
                animation: gradientMove 4s linear infinite;
            }
        </style>

        <div class="absolute bottom-8 w-full flex justify-center animate-bounce">
            <a href="#services" class="text-[#1e3a8a] text-3xl opacity-70">&#x25BC;</a>
        </div>
    </section>
    <!-- IMAGE SECTION ABOVE Programs & Services -->
    <section class="relative py-10 bg-[#f8fafc]">
        <div class="w-full mx-auto px-6 text-center">
            <h2 class="text-4xl font-black text-[#1e3a8a] mb-4">Commander's Corner</h2>
            <div class="max-w-7xl mx-auto overflow-hidden border border-gray-300 shadow-lg">
                <img src="{{ asset('backgrounds/MEP 5.jpg') }}" alt="RADM SUPANGAN PCG Message"
                    class="w-full h-auto object-contain cursor-pointer hover:opacity-90 transition"
                    onclick="openImageModal(this.src)">
            </div>

        </div>

        <br>

        <section class="py-12 bg-white" x-data="modalData()">
            <div class="mx-auto px-4 md:px-6 text-center max-w-[1320px]">

                <!-- TITLE -->
                <h2 class="text-2xl md:text-4xl font-extrabold text-white mb-10 tracking-wide">
                    Operational & Administrative Support Units
                </h2>

                <!-- MAIN CONTAINER -->
                <div class="relative overflow-hidden rounded-[35px] py-16 px-4 md:px-10 bg-cover bg-center shadow-2xl"
                    style="background-image: url('{{ asset('backgrounds/RED CARPER.jpg') }}'); min-height: 420px;">

                    <!-- DARK OVERLAY -->
                    <div class="absolute inset-0 bg-black/30"></div>

                    <div class="relative z-10 max-w-7xl mx-auto">

                        <!-- CENTER FEATURED LOGO -->
                        <div class="flex justify-center mb-14">

                            <a href="#" @click.prevent="openModal('mepcom')"
                                class="transition-all duration-500 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/MEPCOM.png') }}"
                                    class="w-32 h-32 md:w-44 md:h-44 object-contain drop-shadow-[0_10px_20px_rgba(0,0,0,0.7)]">

                            </a>
                        </div>

                        <!-- OTHER LOGOS -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-y-10 gap-x-6 place-items-center">

                            <!-- MEPLEF -->
                            <a href="#" @click.prevent="openModal('meplef')"
                                class="transition-all duration-300 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/MEPLEF_new.png') }}"
                                    class="w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-2xl">

                            </a>

                            <!-- NOCOP -->
                            <a href="#" @click.prevent="openModal('nocop')"
                                class="transition-all duration-300 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/NOCOP_new.png') }}"
                                    class="w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-2xl">

                            </a>

                            <!-- MFSIF -->
                            <a href="#" @click.prevent="openModal('msfis')"
                                class="transition-all duration-300 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/MFSIF.png') }}"
                                    class="w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-2xl">

                            </a>

                            <!-- MEPAcES -->
                            <a href="#" @click.prevent="openModal('mepacces')"
                                class="transition-all duration-300 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/MEPAcES.png') }}"
                                    class="w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-2xl">

                            </a>

                            <!-- MEPRCGS -->
                            <a href="#" @click.prevent="openModal('MEPRCGS')"
                                class="transition-all duration-300 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/MEPRCGS.png') }}"
                                    class="w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-2xl">

                            </a>

                            <!-- MEPTDI -->
                            <a href="{{ route('meptdi') }}"
                                class="transition-all duration-300 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/MEPTDI.png') }}"
                                    class="w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-2xl">

                            </a>

                            <!-- HSS -->
                            <a href="#" @click.prevent="openModal('HSS')"
                                class="transition-all duration-300 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/HSS.png') }}"
                                    class="w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-2xl">

                            </a>

                            <!-- MEPPU -->
                            <a href="#" @click.prevent="openModal('MEPPU')"
                                class="transition-all duration-300 hover:scale-110 hover:-translate-y-2">

                                <img src="{{ asset('backgrounds/MEPPU.png') }}"
                                    class="w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-2xl">

                            </a>

                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL -->
            <div x-show="show" x-transition class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">

                <div @click.away="show=false"
                    class="bg-white max-w-3xl w-full rounded-xl p-6 shadow-xl overflow-y-auto max-h-[80vh]">

                    <h3 class="text-xl font-bold text-[#1e3a8a] mb-3" x-text="title"></h3>

                    <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-line" x-text="content"></p>

                    <div class="text-right mt-6">
                        <button @click="show=false" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </section>
        {{-- <div class="max-w-7xl mx-auto px-6 text-center">
            <h2 class="text-4xl font-black text-[#1e3a8a] mb-4">Our Programs & Services</h2>
            <div class="w-24 h-1 bg-yellow-500 mx-auto mb-12"></div>

            <div class="grid md:grid-cols-3 gap-8 text-left">
                <div
                    class="bg-white border-b-4 border-blue-600 rounded-xl shadow-md p-8 hover:-translate-y-2 transition duration-300">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Maritime Enforcement</h3>
                    <p class="text-gray-600 text-sm">Patrolling and enforcing maritime laws to protect national waters and
                        sovereignty.</p>
                </div>

                <div
                    class="bg-white border-b-4 border-yellow-500 rounded-xl shadow-md p-8 hover:-translate-y-2 transition duration-300">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4 text-yellow-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9-9c1.657 0 3 4.03 3 9s-1.343 9-3 9m0-18c-1.657 0-3 4.03-3 9s1.343 9 3 9m-9-9h18">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Search & Rescue</h3>
                    <p class="text-gray-600 text-sm">Rapid-response search and rescue operations across the Philippine
                        archipelago.</p>
                </div>

                <div
                    class="bg-white border-b-4 border-blue-600 rounded-xl shadow-md p-8 hover:-translate-y-2 transition duration-300">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Environmental Protection</h3>
                    <p class="text-gray-600 text-sm">Monitoring and safeguarding marine ecosystems, oil spill response, and
                        coral protection.</p>
                </div>
            </div>
        </div> --}}
    </section>
    <section>
        <div class="flex justify-center">
            <div class="relative w-full max-w-7xl h-[700px]">

                <!-- Video -->
                <video id="heroVideo" autoplay loop muted class="absolute w-full h-full object-cover rounded-lg">
                    <source src="{{ asset('backgrounds/MEPCOM AVP FOR BEACON.mp4') }}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>

                <!-- Overlay Button -->
                <div id="videoOverlay"
                    class="absolute inset-0 bg-black/70 flex items-center justify-center cursor-pointer z-10 rounded-lg">
                    <span id="overlayIcon" class="text-white text-3xl bg-green-500 px-6 py-4 rounded-full">🔊</span>
                </div>

            </div>
        </div>
    </section>

    <script>
        const video = document.getElementById('heroVideo');
        const overlay = document.getElementById('videoOverlay');
        const icon = document.getElementById('overlayIcon');

        overlay.addEventListener('click', () => {
            // Unmute the video
            video.muted = false;

            // Optional: play the video if paused
            if (video.paused) video.play();

            // Hide the overlay
            overlay.style.display = 'none';
        });
    </script>
    <script>
        function modalData() {
            return {
                show: false,
                title: '',
                content: '',

                openModal(type) {
                    this.show = true;

                    if (type === 'mepacces') {
                        this.title = 'MEP Accreditation And Certification Service (MEPAcCes)';
                        this.content =
                            `This unit is responsible for receiving, reviewing, and processing applications for accreditation and certification in accordance with applicable regulations.

It evaluates the completeness, validity, and authenticity of submitted documents and coordinates with MEPCOM units for inspection, testing, and evaluation of vessel plans and pollution control equipment.

It prepares official communications for approval and issuance of certificates, maintains organized records, manages MARPOL 73/78 record books, and coordinates with government agencies and institutions.

Additionally, it provides technical assistance to stakeholders and fosters collaboration to enhance service quality and compliance with standards.`;
                    }

                    if (type === 'mepcom') {
                        this.title = 'Headquarters Support Service (MEPCOM-HSS)';
                        this.content =
                            `This unit provides comprehensive administrative and logistical services in support of the activities and operations of MEPCOM and its subordinate units.

It is responsible for the delivery of administrative assistance, facility and compound security, vehicle management, manpower augmentation, and property maintenance.

Furthermore, the unit oversees the maintenance, repair, and progressive upkeep of equipment, transport assets, and infrastructure of Headquarters MEPCOM, ensuring operational readiness and sustainability of resources.

It also supervises marshaling and security operations, implements camp and facility defense plans, and enforces security requirements across all MEPCOM-controlled compounds.

The renaming of the unit will highlight and align its designation with the full scope of its functions, responsibilities, and the critical support services it renders to the Command.`;
                    }

                    if (type === 'meplef') {
                        this.title = 'MEP Law Enforcement Force (MEPLEF)';
                        this.content =
                            `A specialized law enforcement unit focused on the enforcement of international conventions, national laws, memorandum circulars, and regulatory frameworks on marine environmental protection.

Its core functions shall cover deterrence as a peaceful presence and show of resolve, as well as monitoring, compliance, and enforcement.

This re-designation ensures clearer functional delineation, eliminates operational redundancy, and aligns the unit’s role with the Philippine Coast Guard’s statutory authority as a maritime law enforcement agency.`;
                    }

                    if (type === 'mepti') {
                        this.title = 'MEP Training And Doctrine Institute (MEPTDI)';
                        this.content =
                            `This unit is responsible for the education and training of MEPCOM personnel in marine environmental knowledge and skills for the protection, preservation, and conservation of the marine environment.

The task of the Institute is primarily oriented toward the education and indoctrination of personnel in marine environmental protection.

As an administrative support unit of MEPCOM, it encompasses the conduct of training and education, as well as the formulation, review, updating, and doctrinal study of policies pertaining to marine environmental protection.`;
                    }

                    if (type === 'msif') {
                        this.title = 'Marine Science Forensic and Investigation Service (MSFIS)';
                        this.content =
                            `The unit is composed of environmental technical experts, chemists, and laboratory technicians specializing in forensic analysis and scientific investigation of marine environmental pollution incidents.

Its core capabilities include the collection, testing, and analysis of water, sediment, and effluent samples from vessels, coastal facilities, and marine areas, as well as conducting underwater assessments of the marine environment, including coral reefs, to identify sources of pollution and environmental damage, ensuring compliance with government-set environmental standards.

As a specialized operational support unit of MEPCOM, it focuses on laboratory-based investigations and comprehensive marine and coral assessments, providing scientifically validated data and analyses to support monitoring, decision-making, and the preservation of the marine environment.

It emphasizes the unit’s scientific, evidence-based, and analytical approach in collecting and analyzing data, ensuring that results are reliable, traceable, and actionable for environmental monitoring and management.`;
                    }

                    if (type === 'nocop') {
                        this.title = 'National Operations Center for Oil Pollution (NOCOP)';
                        this.content =
                            `A unit of the Marine Environmental Protection Command (MEPCOM) stands as the central hub for orchestrating oil spill response initiatives across the Philippines.

Established in 1974 through Presidential Decree No. 602, also known as the National Oil Pollution Operations Center Decree, NOCOP embodies the nation's commitment to environmental protection and maritime safety.

NOCOP acts as a focal point for both local and international concerns on marine pollution (oil and HNS) and facilitates coordination with other government instrumentalities, as well as local and private entities, for any assistance deemed necessary for the mitigation, containment, and recovery of oil and/or HNS spills.

As the principal coordinating unit, it plays a pivotal role in mobilizing resources, deploying well-trained personnel, and leveraging state-of-the-art equipment to address and mitigate oil pollution incidents, safeguarding the marine ecosystem and coastal communities.`;
                    }
                }
            }
        }
    </script>

    <section id="about" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-2 gap-16 items-center">
            <div class="relative w-48 md:w-64 mx-auto">
                <!-- Border -->
                <div class="absolute inset-0 border-4 border-yellow-500 rounded-2xl z-0"></div>

                <!-- Image -->
                <img src="{{ asset('backgrounds/mepcom_logo.png') }}" alt="MEPCOM Logo"
                    class="relative z-10 rounded-2xl shadow-2xl w-full h-full object-contain grayscale hover:grayscale-0 transition duration-500">
            </div>
            <div>
                <h2 class="text-4xl font-black text-[#1e3a8a] mb-6">About MEPCOM</h2>
                <p class="text-gray-700 leading-relaxed mb-6">
                    The Marine Environmental Protection Command is the specialized unit of the PCG responsible for
                    preventing, controlling, and mitigating marine pollution within the maritime jurisdiction of the
                    Philippines.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-bold text-blue-700">DUTY</h4>
                        <p class="text-xs text-gray-500">Service above self.</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-bold text-blue-700">HONOR</h4>
                        <p class="text-xs text-gray-500">Integrity in action.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
