@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <style>
        #registerForm input:focus, #registerForm select:focus { border-color: #3b82f6 !important; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3); outline: none; }
        #regDraftNote[hidden] { display: none !important; }
        .reg-pw-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
        .reg-pw-field { position: relative; margin-top: 0.25rem; }
        .reg-pw-field input { width: 100%; padding: 0.5rem 3rem 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; font-size: 1rem; }
        .reg-eye { position: absolute; right: 0.25rem; top: 50%; transform: translateY(-50%); width: 2.5rem; height: 2.5rem; display: grid; place-items: center; border: 0; background: transparent; color: #4b5563; cursor: pointer; border-radius: 0.4rem; }
        .reg-eye:focus-visible { outline: 2px solid #3b82f6; }
        .reg-eye .reg-eye-shut { display: none; }
        .reg-eye[aria-pressed="true"] .reg-eye-open { display: none; }
        .reg-eye[aria-pressed="true"] .reg-eye-shut { display: block; }
        .reg-meter { height: 6px; background: #e5e7eb; border-radius: 3px; margin-top: 0.5rem; overflow: hidden; }
        .reg-meter span { display: block; height: 100%; width: 0; background: #dc2626; transition: width .2s, background-color .2s; }
        .reg-pw-label { font-size: 0.85rem; color: #4b5563; margin-top: 0.35rem; min-height: 1.2em; }
        .reg-pw-checks { list-style: none; padding: 0; margin: 0.35rem 0 0; font-size: 0.82rem; color: #6b7280; display: grid; gap: 0.15rem; }
        .reg-pw-checks li::before { content: "○ "; }
        .reg-pw-checks li.ok { color: #15803d; }
        .reg-pw-checks li.ok::before { content: "✓ "; }
    </style>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>

    @php $isOfficer = $type === 'officer'; @endphp
    @php
        // Open on the step that holds the first field the server rejected.
        $fieldStep = ['rank' => 0, 'serial_number' => 0, 'name' => 0, 'email' => 0, 'registration_type' => 0,
            'dob' => 1, 'date_of_appointment' => 1, 'ete' => 1,
            'command' => 2, 'category' => 2, 'unit' => 2, 'sub_unit' => 2,
            'password' => 3, 'selfie_image' => 3];
        $startStep = session('email_exists') ? 0 : 3;
        foreach (array_keys($errors->getMessages()) as $field) {
            $startStep = min($startStep, $fieldStep[$field] ?? 3);
        }
        if (!$errors->any() && !session('email_exists')) {
            $startStep = 0;
        }
    @endphp
    {{-- Intro video. Closes itself if the video file is missing (it is not in git). --}}
    <div x-data="{ open: true }" x-show="open" @keydown.escape.window="open = false"
        role="dialog" aria-modal="true" aria-label="MEPCOM introduction video"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50">

        <!-- Modal Container -->
        <div class="bg-white rounded-3xl shadow-2xl relative overflow-hidden inline-block z-[10000]">

            <!-- Close Button -->
            <button @click="open = false" aria-label="Close video"
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-2xl font-bold z-20">
                &times;
            </button>

            <!-- Video Content -->
            <video controls autoplay muted loop
                class="block max-w-full max-h-[90vh] object-contain rounded-3xl shadow-inner">
                <source src="{{ asset('backgrounds/mepcom 4.mp4') }}" type="video/mp4" x-on:error="open = false">
                Your browser does not support the video tag.
            </video>

        </div>
    </div>
    <div
        class="min-h-screen flex items-center justify-center p-6
@if ($isOfficer) bg-gradient-to-br from-slate-900 via-blue-900 to-black @else bg-gray-100 @endif">

        <!-- MAIN FORM CONTAINER -->
        <div x-data="{ ...stepForm(), ...unitDropdown() }" class="w-full max-w-5xl">

            <!-- HEADER -->
            <div class="text-center mb-6 text-black">
                <h1 class="text-3xl font-bold mt-4">Registration Portal</h1>
                <p class="text-gray-300 text-sm">
                    {{ $isOfficer ? 'Commissioned Officer' : 'Enlisted Personnel' }}
                </p>
            </div>

            <!-- CARD -->
            <div class="bg-white rounded-3xl shadow-2xl p-8">

                {{-- The server's answer when it sends the form back (it used to be silent). --}}
                @if ($errors->any() || session('email_exists'))
                    <div id="registerErrors" role="alert" tabindex="-1"
                        style="border: 1px solid #f5c2c0; background: #fdecea; color: #8a1c13; border-radius: 0.75rem; padding: 0.9rem 1.1rem; margin-bottom: 1.5rem;">
                        <p style="font-weight: 700; margin: 0 0 0.35rem;">Your registration was not sent. Please fix the following:</p>
                        <ul style="margin: 0; padding-left: 1.2rem; list-style: disc;">
                            @if (session('email_exists'))
                                <li>This email is already registered. Sign in instead, or use another email. If you registered before and are waiting, the administrator still has to approve your account.</li>
                            @endif
                            @php $stepNames = ['Step 1 (Basic)', 'Step 2 (Service)', 'Step 3 (Unit)', 'Step 4 (Verify)']; @endphp
                            @foreach ($errors->getMessages() as $field => $messages)
                                @foreach ($messages as $message)
                                    <li><strong>{{ isset($fieldStep[$field]) ? $stepNames[$fieldStep[$field]] . ': ' : '' }}</strong>{{ $message }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div id="regDraftNote" hidden role="status"
                    style="display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; align-items: center; justify-content: space-between; border: 1px solid #bfdbfe; background: #eff6ff; color: #1e3a8a; border-radius: 0.75rem; padding: 0.6rem 1rem; margin-bottom: 1.25rem; font-size: 0.9rem;">
                    <span>We kept what you typed on this device. Passwords and the selfie need to be entered again.</span>
                    <button type="button" id="regStartOver" style="font-weight: 600; text-decoration: underline; background: none; border: 0; color: inherit; cursor: pointer;">Start over</button>
                </div>

                <!-- STEPPER -->
                <div class="flex justify-between mb-8">
                    <template x-for="(label, index) in steps" :key="index">
                        <div class="flex-1 text-center cursor-pointer" @click="goTo(index)">
                            <div class="w-10 h-10 mx-auto rounded-full flex items-center justify-center font-bold transition"
                                :class="step >= index ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'">
                                <span x-text="index + 1"></span>
                            </div>
                            <p class="text-xs mt-2" x-text="label"></p>
                        </div>
                    </template>
                </div>

                <form id="registerForm" method="POST" enctype="multipart/form-data"
                    action="{{ route('register.submit') }}">
                    @csrf
                    <input type="hidden" name="registration_type" value="{{ request('type', old('registration_type')) }}">
                    <!-- STEP 1: BASIC INFO -->
                    <div x-show="step === 0" class="space-y-5" data-reg-step="0">
                        <h3 class="font-semibold text-lg">Basic Information</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <select name="rank"
                                style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;">
                                <option value="">Rank</option>
                                @if (!$isOfficer)
                                    <option value="FMCPO" @selected(old('rank') === 'FMCPO')>First Master Chief Petty Officer (FMCPO)</option>
                                    <option value="MCPO" @selected(old('rank') === 'MCPO')>Master Chief Petty Officer (MCPO)</option>
                                    <option value="SCPO" @selected(old('rank') === 'SCPO')>Senior Chief Petty Officer (SCPO)</option>
                                    <option value="CPO" @selected(old('rank') === 'CPO')>Chief Petty Officer (CPO)</option>
                                    <option value="PO1" @selected(old('rank') === 'PO1')>Petty Officer First Class (PO1)</option>
                                    <option value="PO2" @selected(old('rank') === 'PO2')>Petty Officer Second Class (PO2)</option>
                                    <option value="PO3" @selected(old('rank') === 'PO3')>Petty Officer Third Class (PO3)</option>
                                    <option value="SN1" @selected(old('rank') === 'SN1')>Seaman/Seawoman First Class (SN1 / SW1)</option>
                                    <option value="SN2" @selected(old('rank') === 'SN2')>Seaman/Seawoman Second Class (SN2 / SW2)</option>
                                    <option value="ASN" @selected(old('rank') === 'ASN')>Apprentice Seaman/Seawoman (ASN / ASW)</option>
                                @else
                                    <option value="ENS" @selected(old('rank') === 'ENS')>Ensign (ENS)</option>
                                    <option value="LTJG" @selected(old('rank') === 'LTJG')>Lieutenant Junior Grade (LTJG)</option>
                                    <option value="LT" @selected(old('rank') === 'LT')>Lieutenant (LT)</option>
                                    <option value="LCDR" @selected(old('rank') === 'LCDR')>Lieutenant Commander (LCDR)</option>
                                    <option value="CDR" @selected(old('rank') === 'CDR')>Commander (CDR)</option>
                                    <option value="CAPT" @selected(old('rank') === 'CAPT')>Captain (CAPT)</option>
                                    <option value="COMMO" @selected(old('rank') === 'COMMO')>Commodore (COMMO)</option>
                                    <option value="RADM" @selected(old('rank') === 'RADM')>Rear Admiral (RADM)</option>
                                    <option value="VADM" @selected(old('rank') === 'VADM')>Vice Admiral (VADM)</option>
                                    <option value="ADM" @selected(old('rank') === 'ADM')>Admiral (ADM)</option>
                                @endif
                            </select>

                            <input type="text" name="serial_number" placeholder="Serial Number" value="{{ old('serial_number') }}" maxlength="100"
                                style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;">
                        </div>

                        <input type="text" name="name" placeholder="Full Name" value="{{ old('name') }}" maxlength="255" autocomplete="name"
                            style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;">
                        <input type="email" name="email" placeholder="Email" value="{{ old('email') }}" autocomplete="email"
                            style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;">
                    </div>

                    <!-- STEP 2: SERVICE DETAILS -->
                    <div x-show="step === 1" class="space-y-5" data-reg-step="1">
                        <h3 class="font-semibold text-lg">Service Details</h3>

                        <div class="grid grid-cols-3 gap-4">

                            <!-- Date of Birth -->
                            <div class="flex flex-col">
                                <label for="dob" class="text-sm font-medium mb-1">Date of Birth</label>
                                <input type="date" id="dob" name="dob" value="{{ old('dob') }}"
                                    style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;">
                            </div>

                            <!-- Date of Appointment -->
                            <div class="flex flex-col">
                                <label for="date_of_appointment" class="text-sm font-medium mb-1">Date of
                                    Appointment</label>
                                <input type="date" id="date_of_appointment" name="date_of_appointment" value="{{ old('date_of_appointment') }}"
                                    style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;">
                            </div>

                            <!-- ETE -->
                            <div class="flex flex-col">
                                <label for="ete" class="text-sm font-medium mb-1">ETE</label>
                                <input type="date" id="ete" name="ete" value="{{ old('ete') }}" readonly
                                    class="input-modern bg-gray-100">
                            </div>

                        </div>

                        <p class="text-sm text-gray-500">Age: <span id="age"></span></p>
                    </div>

                    <!-- STEP 3: UNIT ASSIGNMENT -->
                    <div x-data="unitDropdown()" x-show="step === 2" class="space-y-5" data-reg-step="2">

                        <h3 class="font-semibold text-lg">Unit Assignment</h3>

                        <div class="grid grid-cols-4 gap-3">

                            <select name="command" x-model="selectedCommand" @change="updateCategories()"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                                <option value="">Command</option>
                                <template x-for="cmd in Object.keys(units)" :key="cmd">
                                    <option :value="cmd" x-text="cmd"></option>
                                </template>
                            </select>

                            <select name="category" x-model="selectedCategory" @change="updateUnits()"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                                <option value="">Category</option>
                                <template x-for="cat in categories" :key="cat">
                                    <option :value="cat" x-text="cat"></option>
                                </template>
                            </select>

                            <select name="unit" x-model="selectedUnit" @change="updateSubUnits()"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                                <option value="">Unit</option>
                                <template x-for="unit in unitsList" :key="unit">
                                    <option :value="unit" x-text="unit"></option>
                                </template>
                            </select>

                            <select name="sub_unit" x-model="selectedSubUnit"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                                <option value="">Sub Unit</option>
                                <template x-for="sub in subUnits" :key="sub">
                                    <option :value="sub" x-text="sub"></option>
                                </template>
                            </select>

                        </div>

                    </div>
                    <!-- STEP 4: SECURITY -->
                    <div x-show="step === 3" class="space-y-5" data-reg-step="3">
                        <h3 class="font-semibold text-lg">Security & Verification</h3>

                        <div class="reg-pw-grid">
                            <div>
                                <label for="regPassword" class="text-sm font-medium">Password</label>
                                <div class="reg-pw-field">
                                    <input type="password" id="regPassword" name="password" placeholder="At least 8 characters" autocomplete="new-password" aria-describedby="regPwHelp">
                                    <button type="button" class="reg-eye" data-target="regPassword" aria-label="Show password" aria-pressed="false"><svg class="reg-eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="reg-eye-shut" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c7 0 11 7 11 7a18.6 18.6 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
                                </div>
                                <div class="reg-meter" aria-hidden="true"><span id="regPwBar"></span></div>
                                <p id="regPwHelp" class="reg-pw-label" aria-live="polite">Use 12 or more characters with upper and lower case letters, a number and a symbol.</p>
                                <ul class="reg-pw-checks" id="regPwChecks">
                                    <li data-check="len">At least 8 characters</li>
                                    <li data-check="case">Upper and lower case letters</li>
                                    <li data-check="num">A number</li>
                                    <li data-check="sym">A symbol (for example ! @ # ?)</li>
                                </ul>
                            </div>
                            <div>
                                <label for="regPasswordConfirm" class="text-sm font-medium">Confirm password</label>
                                <div class="reg-pw-field">
                                    <input type="password" id="regPasswordConfirm" name="password_confirmation" placeholder="Type it again" autocomplete="new-password">
                                    <button type="button" class="reg-eye" data-target="regPasswordConfirm" aria-label="Show password" aria-pressed="false"><svg class="reg-eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="reg-eye-shut" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-7-11-7a18.5 18.5 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c7 0 11 7 11 7a18.6 18.6 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
                                </div>
                                <p id="regPwMatch" class="reg-pw-label" aria-live="polite"></p>
                            </div>
                        </div>

                        <!-- CAMERA -->
                        <div class="border rounded-xl p-4 bg-gray-50">
                            <video id="video" autoplay playsinline class="w-full rounded-lg mb-2"></video>
                            <canvas id="canvas" class="hidden"></canvas>

                            <button type="button" id="captureBtn"
                                class="w-full py-2 rounded-lg bg-green-600 text-white">
                                Capture Selfie
                            </button>

                            <p id="cameraStatus" class="text-xs text-gray-500 mt-1"></p>

                            <img id="selfiePreview" class="w-24 h-24 rounded-full mt-3 hidden">
                            <input type="hidden" name="selfie_image" id="selfieImage">
                        </div>
                    </div>

                    <!-- NAV -->
                    <div class="flex justify-between mt-8">
                        <button type="button" @click="prev()" x-show="step > 0"
                            class="px-6 py-2 rounded-lg bg-gray-200">Back</button>

                        <button type="button" @click="next()" x-show="step < 3"
                            class="px-6 py-2 rounded-lg bg-blue-600 text-white">Next</button>

                        <button type="submit" x-show="step === 3"
                            class="px-6 py-2 rounded-lg bg-green-600 text-white">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <!-- STEP FORM JS -->
    <script nonce="{{ $cspNonce ?? '' }}">
        // Draft: what the person typed is kept in this browser tab (sessionStorage),
        // so a refresh does not lose it. Passwords and the selfie are never kept.
        // The tab's copy is removed once the registration is accepted (sign-in page),
        // with "Start over", after 12 hours, or when the tab closes.
        const REG_DRAFT_KEY = 'pcgRegisterDraft:{{ $isOfficer ? 'officer' : 'non_officer' }}';
        const REG_DRAFT_FIELDS = ['rank', 'serial_number', 'name', 'email', 'dob', 'date_of_appointment', 'ete',
            'command', 'category', 'unit', 'sub_unit'];

        function regSaveDraft() {
            const form = document.getElementById('registerForm');
            if (!form) return;
            const data = { saved: Date.now() };
            REG_DRAFT_FIELDS.forEach(name => { const el = form.elements[name]; if (el && el.value) data[name] = el.value; });
            const root = document.querySelector('[x-data*="stepForm"]');
            if (root && window.Alpine) data.step = Alpine.$data(root).step;
            try { sessionStorage.setItem(REG_DRAFT_KEY, JSON.stringify(data)); } catch (e) { /* storage off: nothing kept */ }
        }

        function regClearDraft() {
            try { sessionStorage.removeItem(REG_DRAFT_KEY); } catch (e) {}
        }

        function regRestoreDraft() {
            let data = null;
            try { data = JSON.parse(sessionStorage.getItem(REG_DRAFT_KEY) || 'null'); } catch (e) {}
            if (!data || Date.now() - (data.saved || 0) > 12 * 3600 * 1000) { regClearDraft(); return; }
            const form = document.getElementById('registerForm');
            const serverAnswered = !!document.getElementById('registerErrors');
            ['rank', 'serial_number', 'name', 'email', 'dob', 'date_of_appointment', 'ete'].forEach(name => {
                const el = form.elements[name];
                if (el && !el.value && data[name]) el.value = data[name]; // what the server sent back wins
            });
            const dob = document.getElementById('dob');
            if (dob.value) dob.dispatchEvent(new Event('change'));
            const unitBox = document.querySelector('[data-reg-step="2"]');
            if (unitBox && data.command) Alpine.$data(unitBox).restoreUnits(data.command, data.category, data.unit, data.sub_unit);
            const root = document.querySelector('[x-data*="stepForm"]');
            if (root && !serverAnswered && Number.isInteger(data.step)) Alpine.$data(root).step = Math.min(data.step, 3);
            // no intro video again for someone who is half-way through
            const intro = document.querySelector('[aria-label="MEPCOM introduction video"]');
            if (intro) { Alpine.$data(intro).open = false; intro.querySelector('video')?.pause(); }
            const note = document.getElementById('regDraftNote');
            if (note) note.hidden = false;
        }

        document.addEventListener('alpine:initialized', () => {
            regRestoreDraft();
            const form = document.getElementById('registerForm');
            form.addEventListener('input', regSaveDraft);
            form.addEventListener('change', regSaveDraft);
            const restart = document.getElementById('regStartOver');
            if (restart) restart.addEventListener('click', () => { regClearDraft(); window.location.reload(); });
        });

        // Fields each step must have before the person can move on (the server checks again).
        const REG_REQUIRED = {
            0: { rank: 'Rank', serial_number: 'Serial number', name: 'Full name', email: 'Email' },
            1: { dob: 'Date of birth', date_of_appointment: 'Date of appointment' },
            2: { command: 'Command' },
            3: {}
        };

        // List of problems on one step (empty when the step is complete).
        function regProblems(step) {
            const form = document.getElementById('registerForm');
            const problems = [];
            Object.entries(REG_REQUIRED[step] || {}).forEach(([name, label]) => {
                const el = form.elements[name];
                if (!el || !String(el.value || '').trim()) problems.push(label + ' is required.');
            });
            if (step === 0) {
                const email = form.elements['email'];
                if (email && email.value && !email.checkValidity()) problems.push('Enter a valid email address.');
            }
            if (step === 3) {
                const pw = form.elements['password'].value;
                if (pw.length < 8) problems.push('Password must be at least 8 characters.');
                if (pw !== form.elements['password_confirmation'].value) problems.push('The two passwords do not match.');
                if (!document.getElementById('selfieImage').value) problems.push('Capture your selfie with the camera first.');
            }
            return problems;
        }

        function regShowProblems(problems) {
            const list = document.createElement('ul');
            list.style.textAlign = 'left';
            problems.forEach(p => { const li = document.createElement('li'); li.textContent = p; list.appendChild(li); });
            Swal.fire({ icon: 'warning', title: 'Please complete this step', html: list });
        }

        function stepForm() {
            return {
                step: {{ (int) ($startStep ?? 0) }},
                steps: ['Basic', 'Service', 'Unit', 'Verify'],
                init() {
                    this.$watch('step', () => regSaveDraft());
                },
                next() {
                    const problems = regProblems(this.step);
                    if (problems.length) { regShowProblems(problems); return; }
                    if (this.step < 3) this.step++;
                },
                prev() {
                    if (this.step > 0) this.step--;
                },
                goTo(index) {
                    // moving back is always allowed; moving forward only past complete steps
                    for (let s = this.step; s < index; s++) {
                        const problems = regProblems(s);
                        if (problems.length) { this.step = s; regShowProblems(problems); return; }
                    }
                    this.step = index;
                }
            }
        }
    </script>
    <script nonce="{{ $cspNonce ?? '' }}">
        function unitDropdown() {
            return {
                selectedCommand: '',
                selectedCategory: '',
                selectedUnit: '',
                selectedSubUnit: '',

                // ✅ UPDATED BASED ON YOUR CSV FILE
                units: {
                    "Operational Support Units": {
                        "Marine Environmental Protection Law Enforcement Force (MEPLEF)": {
                            "HMEPLEF": [
                                "O/C, MEPLEF",
                                "O/DC, MEPLEF",
                                "O/CS, MEPLEF",
                                "LEF-1",
                                "LEF-2",
                                "LEF-3",
                                "LEF-4",
                                "LEF-5",
                                "LEF-6",
                                "LEF-11",
                                "LEF-12"
                            ],
                            "MEPLEF DIVISION": [
                                "MEP INSPECTION AND COMPLIANCE DIVISION",
                                "MEP ENFORCEMENT INFORMATION & COMMUNICATIONS TECHNOLOGY SYSTEM DIVISION",
                                "ADJUDICATION AND PROSECUTION ASSISTANCE DIVISION",
                                "COMMUNITY ENGAGEMENT OUTREACH DIVISION",
                                "HEADQUARTERS SUPPORT GROUP",
                                "MEPLEF PROCUREMENT OFFICE"
                            ],
                            "MEPLEU": [
                                "MEPLEU NCR-CL",
                                "MEPLEU NWLZN",
                                "MEPLEU NELZN",
                                "MEPLEU STL",
                                "MEPLEU BCL",
                                "MEPLEU PAL",
                                "MEPLEU KIG",
                                "MEPLEU CV",
                                "MEPLEU EV",
                                "MEPLEU WV",
                                "MEPLEU SV",
                                "MEPLEU NM",
                                "MEPLEU NEM",
                                "MEPLEU SWM",
                                "MEPLEU SM",
                                "MEPLEU BARMM"
                            ]
                        },

                        "National Operations Center for Oil Pollution (NOCOP)": {
                            "HNOCOP": [
                                "O/D, NOCOP",
                                "O/DD, NOCOP",
                                "O/CS, NOCOP",
                                "N-1, Adjutant",
                                "N-2",
                                "N-3",
                                "N-4",
                                "N-5",
                                "N-6",
                                "N-11",
                                "N-12"
                            ],

                            "Operational Support Unit": [
                                "Marine Pollution Response Risk Assessment Center",
                                "National Oil Pollution Response Center",
                                "Marine Pollutant Response Center"
                            ],

                            "Administrative Support Unit": [
                                "MARPOL Coordinating Center",
                                "NOCOP Communications Center",
                                "BICIO Headquarters Support Group",
                                "NOCOP Procurement Office"
                            ],

                            "OSRU": [
                                "OSRU NCR-CL",
                                "OSRU NWLZN",
                                "OSRU NELZN",
                                "OSRU STL",
                                "OSRU BCL",
                                "OSRU PAL",
                                "OSRU KIG",
                                "OSRU CV",
                                "OSRU EV",
                                "OSRU WV",
                                "OSRU SV",
                                "OSRU NM",
                                "OSRU NEM",
                                "OSRU SWM",
                                "OSRU SM",
                                "OSRU BARMM"
                            ]
                        },

                        "Marine Science Forensic Investigation Service (MSFIS)": {
                            "HMSFIS": [
                                "O/C, MSFIS",
                                "O/DC, MSFIS",
                                "O/CS, MSFIS",
                                "S-1",
                                "S-2",
                                "S-3",
                                "S-4",
                                "S-5",
                                "S-6",
                                "S-11",
                                "S-12"
                            ],

                            "Operational Support Unit": [
                                "Field Scientific Division",
                                "Marine Science Underwater Investigation Division",
                                "MSFIS Laboratory Center"
                            ],

                            "Administrative Support Unit": [
                                "Marine Science Custody, Receiving and Releasing Division",
                                "Forensic and Investigation Division",
                                "Research and Development Division",
                                "MSFI Service Headquarter Support Group",
                                "MSFIS Procurement Office"
                            ],

                            "MSFIU": [
                                "MSFIU NCR-CL",
                                "MSFIU NWLZN",
                                "MSFIU NELZN",
                                "MSFIU STL",
                                "MSFIU BCL",
                                "MSFIU PAL",
                                "MSFIU KIG",
                                "MSFIU CV",
                                "MSFIU EV",
                                "MSFIU WV",
                                "MSFIU SV",
                                "MSFIU NM",
                                "MSFIU NEM",
                                "MSFIU SWM",
                                "MSFIU SM",
                                "MSFIU BARMM"
                            ]
                        }
                    },

                    "Administrative Support Units": {
                        "Marine Environmental Protection Regulatory Compliance and Governance Service (MEPRCGS)": {
                            "MEPRCGS": [
                                "O/D, MEP RCGS",
                                "O/DD, MEP RCGS",
                                "Administrative and Resource Management Branch",
                                "Climate Change and Blue Economy Division",
                                "Regulatory Compliance Division",
                                "Governance & Management Division"
                            ]
                        },

                        "Marine Environmental Protection Training and Doctrine Institute (MEPTDI)": {
                            "MEPTDI": [
                                "O/S, MEPTDI",
                                "O/AS, MEPTDI",
                                "Administrative and Resource Management Division",
                                "Research and Doctrine Development Division",
                                "Special Training Division",
                                "Information and Communications Technology Division"
                            ]
                        }
                    },

                    "Operating Unit": {
                        "Marine Environmental Protection Group (MEPG)": {
                            "MEPGroup NCRCL": [
                                "HMEPG NCR CL",
                                "MEPUnit Manila",
                                "MEPUnit Cavite",
                                "MEPUnit Bataan",
                                "MEPUnit Zambales",
                                "MEPUnit Laguna",
                                "MEPUnit Rizal",
                                "MEPUnit Malacañang",
                                "MEPUnit Pampanga"
                            ],

                            "MEPGroup NELZN": [
                                "HMEPGroup NELZN",
                                "MEPUnit Cagayan",
                                "MEPUnit Calayan",
                                "MEPUnit Aurora",
                                "MEPUnit Batanes",
                                "MEPUnit Isabela"
                            ]

                            // Continue remaining MEPGroup NWLZN, STL, BCL,
                            // PAL, KIG, EV, WV, CV, SV, NM, NEM,
                            // SM, SEM, SWM, BARMM
                        }
                    }
                },

                categories: [],
                unitsList: [],
                subUnits: [],

                updateCategories() {
                    this.selectedCategory = '';
                    this.selectedUnit = '';
                    this.selectedSubUnit = '';
                    this.categories = Object.keys(this.units[this.selectedCommand] || {});
                    this.unitsList = [];
                    this.subUnits = [];
                },

                updateUnits() {
                    this.selectedUnit = '';
                    this.selectedSubUnit = '';
                    this.unitsList = Object.keys(this.units[this.selectedCommand]?.[this.selectedCategory] || {});
                    this.subUnits = [];
                },

                updateSubUnits() {
                    this.selectedSubUnit = '';
                    const data = this.units[this.selectedCommand]?.[this.selectedCategory]?.[this.selectedUnit];
                    this.subUnits = Array.isArray(data) ? data : [];
                },

                // Put back a saved choice level by level, waiting for each list to render.
                restoreUnits(cmd, cat, unit, sub) {
                    if (!cmd || !this.units[cmd]) return;
                    this.selectedCommand = cmd;
                    this.categories = Object.keys(this.units[cmd]);
                    this.$nextTick(() => {
                        if (!cat || !this.units[cmd][cat]) return;
                        this.selectedCategory = cat;
                        this.unitsList = Object.keys(this.units[cmd][cat]);
                        this.$nextTick(() => {
                            if (!unit || !this.units[cmd][cat][unit]) return;
                            this.selectedUnit = unit;
                            const data = this.units[cmd][cat][unit];
                            this.subUnits = Array.isArray(data) ? data : [];
                            this.$nextTick(() => { if (sub && this.subUnits.includes(sub)) this.selectedSubUnit = sub; });
                        });
                    });
                }
            }
        }
    </script>

    <!-- MAIN JS -->
    <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener("DOMContentLoaded", () => {

            const errorBox = document.getElementById('registerErrors');
            if (errorBox) { errorBox.scrollIntoView({ block: 'center' }); errorBox.focus({ preventScroll: true }); }

            // AGE
            const dob = document.getElementById('dob');
            const ageSpan = document.getElementById('age');
            dob.addEventListener('change', () => {
                const b = new Date(dob.value);
                const t = new Date();
                let age = t.getFullYear() - b.getFullYear();
                if (t < new Date(b.setFullYear(t.getFullYear()))) age--;
                ageSpan.textContent = age;
            });

            // ETE
            const doa = document.getElementById('date_of_appointment');
            const ete = document.getElementById('ete');
            doa.addEventListener('change', () => {
                const d = new Date(doa.value);
                d.setFullYear(d.getFullYear() + 3);
                ete.value = d.toISOString().split('T')[0];
            });

            // CAMERA
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const btn = document.getElementById('captureBtn');
            const selfie = document.getElementById('selfieImage');
            const preview = document.getElementById('selfiePreview');
            const status = document.getElementById('cameraStatus');

            navigator.mediaDevices.getUserMedia({
                    video: true
                })
                .then(stream => {
                    video.srcObject = stream;
                    status.textContent = "Camera ready";
                })
                .catch(err => status.textContent = "Camera error: " + err.message);

            btn.addEventListener('click', () => {
                if (!video.videoWidth) {
                    Swal.fire('Camera not ready', 'Allow camera access for this site, wait for the picture, then capture again.', 'warning');
                    return;
                }
                const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
                canvas.width = Math.round(video.videoWidth * scale);
                canvas.height = Math.round(video.videoHeight * scale);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                const data = canvas.toDataURL('image/jpeg', 0.85);
                selfie.value = data;
                preview.src = data;
                preview.classList.remove('hidden');
                Swal.fire('Captured!', 'Selfie saved.', 'success');
            });

            // PASSWORD: show/hide and strength
            document.querySelectorAll('.reg-eye').forEach(btn => {
                btn.addEventListener('click', () => {
                    const input = document.getElementById(btn.dataset.target);
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                });
            });
            const pw = document.getElementById('regPassword');
            const pw2 = document.getElementById('regPasswordConfirm');
            const bar = document.getElementById('regPwBar');
            const help = document.getElementById('regPwHelp');
            const match = document.getElementById('regPwMatch');
            const LEVELS = [
                ['Too short', '#dc2626', 10], ['Weak', '#dc2626', 25], ['Fair', '#f59e0b', 50],
                ['Good', '#2563eb', 75], ['Strong', '#15803d', 100]
            ];
            function rate() {
                const v = pw.value;
                const checks = {
                    len: v.length >= 8,
                    case: /[a-z]/.test(v) && /[A-Z]/.test(v),
                    num: /\d/.test(v),
                    sym: /[^A-Za-z0-9]/.test(v)
                };
                document.querySelectorAll('#regPwChecks li').forEach(li => li.classList.toggle('ok', checks[li.dataset.check]));
                let level = 0;
                if (v.length >= 8) {
                    level = 1 + [checks.case, checks.num, checks.sym].filter(Boolean).length;
                    if (v.length >= 12 && level >= 3) level = 4;
                    level = Math.min(level, v.length >= 12 ? 4 : 3);
                }
                if (/^(.)\1+$/.test(v) || /password|12345678|qwerty/i.test(v)) level = Math.min(level, 1);
                const [label, color, width] = LEVELS[level];
                bar.style.width = v ? width + '%' : '0';
                bar.style.backgroundColor = color;
                help.textContent = v ? 'Strength: ' + label + (level < 3 ? '. Add length, mixed case, numbers or symbols.' : '.') : 'Use 12 or more characters with upper and lower case letters, a number and a symbol.';
                rateMatch();
            }
            function rateMatch() {
                if (!pw2.value) { match.textContent = ''; return; }
                const same = pw2.value === pw.value;
                match.textContent = same ? '✓ Passwords match' : 'Passwords do not match yet';
                match.style.color = same ? '#15803d' : '#b91c1c';
            }
            pw.addEventListener('input', rate);
            pw2.addEventListener('input', rateMatch);

            // SUBMIT
            document.getElementById('registerForm').addEventListener('submit', (event) => {
                for (let step = 0; step <= 3; step++) {
                    const problems = regProblems(step);
                    if (problems.length) {
                        event.preventDefault();
                        const root = document.querySelector('[x-data*="stepForm"]');
                        if (root && window.Alpine && Alpine.$data) Alpine.$data(root).step = step;
                        regShowProblems(problems);
                        return;
                    }
                }
                Swal.fire({
                    title: 'Processing...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
            });

        });
    </script>
@endsection
