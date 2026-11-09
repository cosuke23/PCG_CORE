@extends('layouts.personnel')

@section('title', 'SOI profile')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/soi.css') }}">
@endsection

@php
    // Alpine.js (the six-step form below) needs 'unsafe-eval' on this page only.
    // Set inside this block: the one-line form of the directive next to a block
    // of the same directive is mis-paired by Blade.
    app()->instance('csp.allow_eval', true);
    $soiSteps = [
        1 => 'Basic information',
        2 => 'Personal information',
        3 => 'Dependents',
        4 => 'Education and training',
        5 => 'Appointments and duties',
        6 => 'Awards and career',
    ];
    $soiName = trim(($user->rank ?? '') . ' ' . ($user->name ?? ''));
    // submitted SOI: every step read-only (App\Support\SoiLock, owner decision 2026-11-09)
    $soiSubmittedAt = \App\Support\SoiLock::submittedAt($user);
@endphp

@section('content')
    <div class="pcg-page soi {{ $soiSubmittedAt ? 'soi--locked' : '' }}" x-data="formData()" id="soi-root"
        data-soi-locked="{{ $soiSubmittedAt ? '1' : '0' }}" data-soi-message="{{ \App\Support\SoiLock::MESSAGE }}">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $soiName !== '' ? $soiName . ': ' : '' }}Statement of Information</h2>
                @if ($soiSubmittedAt)
                    <p>Submitted on <time class="pcg-num">{{ $soiSubmittedAt->timezone('Asia/Manila')->format('d M Y, h:i A') }}</time>. Read-only: use the list or <strong>Next</strong> to look through the six steps.</p>
                @else
                    <p>Six steps. <strong>Next step</strong> saves the step you are on; you can open any step again from the list. The last step submits the SOI.</p>
                @endif
            </div>
            @if ($profile)
                <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('profile') }}">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to summary
                </a>
            @endif
        </div>

        @if ($soiSubmittedAt)
            <p class="soi-submitted-note" role="status">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <span><strong>Your SOI has been submitted and can no longer be edited.</strong>
                    To change anything, contact your Adjutant or HR Unit Admin; they can reopen it for you.</span>
            </p>
        @endif

        <nav class="soi-stepper" aria-label="SOI steps">
            <ol>
                @foreach ($soiSteps as $n => $stepLabel)
                    <li>
                        <button type="button" class="soi-stepper__btn" @click="step = {{ $n }}"
                            :aria-current="step === {{ $n }} ? 'step' : false"
                            :class="{ 'is-done': step > {{ $n }} }">
                            <span class="soi-stepper__num">{{ $n }}</span>
                            <span class="soi-stepper__label">{{ $stepLabel }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>

        <form @submit.prevent data-pcg-no-busy class="soi-form">
            <!-- ================= STEP 1 ================= -->
            <section x-show="step === 1" class="pcg-card soi-step" aria-labelledby="soi-step-1">
                <header class="soi-step__head">
                    <p class="soi-step__count">Step 1 of 6</p>
                    <h3 id="soi-step-1">Basic information</h3>
                </header>

                <div class="soi-step__body">
                    <!-- A. Full Name -->
                    <section class="soi-section">
                        <h4>A. Full name</h4>
                        <div class="soi-grid soi-grid--4">
                            <label class="soi-field">
                                <span class="pcg-label">Last name</span>
                                <input type="text" class="pcg-input" x-model="basic.last_name"
                                    @input="basic.last_name = basic.last_name.replace(/\b\w/g, c => c.toUpperCase())"
                                    value="{{ $reinlistData->last_name ?? '' }}">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">First name</span>
                                <input type="text" class="pcg-input" x-model="basic.first_name"
                                    @input="basic.first_name = basic.first_name.replace(/\b\w/g, c => c.toUpperCase())"
                                    value="{{ $reinlistData->first_name ?? '' }}">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Middle name</span>
                                <input type="text" class="pcg-input" x-model="basic.middle_name"
                                    @input="basic.middle_name = basic.middle_name.replace(/\b\w/g, c => c.toUpperCase())"
                                    value="{{ $reinlistData->middle_name ?? '' }}">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Extension (Jr., Sr.)</span>
                                <input type="text" class="pcg-input" x-model="basic.extension_name"
                                    @input="basic.extension_name = basic.extension_name.replace(/\b\w/g, c => c.toUpperCase())"
                                    value="{{ $reinlistData->extension_name ?? '' }}">
                            </label>
                        </div>
                    </section>

                    <!-- B. Service Info -->
                    <section class="soi-section">
                        <h4>B. Service information</h4>
                        <div class="soi-grid soi-grid--3">
                            <label class="soi-field">
                                <span class="pcg-label">Current rank</span>
                                <select x-model="basic.rank" required class="pcg-input">
                                    <option value="" disabled>Select Rank</option>
                                    @if ($user->registration_type === 'non_officer')
                                        <option value="ASN">Apprentice Seaman (ASN)</option>
                                        <option value="SN2">Seaman Second Class (SN2)</option>
                                        <option value="SN1">Seaman First Class (SN1)</option>
                                        <option value="ASW">Apprentice Seawoman (ASW)</option>
                                        <option value="SW2">Seawoman Second Class (SW2)</option>
                                        <option value="SW1">Seawoman First Class (SW1)</option>
                                        <option value="PO3">Petty Officer Third Class (PO3)</option>
                                        <option value="PO2">Petty Officer Second Class (PO2)</option>
                                        <option value="PO1">Petty Officer First Class (PO1)</option>
                                        <option value="CPO">Chief Petty Officer (CPO)</option>
                                        <option value="SCPO">Senior Chief Petty Officer (SCPO)</option>
                                        <option value="MCPO">Master Chief Petty Officer (MCPO)</option>
                                        <option value="FMCPO">First Master Chief Petty Officer (FMCPO)</option>
                                    @elseif($user->registration_type === 'officer')
                                        <option value="ENS">Ensign (ENS)</option>
                                        <option value="LTJG">Lieutenant Junior Grade (LTJG)</option>
                                        <option value="LT">Lieutenant (LT)</option>
                                        <option value="LCDR">Lieutenant Commander (LCDR)</option>
                                        <option value="CDR">Commander (CDR)</option>
                                        <option value="CAPT">Captain (CAPT)</option>
                                        <option value="RADM">Rear Admiral (RADM)</option>
                                        <option value="VADM">Vice Admiral (VADM)</option>
                                        <option value="ADM">Admiral (ADM)</option>
                                    @endif
                                </select>
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Serial number</span>
                                <input type="text" class="pcg-input pcg-mono soi-readonly" x-model="basic.serial_number"
                                    value="{{ $serialNumber ?? '' }}" readonly>
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">CGOC / CGNOC class</span>
                                <input type="text" class="pcg-input" x-model="basic.class">
                            </label>
                        </div>
                    </section>

                    <!-- C. Birth Date & Place -->
                    <section class="soi-section">
                        <h4>C. Birth date and place</h4>
                        <div class="soi-grid soi-grid--2">
                            <label class="soi-field">
                                <span class="pcg-label">Birth date</span>
                                <input type="date" class="pcg-input" x-model="basic.birthday" required>
                                <span id="ageDisplay" class="pcg-help pcg-num" x-text="basic.birthday ? 'Age: ' + calculatedAge : ''"></span>
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Place of birth</span>
                                <input type="text" class="pcg-input" placeholder="City, Province" x-model="basic.birth_place" required>
                            </label>
                        </div>
                    </section>
                </div>
            </section>

            <!-- ================= STEP 2 ================= -->
            <section x-show="step === 2" x-cloak class="pcg-card soi-step" aria-labelledby="soi-step-2">
                <header class="soi-step__head">
                    <p class="soi-step__count">Step 2 of 6</p>
                    <h3 id="soi-step-2">D. Personal information</h3>
                </header>

                <div class="soi-step__body">
                    <section class="soi-section">
                        <h4>Basic details</h4>
                        <div class="soi-grid soi-grid--3">
                            <label class="soi-field">
                                <span class="pcg-label">Nickname</span>
                                <input type="text" class="pcg-input" name="nickname" x-model="personal.nickname">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Sex</span>
                                <select name="sex" class="pcg-input" x-model="personal.sex">
                                    <option value="" disabled>Select sex</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                </select>
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Civil status</span>
                                <select name="civil_status" class="pcg-input" x-model="personal.civil_status">
                                    <option value="" disabled>Select civil status</option>
                                    <option>Single</option>
                                    <option>Married</option>
                                    <option>Widowed</option>
                                </select>
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Citizenship</span>
                                <input type="text" class="pcg-input" name="citizenship" x-model="personal.citizenship">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Religion</span>
                                <input type="text" class="pcg-input" name="religion" x-model="personal.religion">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Blood type</span>
                                <input type="text" class="pcg-input" name="blood_type" x-model="personal.blood_type">
                            </label>
                        </div>
                    </section>

                    <section class="soi-section">
                        <h4>Physical attributes</h4>
                        <div class="soi-grid soi-grid--5">
                            <label class="soi-field">
                                <span class="pcg-label">Height (cm)</span>
                                <input type="number" class="pcg-input" name="height" x-model="personal.height">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Weight (kg)</span>
                                <input type="number" class="pcg-input" name="weight" x-model="personal.weight">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Head size (cm)</span>
                                <input type="number" class="pcg-input" name="head_size" x-model="personal.head_size">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Foot size (cm)</span>
                                <input type="number" class="pcg-input" name="foot_size" x-model="personal.foot_size">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Waist size (cm)</span>
                                <input type="number" class="pcg-input" name="waist_size" x-model="personal.waist_size">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Body build</span>
                                <input type="text" class="pcg-input" name="body_build" x-model="personal.body_build">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Hair color</span>
                                <input type="text" class="pcg-input" name="hair_color" x-model="personal.hair_color">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Skin color</span>
                                <input type="text" class="pcg-input" name="skin_color" x-model="personal.skin_color">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Eye color</span>
                                <input type="text" class="pcg-input" name="eye_color" x-model="personal.eye_color">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Distinct features</span>
                                <input type="text" class="pcg-input" name="distinct_features" x-model="personal.distinct_features">
                            </label>
                        </div>
                    </section>

                    <section class="soi-section">
                        <h4>Contact and residence</h4>
                        <div class="soi-grid soi-grid--2">
                            <label class="soi-field">
                                <span class="pcg-label">Contact number</span>
                                <input type="text" class="pcg-input" name="contact_number" x-model="personal.contact_number" inputmode="tel">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Email</span>
                                <input type="email" class="pcg-input soi-readonly" name="email" x-model="personal.email" readonly>
                                <span class="pcg-help">The email of your account. It cannot be changed here.</span>
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Home address</span>
                                <textarea class="pcg-input" name="home_address" rows="2" x-model="personal.home_address"></textarea>
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Permanent address</span>
                                <textarea class="pcg-input" name="permanent_address" rows="2" x-model="personal.permanent_address"></textarea>
                            </label>
                        </div>
                    </section>

                    <section class="soi-section">
                        <h4>Government identifiers</h4>
                        <div class="soi-grid soi-grid--3">
                            <label class="soi-field">
                                <span class="pcg-label">TIN</span>
                                <input type="text" class="pcg-input" name="tin" x-model="personal.tin">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">PhilHealth</span>
                                <input type="text" class="pcg-input" name="philhealth" x-model="personal.philhealth">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Pag-IBIG</span>
                                <input type="text" class="pcg-input" name="pagibig" x-model="personal.pagibig">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">GSIS</span>
                                <input type="text" class="pcg-input" name="gsis" x-model="personal.gsis">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">SSS</span>
                                <input type="text" class="pcg-input" name="sss" x-model="personal.sss">
                            </label>
                            <label class="soi-field">
                                <span class="pcg-label">Driver's license</span>
                                <input type="text" class="pcg-input" name="drivers_license" x-model="personal.drivers_license">
                            </label>
                        </div>
                    </section>
                </div>
            </section>

            <!-- ================= STEP 3 ================= -->
            <section x-show="step === 3" x-cloak class="pcg-card soi-step" aria-labelledby="soi-step-3">
                <header class="soi-step__head">
                    <p class="soi-step__count">Step 3 of 6</p>
                    <h3 id="soi-step-3">Dependents</h3>
                    <p>List all legal dependents: full name, relationship and date of birth.</p>
                </header>

                <div class="soi-step__body">
                    <template x-for="(dep, index) in dependents" :key="index">
                        <div class="soi-entry">
                            <div class="soi-entry__head">
                                <span class="soi-entry__title">Dependent <span class="pcg-num" x-text="index + 1"></span></span>
                                <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="dependents.splice(index, 1)">
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                </button>
                            </div>
                            <div class="soi-grid soi-grid--3">
                                <label class="soi-field">
                                    <span class="pcg-label">Full name</span>
                                    <input type="text" class="pcg-input" x-model="dep.name">
                                </label>
                                <label class="soi-field">
                                    <span class="pcg-label">Relationship</span>
                                    <select class="pcg-input" x-model="dep.relationship">
                                        <option value="" disabled selected>Select relationship</option>
                                        <option>Spouse</option>
                                        <option>Child</option>
                                        <option>Parent</option>
                                        <option>Sibling</option>
                                    </select>
                                </label>
                                <label class="soi-field">
                                    <span class="pcg-label">Date of birth</span>
                                    <input type="date" class="pcg-input" x-model="dep.bday">
                                </label>
                            </div>
                        </div>
                    </template>
                    <p class="soi-empty" x-show="!dependents.length">No dependents listed.</p>

                    <button type="button" class="pcg-btn pcg-btn--secondary soi-add" @click="addDependent">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add dependent
                    </button>
                </div>
            </section>

            <!-- ================= STEP 4 ================= -->
            <section x-show="step === 4" x-cloak class="pcg-card soi-step" aria-labelledby="soi-step-4">
                <header class="soi-step__head">
                    <p class="soi-step__count">Step 4 of 6</p>
                    <h3 id="soi-step-4">E. Educational attainment</h3>
                    <p>Academic, vocational and specialised training history.</p>
                </header>

                <div class="soi-step__body">
                    <!-- ================= TERTIARY ================= -->
                    <section class="soi-section">
                        <div class="soi-section__head">
                            <h4>1. Tertiary education</h4>
                            <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                @click="academic.push({ course:'', school:'', year_start:'', year_end:'', remarks:'' })">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add tertiary
                            </button>
                        </div>

                        <template x-for="(edu, index) in academic" :key="index">
                            <div class="soi-entry">
                                <div class="soi-entry__head">
                                    <span class="soi-entry__title">Tertiary <span class="pcg-num" x-text="index + 1"></span></span>
                                    <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="academic.splice(index,1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                </div>
                                <div class="soi-grid soi-grid--2">
                                    <div class="soi-field">
                                        <span class="pcg-label">Course</span>
                                        <input type="search" class="pcg-input soi-course-search" placeholder="Type to filter the courses"
                                            aria-label="Filter the course list" @keyup="filterCourses($event)">
                                        <select x-model="edu.course" class="pcg-input" aria-label="Course">
                                            <option value="" disabled>Select course</option>
                                            @foreach ($courses as $course)
                                                <option value="{{ $course->course_description }}">{{ $course->course_description }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <label class="soi-field">
                                        <span class="pcg-label">School / location</span>
                                        <input class="pcg-input" x-model="edu.school">
                                    </label>
                                    <div class="soi-grid soi-grid--2 soi-grid--tight">
                                        <label class="soi-field">
                                            <span class="pcg-label">Start year</span>
                                            <input type="number" class="pcg-input" x-model="edu.year_start" placeholder="e.g. 2018">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">End year</span>
                                            <input type="number" class="pcg-input" x-model="edu.year_end" placeholder="e.g. 2022">
                                        </label>
                                    </div>
                                    <label class="soi-field">
                                        <span class="pcg-label">Remarks</span>
                                        <input class="pcg-input" x-model="edu.remarks">
                                    </label>
                                </div>
                            </div>
                        </template>
                    </section>

                    <!-- ================= MASTERAL ================= -->
                    <section class="soi-section">
                        <div class="soi-section__head">
                            <h4>Masteral / post graduate</h4>
                            <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                @click="masteral.push({ course:'', school:'', year_start:'', year_end:'', remarks:'' })">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add masteral
                            </button>
                        </div>

                        <template x-for="(edu, index) in masteral" :key="index">
                            <div class="soi-entry">
                                <div class="soi-entry__head">
                                    <span class="soi-entry__title">Masteral <span class="pcg-num" x-text="index + 1"></span></span>
                                    <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="masteral.splice(index,1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                </div>
                                <div class="soi-grid soi-grid--2">
                                    <div class="soi-field">
                                        <span class="pcg-label">Course</span>
                                        <input type="search" class="pcg-input soi-course-search" placeholder="Type to filter the courses"
                                            aria-label="Filter the course list" @keyup="filterCourses($event)">
                                        <select x-model="edu.course" class="pcg-input" aria-label="Course">
                                            <option value="" disabled>Select course</option>
                                            @foreach ($courses as $course)
                                                <option value="{{ $course->course_description }}">{{ $course->course_description }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <label class="soi-field">
                                        <span class="pcg-label">School / location</span>
                                        <input class="pcg-input" x-model="edu.school">
                                    </label>
                                    <div class="soi-grid soi-grid--2 soi-grid--tight">
                                        <label class="soi-field">
                                            <span class="pcg-label">Start year</span>
                                            <input type="number" class="pcg-input" x-model="edu.year_start" placeholder="e.g. 2020">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">End year</span>
                                            <input type="number" class="pcg-input" x-model="edu.year_end" placeholder="e.g. 2024">
                                        </label>
                                    </div>
                                    <label class="soi-field">
                                        <span class="pcg-label">Remarks</span>
                                        <input class="pcg-input" x-model="edu.remarks">
                                    </label>
                                </div>
                            </div>
                        </template>
                    </section>

                    <!-- ================= SECONDARY ================= -->
                    <section class="soi-section">
                        <h4>Secondary education</h4>
                        <div class="soi-entry">
                            <div class="soi-grid soi-grid--4">
                                <label class="soi-field">
                                    <span class="pcg-label">School / location</span>
                                    <input class="pcg-input" x-model="secondary.school">
                                </label>
                                <label class="soi-field">
                                    <span class="pcg-label">Start year</span>
                                    <input type="number" class="pcg-input" x-model="secondary.year_start" placeholder="e.g. 2014">
                                </label>
                                <label class="soi-field">
                                    <span class="pcg-label">End year</span>
                                    <input type="number" class="pcg-input" x-model="secondary.year_end" placeholder="e.g. 2018">
                                </label>
                                <label class="soi-field">
                                    <span class="pcg-label">Remarks</span>
                                    <input class="pcg-input" x-model="secondary.remarks">
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- ================= PRIMARY ================= -->
                    <section class="soi-section">
                        <h4>Primary education</h4>
                        <div class="soi-entry">
                            <div class="soi-grid soi-grid--4">
                                <label class="soi-field">
                                    <span class="pcg-label">School / location</span>
                                    <input class="pcg-input" x-model="primary.school">
                                </label>
                                <label class="soi-field">
                                    <span class="pcg-label">Start year</span>
                                    <input type="number" class="pcg-input" x-model="primary.year_start" placeholder="e.g. 2002">
                                </label>
                                <label class="soi-field">
                                    <span class="pcg-label">End year</span>
                                    <input type="number" class="pcg-input" x-model="primary.year_end" placeholder="e.g. 2008">
                                </label>
                                <label class="soi-field">
                                    <span class="pcg-label">Remarks</span>
                                    <input class="pcg-input" x-model="primary.remarks">
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- ================= VOCATIONAL ================= -->
                    <section class="soi-section">
                        <div class="soi-section__head">
                            <h4>2. Vocational</h4>
                            <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="addVocational">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add vocational
                            </button>
                        </div>

                        <template x-for="(voc, index) in vocational" :key="index">
                            <div class="soi-entry">
                                <div class="soi-entry__head">
                                    <span class="soi-entry__title">Vocational <span class="pcg-num" x-text="index + 1"></span></span>
                                    <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="vocational.splice(index, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                </div>
                                <div class="soi-grid soi-grid--4">
                                    <label class="soi-field">
                                        <span class="pcg-label">Vocational course</span>
                                        <input class="pcg-input" x-model="voc.course">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Awarding authority</span>
                                        <input class="pcg-input" x-model="voc.authority">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">School</span>
                                        <input class="pcg-input" x-model="voc.school">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Period</span>
                                        <input class="pcg-input" x-model="voc.period">
                                    </label>
                                </div>
                            </div>
                        </template>
                    </section>

                    <!-- ================= 3. TRAININGS / SEMINARS ================= -->
                    <section class="soi-section">
                        <h4>3. Trainings and seminars</h4>

                        <!-- a. COAST GUARD (recorded by the Adjutant, read-only for the member) -->
                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>a. Coast Guard</h5>
                                <span class="soi-locked"><i class="fa-solid fa-lock" aria-hidden="true"></i> Recorded by the Adjutant</span>
                            </div>
                            <p class="pcg-help">Coast Guard schooling is recorded by your unit's Adjutant. Ask the Adjutant if an entry is missing or wrong.</p>

                            <template x-for="(train, index) in (trainings.coastguard || [])" :key="'cgt' + index">
                                <div class="soi-entry soi-entry--readonly">
                                    <dl class="soi-facts">
                                        <div><dt>Course</dt><dd x-text="train.course || '—'"></dd></div>
                                        <div><dt>Type</dt><dd x-text="train.type || '—'"></dd></div>
                                        <div><dt>Authority</dt><dd x-text="train.authority || '—'"></dd></div>
                                        <div><dt>School / provider</dt><dd x-text="train.school || '—'"></dd></div>
                                        <div><dt>Period</dt><dd x-text="train.period || '—'"></dd></div>
                                        <div><dt>Remarks</dt><dd x-text="train.remarks || '—'"></dd></div>
                                    </dl>
                                    <p class="soi-entry__note" x-text="train.added_by ? 'Recorded by the Adjutant' : 'Entered before Adjutant records began'"></p>
                                </div>
                            </template>
                            <p class="soi-empty" x-show="!(trainings.coastguard || []).length">No Coast Guard schooling recorded yet.</p>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>a.1 Others: courses and seminars (local)</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="trainings.others_local.push({ course:'', authority:'', school:'', period:'', remarks:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add course
                                </button>
                            </div>

                            <template x-for="(train, index) in trainings.others_local" :key="index">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Course <span class="pcg-num" x-text="index + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--3">
                                        <label class="soi-field">
                                            <span class="pcg-label">Course</span>
                                            <input class="pcg-input" x-model="train.course">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority (order)</span>
                                            <input class="pcg-input" x-model="train.authority">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">School / provider</span>
                                            <input class="pcg-input" x-model="train.school">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Period</span>
                                            <input class="pcg-input" x-model="train.period">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Remarks</span>
                                            <input class="pcg-input" x-model="train.remarks">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>a.2 Others: courses and seminars (foreign)</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="trainings.others_foreign.push({ course:'', authority:'', school:'', period:'', remarks:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add course
                                </button>
                            </div>

                            <template x-for="(train, index) in trainings.others_foreign" :key="index">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Course <span class="pcg-num" x-text="index + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--3">
                                        <label class="soi-field">
                                            <span class="pcg-label">Course</span>
                                            <input class="pcg-input" x-model="train.course">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority (order)</span>
                                            <input class="pcg-input" x-model="train.authority">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">School / provider</span>
                                            <input class="pcg-input" x-model="train.school">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Period</span>
                                            <input class="pcg-input" x-model="train.period">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Remarks</span>
                                            <input class="pcg-input" x-model="train.remarks">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>a.3 Military (AFP)</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="trainings.military.push({ type:'', course:'', authority:'', school:'', period:'', remarks:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add training
                                </button>
                            </div>

                            <template x-for="(train, index) in trainings.military" :key="index">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Training <span class="pcg-num" x-text="index + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--3">
                                        <label class="soi-field">
                                            <span class="pcg-label">Training type</span>
                                            <select x-model="train.type" class="pcg-input">
                                                <option value="" disabled selected>Select training type</option>
                                                <option value="Basic Training">Basic Training</option>
                                                <option value="Advance Training">Advance Training</option>
                                                <option value="Strategic Training">Strategic Training</option>
                                                <option value="General Staff Course">General Staff Course</option>
                                            </select>
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Course</span>
                                            <input class="pcg-input" x-model="train.course">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority (order)</span>
                                            <input class="pcg-input" x-model="train.authority">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">School / unit / location</span>
                                            <input class="pcg-input" x-model="train.school">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Period</span>
                                            <input class="pcg-input" x-model="train.period">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Remarks</span>
                                            <input class="pcg-input" x-model="train.remarks">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>a.4 Military others (local)</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="trainings.military_others_local.push({ type:'', course:'', authority:'', school:'', period:'', remarks:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add training
                                </button>
                            </div>

                            <template x-for="(train, index) in trainings.military_others_local" :key="index">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Training <span class="pcg-num" x-text="index + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--3">
                                        <label class="soi-field">
                                            <span class="pcg-label">Course</span>
                                            <input class="pcg-input" x-model="train.course">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority (order)</span>
                                            <input class="pcg-input" x-model="train.authority">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">School / unit / location</span>
                                            <input class="pcg-input" x-model="train.school">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Period</span>
                                            <input class="pcg-input" x-model="train.period">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Remarks</span>
                                            <input class="pcg-input" x-model="train.remarks">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>a.4 Military others (foreign)</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="trainings.military_others_foreign.push({ type:'', course:'', authority:'', school:'', period:'', remarks:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add training
                                </button>
                            </div>

                            <template x-for="(train, index) in trainings.military_others_foreign" :key="index">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Training <span class="pcg-num" x-text="index + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--3">
                                        <label class="soi-field">
                                            <span class="pcg-label">Course</span>
                                            <input class="pcg-input" x-model="train.course">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority (order)</span>
                                            <input class="pcg-input" x-model="train.authority">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">School / unit / location</span>
                                            <input class="pcg-input" x-model="train.school">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Period</span>
                                            <input class="pcg-input" x-model="train.period">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Remarks</span>
                                            <input class="pcg-input" x-model="train.remarks">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>
            </section>

            <!-- ================= STEP 5 ================= -->
            <section x-show="step === 5" x-cloak class="pcg-card soi-step" aria-labelledby="soi-step-5">
                <header class="soi-step__head">
                    <p class="soi-step__count">Step 5 of 6</p>
                    <h3 id="soi-step-5">Appointments, promotions and duty assignments</h3>
                    <p>Enter every list from the present to the past.</p>
                </header>

                <div class="soi-step__body">
                    <section class="soi-section">
                        <div class="soi-section__head">
                            <h4>F. Appointments and promotions</h4>
                            <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                @click="appointments.push({description:'', date:'', authority:''})">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add appointment
                            </button>
                        </div>

                        <template x-for="(a, i) in appointments" :key="i">
                            <div class="soi-entry">
                                <div class="soi-entry__head">
                                    <span class="soi-entry__title">Appointment <span class="pcg-num" x-text="i + 1"></span></span>
                                    <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="appointments.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                </div>
                                <div class="soi-grid soi-grid--3">
                                    <label class="soi-field">
                                        <span class="pcg-label">Description</span>
                                        <input class="pcg-input" x-model="a.description" placeholder="e.g. Promotion to PO2">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Date</span>
                                        <input type="date" class="pcg-input" x-model="a.date">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Authority / orders</span>
                                        <input class="pcg-input" x-model="a.authority">
                                    </label>
                                </div>
                            </div>
                        </template>
                    </section>

                    <section class="soi-section">
                        <h4>G. Duty assignments and additional duties</h4>
                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>1. Shipboard</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="duties.shipboard.push({unit:'', position:'', period:'', authority:''})">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add shipboard
                                </button>
                            </div>

                            <template x-for="(d, i) in duties.shipboard" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Shipboard <span class="pcg-num" x-text="i + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--4">
                                        <label class="soi-field">
                                            <span class="pcg-label">Unit</span>
                                            <input class="pcg-input" x-model="d.unit">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Duty / position</span>
                                            <input class="pcg-input" x-model="d.position">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Inclusive period</span>
                                            <input class="pcg-input" x-model="d.period" placeholder="From - to">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="d.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>1.a Collateral duties (shipboard)</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="duties.shipboard_collateral.push({unit:'', position:'', period:'', authority:''})">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add collateral duty
                                </button>
                            </div>

                            <template x-for="(d, i) in duties.shipboard_collateral" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Collateral duty <span class="pcg-num" x-text="i + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--4">
                                        <label class="soi-field">
                                            <span class="pcg-label">Unit</span>
                                            <input class="pcg-input" x-model="d.unit">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Duty / position</span>
                                            <input class="pcg-input" x-model="d.position">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Inclusive period</span>
                                            <input class="pcg-input" x-model="d.period" placeholder="From - to">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="d.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>2. Shore based</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="duties.shorebased.push({unit:'', position:'', period:'', authority:''})">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add shore based
                                </button>
                            </div>

                            <template x-for="(d, i) in duties.shorebased" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Shore based <span class="pcg-num" x-text="i + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--4">
                                        <label class="soi-field">
                                            <span class="pcg-label">Unit</span>
                                            <input class="pcg-input" x-model="d.unit">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Duty / position</span>
                                            <input class="pcg-input" x-model="d.position">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Inclusive period</span>
                                            <input class="pcg-input" x-model="d.period" placeholder="From - to">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="d.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>2.a Collateral duties (shore based)</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="duties.shorebased_collateral.push({unit:'', position:'', period:'', authority:''})">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add collateral duty
                                </button>
                            </div>

                            <template x-for="(d, i) in duties.shorebased_collateral" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Collateral duty <span class="pcg-num" x-text="i + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--4">
                                        <label class="soi-field">
                                            <span class="pcg-label">Unit</span>
                                            <input class="pcg-input" x-model="d.unit">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Duty / position</span>
                                            <input class="pcg-input" x-model="d.position">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Inclusive period</span>
                                            <input class="pcg-input" x-model="d.period" placeholder="From - to">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="d.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>3. Special duties and assignments</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="duties.special.push({unit:'', position:'', period:'', authority:''})">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add special duty
                                </button>
                            </div>

                            <template x-for="(d, i) in duties.special" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Special duty <span class="pcg-num" x-text="i + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--4">
                                        <label class="soi-field">
                                            <span class="pcg-label">Unit</span>
                                            <input class="pcg-input" x-model="d.unit">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Duty / position</span>
                                            <input class="pcg-input" x-model="d.position">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Inclusive period</span>
                                            <input class="pcg-input" x-model="d.period" placeholder="From - to">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="d.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>4.a Other government service</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="duties.govt.push({position:'', agency:'', period:''})">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add government service
                                </button>
                            </div>

                            <template x-for="(d, i) in duties.govt" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Government service <span class="pcg-num" x-text="i + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--3">
                                        <label class="soi-field">
                                            <span class="pcg-label">Position / designation</span>
                                            <input class="pcg-input" x-model="d.position">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Agency</span>
                                            <input class="pcg-input" x-model="d.agency">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Inclusive period</span>
                                            <input class="pcg-input" x-model="d.period">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>4.b Non-government service</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="duties.non_govt.push({position:'', agency:'', period:''})">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add non-government service
                                </button>
                            </div>

                            <template x-for="(d, i) in duties.non_govt" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Non-government service <span class="pcg-num" x-text="i + 1"></span></span>
                                    </div>
                                    <div class="soi-grid soi-grid--3">
                                        <label class="soi-field">
                                            <span class="pcg-label">Position</span>
                                            <input class="pcg-input" x-model="d.position">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Agency</span>
                                            <input class="pcg-input" x-model="d.agency">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Inclusive period</span>
                                            <input class="pcg-input" x-model="d.period" placeholder="From - to">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>
            </section>

            <!-- ================= STEP 6 ================= -->
            <section x-show="step === 6" x-cloak class="pcg-card soi-step" aria-labelledby="soi-step-6">
                <header class="soi-step__head">
                    <p class="soi-step__count">Step 6 of 6</p>
                    <h3 id="soi-step-6">H. Awards and recognitions, I. Career management profile</h3>
                </header>

                <div class="soi-step__body">
                    <section class="soi-section">
                        <h4>1. Coast Guard</h4>

                        <!-- a. Awards and decorations (recorded by the Adjutant, read-only for the member) -->
                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>a. Awards and decorations</h5>
                                <span class="soi-locked"><i class="fa-solid fa-lock" aria-hidden="true"></i> Recorded by the Adjutant</span>
                            </div>
                            <p class="pcg-help">Coast Guard awards and decorations are recorded by your unit's Adjutant.</p>
                            <template x-for="(a,i) in cgAwardsDecorations.filter(x => x.type || x.authority)" :key="'cga' + i">
                                <div class="soi-entry soi-entry--readonly">
                                    <dl class="soi-facts">
                                        <div><dt>Award</dt><dd x-text="a.type || '—'"></dd></div>
                                        <div><dt>Authority</dt><dd x-text="a.authority || '—'"></dd></div>
                                    </dl>
                                    <p class="soi-entry__note" x-text="a.added_by ? 'Recorded by the Adjutant' : 'Entered before Adjutant records began'"></p>
                                </div>
                            </template>
                            <p class="soi-empty" x-show="!cgAwardsDecorations.some(x => x.type || x.authority)">No Coast Guard awards recorded yet.</p>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>b. Letters of commendation</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="addCgLetter()">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add letter
                                </button>
                            </div>

                            <template x-for="(a, i) in cgLetters" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Letter <span class="pcg-num" x-text="i + 1"></span></span>
                                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="cgLetters.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                    </div>
                                    <div class="soi-grid soi-grid--2">
                                        <label class="soi-field">
                                            <span class="pcg-label">Letter type</span>
                                            <input class="pcg-input" x-model="a.type">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="a.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>c. Appreciation</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="addCgAppreciation()">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add appreciation
                                </button>
                            </div>

                            <template x-for="(a, i) in cgAppreciations" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Appreciation <span class="pcg-num" x-text="i + 1"></span></span>
                                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="cgAppreciations.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                    </div>
                                    <div class="soi-grid soi-grid--2">
                                        <label class="soi-field">
                                            <span class="pcg-label">Appreciation type</span>
                                            <input class="pcg-input" x-model="a.type">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="a.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>d. Plaque</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="addCgPlaque()">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add plaque
                                </button>
                            </div>

                            <template x-for="(a, i) in cgPlaques" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Plaque <span class="pcg-num" x-text="i + 1"></span></span>
                                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="cgPlaques.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                    </div>
                                    <div class="soi-grid soi-grid--2">
                                        <label class="soi-field">
                                            <span class="pcg-label">Plaque type</span>
                                            <input class="pcg-input" x-model="a.type">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="a.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>

                    <section class="soi-section">
                        <h4>2. Other awards and recognitions</h4>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>Awards</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="addMilitaryAward()">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add award
                                </button>
                            </div>

                            <template x-for="(a,i) in militaryAwards" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Award <span class="pcg-num" x-text="i + 1"></span></span>
                                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="militaryAwards.splice(i, 1)">
                                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                        </button>
                                    </div>
                                    <div class="soi-grid soi-grid--3">
                                        <label class="soi-field">
                                            <span class="pcg-label">Branch</span>
                                            <select x-model="a.branch" @change="a.category=''; a.type=''" class="pcg-input">
                                                <option value="">Branch</option>
                                                <option value="bucor">BuCor</option>
                                                <option value="afp">AFP</option>
                                                <option value="pnp">PNP</option>
                                                <option value="bjmp">BJMP</option>
                                            </select>
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Category</span>
                                            <select x-model="a.category" @change="a.type=''" class="pcg-input">
                                                <option value="">Category</option>
                                                <!-- BUCOR -->
                                                <template x-if="a.branch === 'bucor'">
                                                    <optgroup label="BuCor">
                                                        <option value="heroism">Heroism</option>
                                                        <option value="service">Service</option>
                                                    </optgroup>
                                                </template>
                                                <!-- AFP -->
                                                <template x-if="a.branch === 'afp'">
                                                    <optgroup label="AFP">
                                                        <option value="awards">Awards</option>
                                                    </optgroup>
                                                </template>
                                                <!-- PNP -->
                                                <template x-if="a.branch === 'pnp'">
                                                    <optgroup label="PNP">
                                                        <option value="awards">Awards</option>
                                                        <option value="unit">Unit Decorations</option>
                                                        <option value="campaign">Campaign Medals</option>
                                                    </optgroup>
                                                </template>
                                                <!-- BJMP -->
                                                <template x-if="a.branch === 'bjmp'">
                                                    <optgroup label="BJMP">
                                                        <option value="awards">Awards</option>
                                                        <option value="promotion">Promotions</option>
                                                    </optgroup>
                                                </template>
                                                <!-- PCG (if needed) -->
                                                <template x-if="a.branch === 'pcg'">
                                                    <optgroup label="PCG">
                                                        <option value="awards">Awards</option>
                                                        <option value="unit">Unit Decorations</option>
                                                        <option value="campaign">Campaign Medals</option>
                                                    </optgroup>
                                                </template>
                                            </select>
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Award</span>
                                            <select x-model="a.type" class="pcg-input">
                                                <option value="">Select Award</option>
                                                <!-- BUCOR HEROISM -->
                                                <template x-if="a.branch==='bucor' && a.category==='heroism'">
                                                    <optgroup label="Heroism">
                                                        <option value="Medal of Valor (MOV)">Medal of Valor (MOV)</option>
                                                        <option value="Distinguished Conduct Medal (DCM)">Distinguished
                                                            Conduct Medal (DCM)</option>
                                                        <option value="Gold Cross Medal (GCM)">Gold Cross Medal (GCM)
                                                        </option>
                                                        <option value="Silver Cross Medal (SCM)">Silver Cross Medal (SCM)
                                                        </option>
                                                        <option value="Bronze Cross Medal (BCM)">Bronze Cross Medal (BCM)
                                                        </option>
                                                        <option value="Gallantry Medal">Gallantry Medal</option>
                                                        <option value="Merit Medal">Merit Medal</option>
                                                    </optgroup>
                                                </template>
                                                <!-- BUCOR SERVICE -->
                                                <template x-if="a.branch==='bucor' && a.category==='service'">
                                                    <optgroup label="Service">
                                                        <option value="Distinguished Service Star (DSS)">Distinguished
                                                            Service Star (DSS)</option>
                                                        <option value="Distinguished Service Medal (DSM)">Distinguished
                                                            Service Medal (DSM)</option>
                                                        <option value="BuCor Commendation Medal">BuCor Commendation Medal
                                                        </option>
                                                        <option value="BuCor Good Conduct Medal">BuCor Good Conduct Medal
                                                        </option>
                                                        <option value="BuCor Service Medal">BuCor Service Medal</option>
                                                        <option value="Special Service Medal">Special Service Medal
                                                        </option>
                                                    </optgroup>
                                                </template>
                                                <!-- AFP AWARDS -->
                                                <template x-if="a.branch==='afp' && a.category==='awards'">
                                                    <optgroup label="AFP Awards">
                                                        <option value="Medal of Valor">Medal of Valor</option>
                                                        <option value="Philippine Legion of Honor">Philippine Legion of
                                                            Honor</option>
                                                        <option value="Outstanding Achievement Medal">Outstanding
                                                            Achievement Medal</option>
                                                        <option value="Distinguished Service Medal">Distinguished Service
                                                            Medal</option>
                                                        <option value="Gold Cross">Gold Cross</option>
                                                        <option value="Silver Cross">Silver Cross</option>
                                                        <option value="Bronze Cross">Bronze Cross</option>
                                                        <option value="Military Merit Medal">Military Merit Medal</option>
                                                        <option value="Military Commendation Medal">Military Commendation
                                                            Medal</option>
                                                    </optgroup>
                                                </template>
                                                <!-- PNP AWARDS -->
                                                <template x-if="a.branch==='pnp' && a.category==='awards'">
                                                    <optgroup label="PNP Awards">
                                                        <option value="Medalya ng Kagitingan">Medalya ng Kagitingan
                                                        </option>
                                                        <option value="Medalya ng Kabayanihan">Medalya ng Kabayanihan
                                                        </option>
                                                        <option value="Medalya ng Katapatan sa Paglilingkod">Medalya ng
                                                            Katapatan sa Paglilingkod</option>
                                                        <option value="Medalya ng Katapangan">Medalya ng Katapangan
                                                        </option>
                                                        <option value="Medalya ng Kagalingan">Medalya ng Kagalingan
                                                        </option>
                                                        <option value="Medalya ng Papuri">Medalya ng Papuri</option>
                                                    </optgroup>
                                                </template>
                                                <!-- PNP UNIT -->
                                                <template x-if="a.branch==='pnp' && a.category==='unit'">
                                                    <optgroup label="Unit Decorations">
                                                        <option value="Presidential Unit Citation">Presidential Unit
                                                            Citation</option>
                                                        <option value="People Power I Unit Citation">People Power I Unit
                                                            Citation</option>
                                                        <option value="People Power II Unit Citation">People Power II Unit
                                                            Citation</option>
                                                    </optgroup>
                                                </template>
                                                <!-- PNP CAMPAIGN -->
                                                <template x-if="a.branch==='pnp' && a.category==='campaign'">
                                                    <optgroup label="Campaign Medals">
                                                        <option value="Luzon Campaign Medal">Luzon Campaign Medal</option>
                                                        <option value="Visayas Campaign Medal">Visayas Campaign Medal
                                                        </option>
                                                        <option value="Mindanao Campaign Medal">Mindanao Campaign Medal
                                                        </option>
                                                    </optgroup>
                                                </template>
                                                <!-- BJMP AWARDS -->
                                                <template x-if="a.branch==='bjmp' && a.category==='awards'">
                                                    <optgroup label="BJMP Awards">
                                                        <option value="Medalya ng Kagitingan">Medalya ng Kagitingan
                                                        </option>
                                                        <option value="Medalya ng Kabayanihan">Medalya ng Kabayanihan
                                                        </option>
                                                        <option value="Medalya ng Kagalingan">Medalya ng Kagalingan
                                                        </option>
                                                        <option value="Medalya ng Sugatang Magiting">Medalya ng Sugatang
                                                            Magiting</option>
                                                        <option value="Medalya ng Katapatan">Medalya ng Katapatan</option>
                                                        <option value="Medalya ng Paglilingkod">Medalya ng Paglilingkod
                                                        </option>
                                                    </optgroup>
                                                </template>
                                                <!-- BJMP PROMOTION -->
                                                <template x-if="a.branch==='bjmp' && a.category==='promotion'">
                                                    <optgroup label="Promotions">
                                                        <option value="Meritorious Promotion">Meritorious Promotion
                                                        </option>
                                                        <option value="On-the-Spot Promotion">On-the-Spot Promotion
                                                        </option>
                                                        <option value="Posthumous Promotion">Posthumous Promotion</option>
                                                    </optgroup>
                                                </template>
                                            </select>
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>e. Other letter of commendation</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="otherLetters.push({ type:'', authority:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add letter
                                </button>
                            </div>

                            <template x-for="(a, i) in otherLetters" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Letter <span class="pcg-num" x-text="i + 1"></span></span>
                                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="otherLetters.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                    </div>
                                    <div class="soi-grid soi-grid--2">
                                        <label class="soi-field">
                                            <span class="pcg-label">Letter type</span>
                                            <input class="pcg-input" x-model="a.type">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="a.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>f. Other appreciation</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="otherAppreciations.push({ type:'', authority:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add appreciation
                                </button>
                            </div>

                            <template x-for="(a, i) in otherAppreciations" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Appreciation <span class="pcg-num" x-text="i + 1"></span></span>
                                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="otherAppreciations.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                    </div>
                                    <div class="soi-grid soi-grid--2">
                                        <label class="soi-field">
                                            <span class="pcg-label">Appreciation type</span>
                                            <input class="pcg-input" x-model="a.type">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="a.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>g. Other plaque</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="otherPlaques.push({ type:'', authority:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add plaque
                                </button>
                            </div>

                            <template x-for="(a, i) in otherPlaques" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Plaque <span class="pcg-num" x-text="i + 1"></span></span>
                                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="otherPlaques.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                    </div>
                                    <div class="soi-grid soi-grid--2">
                                        <label class="soi-field">
                                            <span class="pcg-label">Plaque type</span>
                                            <input class="pcg-input" x-model="a.type">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="a.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>

                    <section class="soi-section">
                        <div class="soi-section__head">
                            <h4>I. Career management profile</h4>
                            <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="addCareer()">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i> Add career row
                            </button>
                        </div>

                        <div class="soi-sub">
                            <div class="soi-section__head">
                                <h5>3.a Civilian (commendations, appreciations, plaques)</h5>
                                <button type="button" class="pcg-btn pcg-btn--secondary pcg-btn--sm" @click="civilianCommendations.push({ type:'', authority:'' })">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add commendation
                                </button>
                            </div>

                            <template x-for="(a, i) in civilianCommendations" :key="i">
                                <div class="soi-entry">
                                    <div class="soi-entry__head">
                                        <span class="soi-entry__title">Commendation <span class="pcg-num" x-text="i + 1"></span></span>
                                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" @click="civilianCommendations.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                    </div>
                                    <div class="soi-grid soi-grid--2">
                                        <label class="soi-field">
                                            <span class="pcg-label">Commendation / appreciation / plaque type</span>
                                            <input class="pcg-input" x-model="a.type">
                                        </label>
                                        <label class="soi-field">
                                            <span class="pcg-label">Authority</span>
                                            <input class="pcg-input" x-model="a.authority">
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <template x-for="(c,i) in careers" :key="i">
                            <div class="soi-entry">
                                <div class="soi-entry__head">
                                    <span class="soi-entry__title">Career row <span class="pcg-num" x-text="i + 1"></span></span>
                                    <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm soi-remove" x-show="careers.length > 1" @click="careers.splice(i, 1)">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                </div>
                                <div class="soi-grid soi-grid--4">
                                    <label class="soi-field">
                                        <span class="pcg-label">Student tour</span>
                                        <input class="pcg-input" x-model="c.student" placeholder="Yrs/Mos/Days">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Sea duty</span>
                                        <input class="pcg-input" x-model="c.sea" placeholder="Yrs/Mos/Days">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Staff duty</span>
                                        <input class="pcg-input" x-model="c.staff" placeholder="Yrs/Mos/Days">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Instructor duty</span>
                                        <input class="pcg-input" x-model="c.instructor" placeholder="Yrs/Mos/Days">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Command (special / major unit)</span>
                                        <input class="pcg-input" x-model="c.command" placeholder="Yrs/Mos/Days">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Organization affiliation</span>
                                        <input type="text" class="pcg-input" x-model="c.organization_affiliation" placeholder="N/A">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Civil service eligibility / licensure</span>
                                        <input type="text" class="pcg-input" x-model="c.civil_service_eligibility">
                                    </label>
                                    <label class="soi-field">
                                        <span class="pcg-label">Date of compulsory retirement</span>
                                        <input type="text" class="pcg-input" x-model="c.compulsory_retirement" placeholder="DD Month YYYY (birth year + 56)">
                                    </label>
                                </div>
                            </div>
                        </template>
                    </section>
                </div>
            </section>

            <!-- NAVIGATION -->
            <div class="soi-actions">
                <button type="button" x-show="step > 1" x-cloak @click="step--" class="pcg-btn pcg-btn--secondary">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back
                </button>
                <span class="soi-actions__hint">Step <span class="pcg-num" x-text="step">1</span> of 6</span>

                <button type="button" x-show="step === 1" @click="saveStep1($el)" class="pcg-btn pcg-btn--cta">
                    Next step <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
                <button type="button" x-show="step === 2" x-cloak @click="saveStep2($el)" class="pcg-btn pcg-btn--cta">
                    Next step <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
                <button type="button" x-show="step === 3" x-cloak @click="saveStep3($el)" class="pcg-btn pcg-btn--cta">
                    Next step <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
                <button type="button" x-show="step === 4" x-cloak @click="saveStep4($el)" class="pcg-btn pcg-btn--cta">
                    Next step <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
                <button type="button" x-show="step === 5" x-cloak @click="saveStep5($el)" class="pcg-btn pcg-btn--cta">
                    Next step <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
                @if ($soiSubmittedAt)
                    <span x-show="step === 6" x-cloak class="pcg-badge pcg-badge--success soi-submitted-badge"><i class="fa-solid fa-lock" aria-hidden="true"></i> Submitted</span>
                @else
                    <button type="button" x-show="step === 6" x-cloak @click="saveStep6($el)" class="pcg-btn pcg-btn--cta">
                        <i class="fa-solid fa-check" aria-hidden="true"></i> Save and submit SOI
                    </button>
                @endif
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}">
        function filterCourses(event) {
            const input = event.target;
            const filter = input.value.toLowerCase();
            const select = input.nextElementSibling;
            const options = select.options;

            for (let i = 0; i < options.length; i++) {
                const txt = options[i].text.toLowerCase();
                options[i].style.display = txt.includes(filter) ? "" : "none";
            }
        }
    </script>
    <script nonce="{{ $cspNonce ?? '' }}">
        function formData() {
            return {

                // ================= STEP 1: Basic Information =================
                step: 1,
                basic: {
                    last_name: @json($reinlistData->last_name ?? ''),
                    first_name: @json($reinlistData->first_name ?? ''),
                    middle_name: @json($reinlistData->middle_name ?? ''),
                    extension_name: @json($reinlistData->extension_name ?? ''),
                    rank: @json($reinlistData->rank ?? ''),
                    class: @json($reinlistData->m_class ?? ''),
                    serial_number: @json($reinlistData->rbi_serial_number ?? ''),
                    birthday: @json($reinlistData->birthday ?? ''),
                    birth_place: @json($reinlistData->birth_place ?? '')
                },

                // ================= STEP 2: Personal Details =================
                personal: {
                    nickname: @json($reinlistData->nickname ?? ''),
                    sex: @json($reinlistData->sex ?? ''),
                    civil_status: @json($reinlistData->civil_status ?? ''),
                    citizenship: @json($reinlistData->citizenship ?? ''),
                    religion: @json($reinlistData->religion ?? ''),
                    blood_type: @json($reinlistData->blood_type ?? ''),
                    height: @json($reinlistData->height ?? ''),
                    weight: @json($reinlistData->weight ?? ''),
                    head_size: @json($reinlistData->head_size ?? ''),
                    foot_size: @json($reinlistData->foot_size ?? ''),
                    waist_size: @json($reinlistData->waist_size ?? ''),
                    distinct_features: @json($reinlistData->distinct_features ?? ''),
                    body_build: @json($reinlistData->body_build ?? ''),
                    hair_color: @json($reinlistData->hair_color ?? ''),
                    skin_color: @json($reinlistData->skin_color ?? ''),
                    eye_color: @json($reinlistData->eye_color ?? ''),
                    contact_number: @json($reinlistData->contact_number ?? ''),
                    email: @json($personalEmail ?? ''),
                    home_address: @json($reinlistData->home_address ?? ''),
                    permanent_address: @json($reinlistData->permanent_address ?? ''),
                    tin: @json($reinlistData->tin ?? ''),
                    philhealth: @json($reinlistData->philhealth ?? ''),
                    pagibig: @json($reinlistData->pagibig ?? ''),
                    gsis: @json($reinlistData->gsis ?? ''),
                    sss: @json($reinlistData->sss ?? ''),
                    drivers_license: @json($reinlistData->drivers_license ?? '')
                },

                // ================= STEP 3 - 5: Arrays =================
                // dependents: [{
                //     name: @json($reinlistData->name ?? ''),
                //     relationship: @json($reinlistData->relationship ?? ''),
                //     bday: @json($reinlistData->birthdate ?? '')
                // }],
                dependents: @json($reinlistData->dependents ?? [['name' => '', 'relationship' => '', 'bday' => '']]),
                // ================= ACADEMIC =================
                academic: @json($reinlistData->academic_tertiary),
                secondary: @json($reinlistData->academic_secondary),
                primary: @json($reinlistData->academic_primary),
                masteral: @json($reinlistData->academic_masteral),
                vocational: @json($reinlistData->vocational),

                // ================= TRAININGS =================
                trainings: @json($reinlistData->trainings),
                appointments: @json($reinlistData->appointments),

                duties: @json($reinlistData->duties),

                // ================= STEP 6: Awards & Career =================
                cgAwardsDecorations: @json($reinlistData->cgAwardsDecorations ?? []),
                cgLetters: @json($reinlistData->cgLetters ?? []),
                cgCertificates: @json($reinlistData->cgCertificates ?? []),
                militaryLetters: @json($reinlistData->militaryLetters ?? []),
                cgAppreciations: @json($reinlistData->cgAppreciations ?? []),
                cgPlaques: @json($reinlistData->cgPlaques ?? []),

                militaryAwards: @json($reinlistData->militaryAwards ?? []),
                militaryLetters: @json($reinlistData->militaryLetters ?? []),
                otherLetters: @json($reinlistData->otherLetters ?? []),
                otherAppreciations: @json($reinlistData->otherAppreciations ?? []),
                otherPlaques: @json($reinlistData->otherPlaques ?? []),
                civilianCommendations: @json($reinlistData->otherLetterCommendations ?? []),

                careers: @json($reinlistData->careers ?? []),

                organization: @json($reinlistData->organization ?? ''),
                eligibility: @json($reinlistData->eligibility ?? ''),
                retirement: @json($reinlistData->retirement ?? ''),

                // ================= GETTERS =================
                get calculatedAge() {
                    if (!this.basic.birthday) return 'N/A';
                    let birth = new Date(this.basic.birthday);
                    let today = new Date();
                    let age = today.getFullYear() - birth.getFullYear();
                    let m = today.getMonth() - birth.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                    return age;
                },

                // ================= STEP 1 TEMP SAVE =================
                async saveStep1(btn) {
                    if (btn && window.pcgBusy) window.pcgBusy(btn, true);
                    try {
                        let res = await fetch('/temp-save/step1', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.basic)
                        });

                        if (res.ok) {
                            let data = await res.json();

                            if (data.status === 'success') {
                                // Show SweetAlert2 success
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Step 1 Saved!',
                                    text: data.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                // Move to step 2
                                this.step++;
                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message || 'Failed to save Step 1'
                                });
                            }
                        } else {
                            const errorData = await res.json();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: JSON.stringify(errorData.errors)
                            });
                        }
                    } catch (err) {
                        console.error('Step 1 save error:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Please try again.'
                        });
                    } finally {
                        if (btn && window.pcgBusy) window.pcgBusy(btn, false);
                    }
                },

                // ================= STEP 2 TEMP SAVE =================
                async saveStep2(btn) {
                    if (btn && window.pcgBusy) window.pcgBusy(btn, true);
                    try {
                        // Collect all inputs inside Step 2 container
                        let step2Data = {};

                        document.querySelectorAll(
                                '[x-show="step === 2"] input, [x-show="step === 2"] select, [x-show="step === 2"] textarea'
                            )
                            .forEach(el => {
                                if (el.name) {
                                    step2Data[el.name] = el.value;
                                }
                            });


                        let res = await fetch('/temp-save/step2', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(step2Data)
                        });

                        if (res.ok) {
                            let data = await res.json();

                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Step 2 Saved!',
                                    text: data.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                // Move to Step 3
                                this.step++;
                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message || 'Failed to save Step 2'
                                });
                            }
                        } else {
                            const errorData = await res.json();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: JSON.stringify(errorData.errors)
                            });
                        }

                    } catch (err) {
                        console.error('Step 2 save error:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Please try again.'
                        });
                    } finally {
                        if (btn && window.pcgBusy) window.pcgBusy(btn, false);
                    }
                },

                // ================= STEP 3 TEMP SAVE =================
                async saveStep3(btn) {
                    if (btn && window.pcgBusy) window.pcgBusy(btn, true);
                    try {
                        // Collect all dependent data
                        let step3Data = {
                            dependents: this.dependents // x-model is already bound to `this.dependents`
                        };


                        let res = await fetch('/temp-save/step3', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(step3Data)
                        });

                        if (res.ok) {
                            let data = await res.json();

                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Step 3 Saved!',
                                    text: data.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                // Move to Step 4
                                this.step++;
                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message || 'Failed to save Step 3'
                                });
                            }
                        } else {
                            const errorData = await res.json();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: JSON.stringify(errorData.errors)
                            });
                        }

                    } catch (err) {
                        console.error('Step 3 save error:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Please try again.'
                        });
                    } finally {
                        if (btn && window.pcgBusy) window.pcgBusy(btn, false);
                    }
                },
                async saveStep4(btn) {
                    if (btn && window.pcgBusy) window.pcgBusy(btn, true);
                    try {
                        let step4Data = {
                            academic: this.academic,
                            masteral: this.masteral,
                            secondary: this.secondary,
                            primary: this.primary,
                            vocational: this.vocational,
                            trainings: this.trainings
                        };


                        let res = await fetch('/temp-save/step4', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(step4Data)
                        });

                        if (res.ok) {
                            let data = await res.json();

                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Step 4 Saved!',
                                    text: data.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                this.step++;
                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });

                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message
                                });
                            }
                        } else {
                            const errorData = await res.json();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: JSON.stringify(errorData.errors)
                            });
                        }

                    } catch (err) {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Please try again.'
                        });
                    } finally {
                        if (btn && window.pcgBusy) window.pcgBusy(btn, false);
                    }
                },
                // ================= ADD METHODS =================
                async saveStep5(btn) {
                    if (btn && window.pcgBusy) window.pcgBusy(btn, true);
                    try {

                        let step5Data = {
                            appointments: this.appointments,
                            duties: {
                                shipboard: this.duties.shipboard,
                                shipboard_collateral: this.duties.shipboard_collateral,
                                shorebased: this.duties.shorebased,
                                shorebased_collateral: this.duties.shorebased_collateral,
                                special: this.duties.special,
                                govt: this.duties.govt,
                                non_govt: this.duties.non_govt
                            }
                        };


                        let res = await fetch('/temp-save/step5', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(step5Data)
                        });

                        if (res.ok) {

                            let data = await res.json();

                            if (data.status === 'success') {

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Step 5 Saved!',
                                    text: data.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                this.step++;
                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth'
                                });

                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message
                                });
                            }

                        } else {

                            const errorData = await res.json();

                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: JSON.stringify(errorData.errors)
                            });
                        }

                    } catch (err) {

                        console.error(err);

                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Please try again.'
                        });
                    } finally {
                        if (btn && window.pcgBusy) window.pcgBusy(btn, false);
                    }
                },
                // STEP 6 SAVE
                async saveStep6(btn) {
                    // last step: the member confirms, then the SOI is saved and submitted (read-only from then on)
                    const sure = await Swal.fire({
                        icon: 'question',
                        title: 'Is the data you entered correct?',
                        text: 'Once you submit, you cannot edit your SOI any more. Only the Adjutant and the HR Admin of your unit can make changes.',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, submit my SOI',
                        cancelButtonText: 'No, return to my SOI',
                        reverseButtons: true,
                        focusCancel: true
                    });
                    if (!sure.isConfirmed) return;
                    if (btn && window.pcgBusy) window.pcgBusy(btn, true);

                    try {

                        let step6Data = {

                            cgAwardsDecorations: this.cgAwardsDecorations,
                            cgLetters: this.cgLetters,
                            cgAppreciations: this.cgAppreciations,
                            cgPlaques: this.cgPlaques,
                            militaryAwards: this.militaryAwards,
                            militaryLetters: this.militaryLetters,
                            otherLetters: this.otherLetters,
                            otherAppreciations: this.otherAppreciations,
                            otherPlaques: this.otherPlaques,
                            civilianCommendations: this.civilianCommendations,
                            civilianAppreciations: this.civilianAppreciations,
                            civilianPlaques: this.civilianPlaques,

                            careers: this.careers

                        };


                        let res = await fetch('/temp-save/step6', {

                            method: 'POST',

                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },

                            body: JSON.stringify(step6Data)

                        });

                        if (res.ok) {

                            let data = await res.json();

                            if (data.status === 'success') {

                                // step 6 saved: now submit (locks every step)
                                const sub = await fetch('/soi/submit', {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    }
                                });
                                const subData = await sub.json().catch(() => ({}));
                                if (sub.ok && subData.status === 'success') {
                                    await Swal.fire({ icon: 'success', title: 'SOI submitted', text: subData.message, confirmButtonText: 'View my SOI' });
                                    window.location.href = '/profile?edit=1';
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Not submitted', text: subData.message || 'Your answers were saved, but the SOI could not be submitted. Please try again.' });
                                }

                            } else {

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message
                                });

                            }

                        } else {

                            const errorData = await res.json();

                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: JSON.stringify(errorData.errors)
                            });

                        }

                    } catch (err) {

                        console.error(err);

                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Please try again.'
                        });

                    } finally {
                        if (btn && window.pcgBusy) window.pcgBusy(btn, false);
                    }
                },
                addDependent() {
                    this.dependents.push({
                        name: '',
                        relationship: '',
                        bday: ''
                    });
                },
                addAcademic() {
                    this.academic.push({
                        course: '',
                        school: '',
                        period: '',
                        remarks: ''
                    });
                },
                addVocational() {
                    this.vocational.push({
                        course: '',
                        authority: '',
                        school: '',
                        period: ''
                    });
                },
                addTraining() {
                    this.trainings.push({
                        course: '',
                        school: '',
                        period: '',
                        remarks: ''
                    });
                },
                addAppointment() {
                    this.appointments.push({
                        description: '',
                        date: '',
                        authority: ''
                    });
                },
                addDuty() {
                    this.duties.push({
                        unit: '',
                        position: '',
                        period: '',
                        authority: ''
                    });
                },
                addCgDecoration() {
                    this.cgAwardsDecorations.push({
                        type: '',
                        authority: ''
                    });
                },
                addCgLetter() {
                    this.cgLetters.push({
                        type: '',
                        authority: ''
                    });
                },
                addCgAppreciation() {
                    this.cgAppreciations.push({
                        type: '',
                        authority: ''
                    });
                },
                addCgPlaque() {
                    this.cgPlaques.push({
                        type: '',
                        authority: ''
                    });
                },
                addOtherLetterCommendation() {
                    this.otherLetterCommendations.push({
                        type: '',
                        authority: ''
                    });
                },
                addCivilianCommendation() {
                    this.civilianCommendations.push({
                        type: '',
                        authority: ''
                    });
                },

                addOtherAppreciation() {
                    this.otherAppreciations.push({
                        type: '',
                        authority: ''
                    });
                },

                addOtherPlaque() {
                    this.otherPlaques.push({
                        type: '',
                        authority: ''
                    });
                },
                addCgCertificate() {
                    this.cgCertificates.push({
                        type: ''
                    });
                },
                addMilitaryAward() {
                    this.militaryAwards.push({
                        type: ''
                    });
                },
                addMilitaryLetter() {
                    this.militaryLetters.push({
                        type: ''
                    });
                },
                addCareer() {
                    this.careers.push({
                        student: '',
                        sea: '',
                        staff: '',
                        instructor: '',
                        command: '',
                        organization_affiliation: '',
                        civil_service_eligibility: '',
                        compulsory_retirement: ''
                    });
                },

                addCgAppreciation() {
                    this.cgAppreciations.push({
                        type: '',
                        authority: ''
                    });
                },

                addCgPlaque() {
                    this.cgPlaques.push({
                        type: '',
                        authority: ''
                    });
                },
                // ================= REMOVE METHODS =================
                removeItem(array, index) {
                    if (this[array].length > 1) this[array].splice(index, 1);
                    else alert("At least one entry is required.");
                },

                // ================= FINAL SUBMISSION =================
                // async submitForm() {
                //     let payload = {
                //         basic: this.basic,
                //         personal: this.personal,
                //         dependents: this.dependents,
                //         academic: this.academic,
                //         vocational: this.vocational,
                //         trainings: this.trainings,
                //         appointments: this.appointments,
                //         duties: this.duties,
                //         awards: {
                //             cgAwardsDecorations: this.cgAwardsDecorations,
                //             cgLetters: this.cgLetters,
                //             cgCertificates: this.cgCertificates,
                //             cgPlaques: this.cgPlaques,
                //             militaryAwards: this.militaryAwards,
                //             militaryLetters: this.militaryLetters
                //         },
                //         careers: this.careers,
                //         organization: this.organization,
                //         eligibility: this.eligibility,
                //         retirement: this.retirement
                //     };

                //     try {
                //         let res = await fetch('/reinlist/store', {
                //             method: 'POST',
                //             headers: {
                //                 'Content-Type': 'application/json',
                //                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                //             },
                //             body: JSON.stringify(payload)
                //         });
                //     } catch (e) {
                //         console.error('Fetch Error:', e);
                //         alert('Connection error. Please try again later.');
                //     }
                // }
            }
        }
    </script>
    {{-- Alpine starts after the component functions above are defined --}}
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/soi-lock.js') }}" defer></script>{{-- read-only when submitted --}}
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/alpinejs/alpine-3.14.9.min.js') }}" defer></script>
@endsection
