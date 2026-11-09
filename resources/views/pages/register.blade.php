@extends('layouts.auth')

@php
    $isOfficer = $type === 'officer';
    // Alpine.js (the step wizard) needs 'unsafe-eval' on this page only.
    app()->instance('csp.allow_eval', true);

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
    $stepNames = ['Step 1 (Basic)', 'Step 2 (Service)', 'Step 3 (Unit)', 'Step 4 (Verify)'];
@endphp

@section('title', $isOfficer ? 'Register as a commissioned officer' : 'Register as a non-officer')
@section('eyebrow', 'MEPCOM HRMIS')
@section('headline', 'One account for your records, documents and training')
@section('intro', 'Your registration goes to the HRMIS administrator. You can sign in as soon as it is approved.')

@section('content')
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/register.css') }}">

    <div id="regWizard" class="reg"
        x-data="{ ...stepForm(), ...unitDropdown() }"
        data-start-step="{{ (int) $startStep }}"
        data-draft-key="pcgRegisterDraft:{{ $isOfficer ? 'officer' : 'non_officer' }}">

        <h2>{{ $isOfficer ? 'Register as a commissioned officer' : 'Register as a non-officer' }}</h2>
        <p class="pcg-auth__lead">
            Four steps. Have your serial number, date of appointment and a working camera ready.
            @if ($isOfficer)
                <a href="{{ route('register', ['type' => 'non_officer']) }}">Enlisted? Register as a non-officer.</a>
            @else
                <a href="{{ route('register', ['type' => 'officer']) }}">Commissioned officer? Register as an officer.</a>
            @endif
        </p>

        {{-- The server's answer when it sends the form back. --}}
        @if ($errors->any() || session('email_exists'))
            <div id="registerErrors" class="pcg-alert pcg-alert--danger" role="alert" tabindex="-1">
                <p class="reg-alert__title">Your registration was not sent. Please fix the following:</p>
                <ul>
                    @if (session('email_exists'))
                        <li>This email is already registered. Sign in instead, or use another email. If you registered before and are waiting, the administrator still has to approve your account.</li>
                    @endif
                    @foreach ($errors->getMessages() as $field => $messages)
                        @foreach ($messages as $message)
                            <li><strong>{{ isset($fieldStep[$field]) ? $stepNames[$fieldStep[$field]] . ': ' : '' }}</strong>{{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        <div id="regDraftNote" class="pcg-alert pcg-alert--info reg-draft" hidden role="status">
            <span>We kept what you typed on this device. Passwords and the selfie need to be entered again.</span>
            <button type="button" id="regStartOver" class="reg-linkbtn">Start over</button>
        </div>

        <!-- STEPPER -->
        <ol class="reg-steps" aria-label="Registration steps">
            <template x-for="(label, index) in steps" :key="index">
                <li class="reg-steps__item"
                    :class="{ 'is-current': step === index, 'is-done': step > index }">
                    <button type="button" class="reg-steps__btn" @click="goTo(index)"
                        :aria-current="step === index ? 'step' : null">
                        <span class="reg-steps__dot" aria-hidden="true">
                            <i class="fa-solid fa-check" x-show="step > index"></i>
                            <span x-show="step <= index" x-text="index + 1"></span>
                        </span>
                        <span class="reg-steps__name" x-text="label"></span>
                    </button>
                </li>
            </template>
        </ol>

        <form id="registerForm" class="reg-form" method="POST" enctype="multipart/form-data" novalidate
            action="{{ route('register.submit') }}">
            @csrf
            <input type="hidden" name="registration_type" value="{{ request('type', old('registration_type')) }}">

            <!-- STEP 1: BASIC INFO -->
            <section x-show="step === 0" x-cloak class="reg-step" data-reg-step="0" aria-labelledby="regStep0Title">
                <h3 id="regStep0Title" class="reg-step__title">Who you are</h3>

                <div class="reg-grid reg-grid--2">
                    <div class="pcg-field">
                        <label class="pcg-label" for="regRank">Rank</label>
                        <select class="pcg-input" id="regRank" name="rank" @error('rank') aria-invalid="true" @enderror>
                            <option value="">Choose your rank</option>
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
                        @error('rank')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pcg-field">
                        <label class="pcg-label" for="regSerial">Serial number</label>
                        <input class="pcg-input pcg-mono" type="text" id="regSerial" name="serial_number" value="{{ old('serial_number') }}" maxlength="100"
                            autocomplete="off" spellcheck="false" @error('serial_number') aria-invalid="true" @enderror>
                        @error('serial_number')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="pcg-field">
                    <label class="pcg-label" for="regName">Full name</label>
                    <input class="pcg-input" type="text" id="regName" name="name" value="{{ old('name') }}" maxlength="255" autocomplete="name"
                        @error('name') aria-invalid="true" @enderror>
                    @error('name')<p class="pcg-error">{{ $message }}</p>@enderror
                </div>

                <div class="pcg-field">
                    <label class="pcg-label" for="regEmail">Email address</label>
                    <input class="pcg-input" type="email" id="regEmail" name="email" value="{{ old('email') }}" autocomplete="email"
                        aria-describedby="regEmailHelp" @if ($errors->has('email') || session('email_exists')) aria-invalid="true" @endif>
                    <p class="pcg-help" id="regEmailHelp">You sign in with this email once your account is approved.</p>
                    @error('email')<p class="pcg-error">{{ $message }}</p>@enderror
                </div>
            </section>

            <!-- STEP 2: SERVICE DETAILS -->
            <section x-show="step === 1" x-cloak class="reg-step" data-reg-step="1" aria-labelledby="regStep1Title">
                <h3 id="regStep1Title" class="reg-step__title">Service dates</h3>

                <div class="reg-grid reg-grid--3">
                    <div class="pcg-field">
                        <label class="pcg-label" for="dob">Date of birth</label>
                        <input class="pcg-input" type="date" id="dob" name="dob" value="{{ old('dob') }}" aria-describedby="regAgeHelp"
                            @error('dob') aria-invalid="true" @enderror>
                        <p class="pcg-help" id="regAgeHelp" aria-live="polite">Age: <span id="age" class="pcg-num"></span></p>
                        @error('dob')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pcg-field">
                        <label class="pcg-label" for="date_of_appointment">Date of appointment</label>
                        <input class="pcg-input" type="date" id="date_of_appointment" name="date_of_appointment" value="{{ old('date_of_appointment') }}"
                            @error('date_of_appointment') aria-invalid="true" @enderror>
                        @error('date_of_appointment')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pcg-field">
                        <label class="pcg-label" for="ete">ETE</label>
                        <input class="pcg-input reg-readonly" type="date" id="ete" name="ete" value="{{ old('ete') }}" readonly aria-describedby="regEteHelp">
                        <p class="pcg-help" id="regEteHelp">Filled in for you: three years after your date of appointment.</p>
                        @error('ete')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <!-- STEP 3: UNIT ASSIGNMENT -->
            <section x-data="unitDropdown()" x-show="step === 2" x-cloak class="reg-step" data-reg-step="2" aria-labelledby="regStep2Title">
                <h3 id="regStep2Title" class="reg-step__title">Unit assignment</h3>
                <p class="pcg-help reg-step__help">Choose your command first; each list fills in from the one before it.</p>

                <div class="reg-grid reg-grid--2">
                    <div class="pcg-field">
                        <label class="pcg-label" for="regCommand">Command</label>
                        <select class="pcg-input" id="regCommand" name="command" x-model="selectedCommand" @change="updateCategories()"
                            @error('command') aria-invalid="true" @enderror>
                            <option value="">Choose a command</option>
                            <template x-for="cmd in Object.keys(units)" :key="cmd">
                                <option :value="cmd" x-text="cmd"></option>
                            </template>
                        </select>
                        @error('command')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pcg-field">
                        <label class="pcg-label" for="regCategory">Category</label>
                        <select class="pcg-input" id="regCategory" name="category" x-model="selectedCategory" @change="updateUnits()">
                            <option value="">Choose a category</option>
                            <template x-for="cat in categories" :key="cat">
                                <option :value="cat" x-text="cat"></option>
                            </template>
                        </select>
                        @error('category')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pcg-field">
                        <label class="pcg-label" for="regUnit">Unit</label>
                        <select class="pcg-input" id="regUnit" name="unit" x-model="selectedUnit" @change="updateSubUnits()">
                            <option value="">Choose a unit</option>
                            <template x-for="unit in unitsList" :key="unit">
                                <option :value="unit" x-text="unit"></option>
                            </template>
                        </select>
                        @error('unit')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pcg-field">
                        <label class="pcg-label" for="regSubUnit">Sub-unit</label>
                        <select class="pcg-input" id="regSubUnit" name="sub_unit" x-model="selectedSubUnit">
                            <option value="">Choose a sub-unit</option>
                            <template x-for="sub in subUnits" :key="sub">
                                <option :value="sub" x-text="sub"></option>
                            </template>
                        </select>
                        @error('sub_unit')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <!-- STEP 4: PASSWORD AND SELFIE -->
            <section x-show="step === 3" x-cloak class="reg-step" data-reg-step="3" aria-labelledby="regStep3Title">
                <h3 id="regStep3Title" class="reg-step__title">Selfie and password</h3>

                <!-- CAMERA -->
                <div class="reg-camera">
                    <div class="reg-camera__frame">
                        <video id="video" autoplay playsinline muted aria-label="Camera preview"></video>
                        <img id="selfiePreview" class="reg-camera__shot" alt="Your captured selfie" hidden>
                    </div>
                    <canvas id="canvas" hidden></canvas>

                    <div class="reg-camera__bar">
                        <p id="cameraStatus" class="pcg-help reg-camera__status" aria-live="polite">Starting the camera…</p>
                        <button type="button" id="cameraRetry" class="pcg-btn pcg-btn--secondary pcg-btn--sm" hidden>
                            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Try the camera again
                        </button>
                        <button type="button" id="captureBtn" class="pcg-btn pcg-btn--primary">
                            <i class="fa-solid fa-camera" aria-hidden="true"></i> <span id="captureLabel">Capture selfie</span>
                        </button>
                    </div>
                    <p class="pcg-help">Face the camera in good light, without a cap or sunglasses. The unit checker compares this photo with your records.</p>
                    @error('selfie_image')<p class="pcg-error">{{ $message }}</p>@enderror
                    <input type="hidden" name="selfie_image" id="selfieImage">
                </div>

                <div class="reg-grid reg-grid--2">
                    <div class="pcg-field">
                        <label class="pcg-label" for="regPassword">Password</label>
                        <div class="pcg-input-group">
                            <input class="pcg-input" type="password" id="regPassword" name="password" autocomplete="new-password"
                                aria-describedby="regPwHelp" @error('password') aria-invalid="true" @enderror>
                            <button type="button" class="pcg-input-group__action" data-pcg-toggle-password="regPassword"
                                aria-pressed="false" aria-label="Show password">Show</button>
                        </div>
                        <div class="reg-meter" aria-hidden="true"><span id="regPwBar"></span></div>
                        <p id="regPwHelp" class="pcg-help" aria-live="polite">Use 12 or more characters with upper and lower case letters, a number and a symbol.</p>
                        <ul class="reg-pw-checks" id="regPwChecks">
                            <li data-check="len">At least 8 characters</li>
                            <li data-check="case">Upper and lower case letters</li>
                            <li data-check="num">A number</li>
                            <li data-check="sym">A symbol (for example ! @ # ?)</li>
                        </ul>
                        @error('password')<p class="pcg-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="pcg-field">
                        <label class="pcg-label" for="regPasswordConfirm">Confirm password</label>
                        <div class="pcg-input-group">
                            <input class="pcg-input" type="password" id="regPasswordConfirm" name="password_confirmation" autocomplete="new-password">
                            <button type="button" class="pcg-input-group__action" data-pcg-toggle-password="regPasswordConfirm"
                                aria-pressed="false" aria-label="Show password">Show</button>
                        </div>
                        <p id="regPwMatch" class="pcg-help reg-pw-match" aria-live="polite"></p>
                    </div>
                </div>
            </section>

            <!-- NAV -->
            <div class="reg-nav">
                <button type="button" @click="prev()" x-show="step > 0" x-cloak
                    class="pcg-btn pcg-btn--secondary">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
                </button>

                <button type="button" @click="next()" x-show="step < 3" x-cloak
                    class="pcg-btn pcg-btn--cta reg-nav__main">
                    Next <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>

                <button type="submit" x-show="step === 3" x-cloak
                    class="pcg-btn pcg-btn--cta reg-nav__main">Submit registration</button>
            </div>
        </form>

        <p class="pcg-help reg-signin">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
    </div>

    {{-- Order matters: SweetAlert and the page script define what Alpine's x-data calls, so they run before Alpine (all deferred, run in order). --}}
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}" defer></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/register.js') }}" defer></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/alpinejs/alpine-3.14.9.min.js') }}" defer></script>
@endsection
