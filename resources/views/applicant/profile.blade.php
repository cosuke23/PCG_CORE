@extends('layouts.app')
<br>
<br>
<br>
<br>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Alpine.js is loaded once by layouts/app (a second copy initialised the form twice) --}}
    <script src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>
    <title>SOI Form</title>
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
</head>

<body class="bg-gray-900 text-white">

    <div x-data="formData()" class="max-w-6xl mx-auto p-6">

        <form @submit.prevent="submitForm">
            <!-- ================= STEP 1 ================= -->
            <div x-show="step === 1" class="space-y-8">
                <h2 class="text-xl font-bold text-white-800 border-b pb-2">Step <span x-text="step"></span></p>: Basic
                    Information</h2>

                <!-- A. Full Name -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider">A. Full Name</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                        <!-- Last Name -->
                        <div class="relative">
                            <input type="text" placeholder=" " x-model="basic.last_name"
                                @input="basic.last_name = basic.last_name.replace(/\b\w/g, c => c.toUpperCase())"
                                value="{{ $reinlistData->last_name ?? '' }}"
                                class="peer w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm">
                            <label
                                class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500 transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:-top-2.5 peer-focus:text-xs peer-focus:text-blue-500">
                                Last Name
                            </label>
                        </div>

                        <!-- First Name -->
                        <div class="relative">
                            <input type="text" placeholder=" " x-model="basic.first_name"
                                @input="basic.first_name = basic.first_name.replace(/\b\w/g, c => c.toUpperCase())"
                                value="{{ $reinlistData->first_name ?? '' }}"
                                class="peer w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm">
                            <label
                                class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500 transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:-top-2.5 peer-focus:text-xs peer-focus:text-blue-500">
                                First Name
                            </label>
                        </div>

                        <!-- Middle Name -->
                        <div class="relative">
                            <input type="text" placeholder=" " x-model="basic.middle_name"
                                @input="basic.middle_name = basic.middle_name.replace(/\b\w/g, c => c.toUpperCase())"
                                value="{{ $reinlistData->middle_name ?? '' }}"
                                class="peer w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm">
                            <label
                                class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500 transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:-top-2.5 peer-focus:text-xs peer-focus:text-blue-500">
                                Middle Name
                            </label>
                        </div>

                        <!-- Extension (Jr./Sr.) -->
                        <div class="relative">
                            <input type="text" placeholder=" " x-model="basic.extension_name"
                                @input="basic.extension_name = basic.extension_name.replace(/\b\w/g, c => c.toUpperCase())"
                                value="{{ $reinlistData->extension_name ?? '' }}"
                                class="peer w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm">
                            <label
                                class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500 transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:-top-2.5 peer-focus:text-xs peer-focus:text-blue-500">
                                Ext. (Jr./Sr.)
                            </label>
                        </div>

                    </div>
                </div>

                <!-- B. Service Info -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider">B. Service Information</h3>
                    <div class="grid md:grid-cols-3 gap-6">
                        <div class="relative">
                            <select x-model="basic.rank" required
                                class="w-full px-4 py-3 bg-white/90 text-black border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent transition duration-200 appearance-none cursor-pointer">
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
                            <label
                                class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-blue-500">Current
                                Rank</label>
                        </div>

                        <div class="relative">
                            <input type="text" placeholder=" " x-model="basic.serial_number"
                                value="{{ $serialNumber ?? '' }}" readonly
                                class="peer w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm">
                            <label class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500">
                                Serial Number
                            </label>
                        </div>

                        <div class="relative">
                            <input type="text" placeholder=" " x-model="basic.class"
                                class="peer w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm">
                            <label class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500">CGOC
                                / CGNOC Class</label>
                        </div>
                    </div>
                </div>

                <!-- C. Birth Date & Place -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider">C. Birth Date and Place
                    </h3>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="relative">
                            <input type="date" x-model="basic.birthday" @change="calculateAge()"
                                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm"
                                required>
                            <label
                                class="absolute -top-2.5 left-3 bg-white px-1 text-xs font-medium text-gray-500">Birth
                                Date</label>
                        </div>

                        <div class="relative">
                            <input type="text" placeholder="City, Province" x-model="basic.birth_place"
                                class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm"
                                required>
                            <label
                                class="absolute -top-2.5 left-3 bg-white px-1 text-xs font-medium text-gray-500">Place
                                of Birth</label>
                        </div>
                    </div>
                    <p id="ageDisplay" class="text-xs font-semibold text-blue-600 mt-1 ml-1" x-text="basic.birthday ? 'Age: ' + calculatedAge : ''"></p>
                </div>
            </div>

            <!-- ================= STEP 2 ================= -->
            <div x-show="step === 2" class="space-y-8">
                <h2 class="text-xl font-bold text-white-800 border-b pb-2">
                    Step <span x-text="step"></span>: D. PERSONAL INFORMATION
                </h2>

                <!-- Basic Details -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider">Basic Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="relative">
                            <input type="text" name="nickname" placeholder=" " x-model="personal.nickname"
                                class="peer w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-200 shadow-sm">
                            <label
                                class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500 transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:-top-2.5 peer-focus:text-xs peer-focus:text-blue-500">
                                Nickname
                            </label>
                        </div>

                        <select name="sex" x-model="personal.sex"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                            <option value="" disabled>Select Sex</option>
                            <option>Male</option>
                            <option>Female</option>
                        </select>

                        <select name="civil_status" x-model="personal.civil_status"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                            <option value="" disabled>Select Civil Status</option>
                            <option>Single</option>
                            <option>Married</option>
                            <option>Widowed</option>
                        </select>

                        <input type="text" name="citizenship" placeholder="Citizenship"
                            x-model="personal.citizenship"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <input type="text" name="religion" placeholder="Religion" x-model="personal.religion"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <input type="text" name="blood_type" placeholder="Blood Type"
                            x-model="personal.blood_type"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                    </div>
                </div>

                <!-- Physical Attributes -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider">Physical Attributes</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">

                        <!-- ROW 1 -->
                        <input type="number" name="height" placeholder="Height (cm)" x-model="personal.height"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                        <input type="number" name="weight" placeholder="Weight (kg)" x-model="personal.weight"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                        <input type="number" name="head_size" placeholder="Head Size (cms)"
                            x-model="personal.head_size"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                        <input type="number" name="foot_size" placeholder="Foot Size (cms)"
                            x-model="personal.foot_size"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                        <input type="number" name="waist_size" placeholder="Waist Size (cms)"
                            x-model="personal.waist_size"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                        <input type="text" name="distinct_features" placeholder="Distinct Features"
                            x-model="personal.distinct_features"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <!-- ROW 2 -->
                        <input type="text" name="body_build" placeholder="Body Build"
                            x-model="personal.body_build"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                        <input type="text" name="hair_color" placeholder="Hair Color"
                            x-model="personal.hair_color"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                        <input type="text" name="skin_color" placeholder="Skin Color"
                            x-model="personal.skin_color"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                        <input type="text" name="eye_color" placeholder="Eye Color" x-model="personal.eye_color"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">

                    </div>
                </div>

                <!-- Contact & Residence -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider">Contact & Residence</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="text" name="contact_number" placeholder="Contact Number"
                            x-model="personal.contact_number"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <input type="email" name="email" x-model="personal.email" readonly
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 cursor-not-allowed shadow-none">
                        <textarea name="home_address" placeholder="Home Address" rows="2" x-model="personal.home_address"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm"></textarea>
                        <textarea name="permanent_address" placeholder="Permanent Address" rows="2"
                            x-model="personal.permanent_address"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm"></textarea>
                    </div>
                </div>

                <!-- Government Identifiers -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase tracking-wider">Government Identifiers
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <input type="text" name="tin" placeholder="TIN" x-model="personal.tin"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <input type="text" name="philhealth" placeholder="PhilHealth"
                            x-model="personal.philhealth"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <input type="text" name="pagibig" placeholder="PAG-IBIG" x-model="personal.pagibig"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <input type="text" name="gsis" placeholder="GSIS" x-model="personal.gsis"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <input type="text" name="sss" placeholder="SSS" x-model="personal.sss"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                        <input type="text" name="drivers_license" placeholder="Driver's License"
                            x-model="personal.drivers_license"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                    </div>
                </div>
            </div>
            <!-- ================= STEP 3 ================= -->

            <div x-show="step === 3" class="space-y-6">
                <div class="border-b pb-2">
                    <h2 class="text-xl font-bold text-white-800">Step 3: Dependents</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Please list all legal dependents. Entry includes: Full Name, Relationship, and Date of Birth.
                    </p>
                </div>

                <div class="space-y-4">
                    <template x-for="(dep, index) in dependents" :key="index">
                        <div
                            class="relative p-5 bg-gray-50 border border-gray-200 rounded-xl shadow-sm transition-all animate-fadeIn">

                            <button type="button" @click="dependents.splice(index, 1)"
                                class="absolute -top-2 -right-2 bg-red-100 text-red-600 rounded-full p-1.5 hover:bg-red-200 transition-colors shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="relative">
                                    <input type="text" x-model="dep.name" placeholder=" "
                                        class="peer w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 shadow-sm">
                                    <label
                                        class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500 transition-all peer-placeholder-shown:top-2.5 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-focus:-top-2.5 peer-focus:text-xs peer-focus:text-blue-500">Dependent
                                        Name</label>
                                </div>

                                <div class="relative">
                                    <select x-model="dep.relationship"
                                        class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer appearance-none">
                                        <option value="" disabled selected>Select Relationship</option>
                                        <option>Spouse</option>
                                        <option>Child</option>
                                        <option>Parent</option>
                                        <option>Sibling</option>
                                    </select>
                                    <label
                                        class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-blue-500">Relationship</label>
                                </div>

                                <div class="relative">
                                    <input type="date" x-model="dep.bday"
                                        class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-black focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm uppercase text-sm">
                                    <label
                                        class="absolute left-3 -top-2.5 bg-white px-1 text-xs font-medium text-gray-500">Date
                                        of Birth</label>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-center py-4">
                    <button type="button" @click="addDependent"
                        class="flex items-center gap-2 px-6 py-3 border-2 border-dashed border-blue-400 text-blue-600 font-semibold rounded-xl hover:bg-blue-50 hover:border-blue-500 transition-all duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Add Dependent
                    </button>
                </div>
            </div>

            <!-- ================= STEP 4 ================= -->

            <div x-show="step === 4" class="space-y-8">
                <div class="border-b pb-2">
                    <h2 class="text-xl font-bold text-white-800">Step 4: E. EDUCATIONAL ATTAINMENT</h2>
                    <p class="text-sm text-gray-500 mt-1">Please provide details regarding your academic, vocational,
                        and specialized training history.</p>
                </div>

                <!-- ================= MASTERAL ================= -->
                <div class="space-y-4 mt-8">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-blue-600 uppercase text-sm">2. Masteral / Post Graduate</h3>

                        <button type="button"
                            @click="masteral.push({
                course:'', school:'', year_start:'', year_end:'', remarks:''
            })"
                            class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border border-blue-200 hover:bg-blue-100">
                            + Add Masteral
                        </button>
                    </div>

                    <template x-for="(edu, index) in masteral" :key="index">
                        <div class="p-5 bg-white border rounded-xl shadow-sm space-y-4 relative">

                            <!-- REMOVE -->
                            <button type="button" @click="masteral.splice(index,1)"
                                class="absolute top-2 right-2 text-red-500">✕</button>

                            <div class="grid md:grid-cols-2 gap-4 text-black">

                                <!-- COURSE SEARCH -->
                                <div>
                                    <input type="text" placeholder="Search Masteral Course"
                                        class="w-full px-4 py-2 mb-2 border rounded-lg text-black"
                                        @keyup="filterCourses($event)">

                                    <select x-model="edu.course"
                                        class="w-full px-4 py-2 border rounded-lg text-black">
                                        <option value="" disabled>Select Course</option>
                                        @foreach ($courses as $course)
                                            <option value="{{ $course->course_description }}">
                                                {{ $course->course_description }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- SCHOOL -->
                                <input x-model="edu.school" placeholder="School / Location"
                                    class="w-full px-4 py-2 border rounded-lg text-black">

                                <!-- YEAR START / END -->
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="number" x-model="edu.year_start"
                                        placeholder="Start Year (e.g. 2020)"
                                        class="w-full px-4 py-2 border rounded-lg text-black">

                                    <input type="number" x-model="edu.year_end" placeholder="End Year (e.g. 2024)"
                                        class="w-full px-4 py-2 border rounded-lg text-black">
                                </div>

                                <!-- REMARKS -->
                                <input x-model="edu.remarks" placeholder="Remarks"
                                    class="w-full px-4 py-2 border rounded-lg text-black">
                            </div>
                        </div>
                    </template>
                </div>
                <!-- ================= TERTIARY ================= -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-blue-600 uppercase text-sm">1. Tertiary Education</h3>

                        <button type="button"
                            @click="academic.push({
                course:'', school:'', year_start:'', year_end:'', remarks:''
            })"
                            class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border border-blue-200 hover:bg-blue-100">
                            + Add Tertiary
                        </button>
                    </div>

                    <template x-for="(edu, index) in academic" :key="index">
                        <div class="p-5 bg-white border rounded-xl shadow-sm space-y-4 relative">

                            <!-- REMOVE -->
                            <button type="button" @click="academic.splice(index,1)"
                                class="absolute top-2 right-2 text-red-500">✕</button>

                            <div class="grid md:grid-cols-2 gap-4 text-black">

                                <!-- COURSE SEARCH + DROPDOWN -->
                                <div>
                                    <input type="text" placeholder="Search Course"
                                        class="w-full px-4 py-2 mb-2 border rounded-lg text-black"
                                        @keyup="filterCourses($event)">

                                    <select x-model="edu.course"
                                        class="w-full px-4 py-2 border rounded-lg text-black">
                                        <option value="" disabled>Select Course</option>
                                        @foreach ($courses as $course)
                                            <option value="{{ $course->course_description }}">
                                                {{ $course->course_description }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- SCHOOL -->
                                <input x-model="edu.school" placeholder="School / Location"
                                    class="w-full px-4 py-2 border rounded-lg text-black">

                                <!-- YEAR START / END -->
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="number" x-model="edu.year_start"
                                        placeholder="Start Year (e.g. 2018)"
                                        class="w-full px-4 py-2 border rounded-lg text-black">

                                    <input type="number" x-model="edu.year_end" placeholder="End Year (e.g. 2022)"
                                        class="w-full px-4 py-2 border rounded-lg text-black">
                                </div>

                                <!-- REMARKS -->
                                <input x-model="edu.remarks" placeholder="Remarks"
                                    class="w-full px-4 py-2 border rounded-lg text-black">
                            </div>
                        </div>
                    </template>
                </div>
                <!-- ================= SECONDARY ================= -->
                <div class="space-y-4 mt-8 text-black">
                    <h3 class="font-bold text-blue-600 uppercase text-sm">3. Secondary Education</h3>

                    <div class="grid md:grid-cols-2 gap-4 bg-white p-5 border rounded-xl shadow-sm">

                        <!-- SCHOOL -->
                        <input x-model="secondary.school" placeholder="School / Location"
                            class="w-full px-4 py-2 border rounded-lg text-black">

                        <!-- REMARKS -->
                        <input x-model="secondary.remarks" placeholder="Remarks"
                            class="w-full px-4 py-2 border rounded-lg text-black">

                        <!-- YEAR START / END -->
                        <div class="grid grid-cols-2 gap-2 md:col-span-2">
                            <input type="number" x-model="secondary.year_start" placeholder="Start Year (e.g. 2014)"
                                class="w-full px-4 py-2 border rounded-lg text-black">

                            <input type="number" x-model="secondary.year_end" placeholder="End Year (e.g. 2018)"
                                class="w-full px-4 py-2 border rounded-lg text-black">
                        </div>

                    </div>
                </div>
                <!-- ================= PRIMARY ================= -->
                <div class="space-y-4 mt-6 text-black">
                    <h3 class="font-bold text-blue-600 uppercase text-sm">4. Primary Education</h3>

                    <div class="grid md:grid-cols-2 gap-4 bg-white p-5 border rounded-xl shadow-sm">

                        <!-- SCHOOL -->
                        <input x-model="primary.school" placeholder="School / Location"
                            class="w-full px-4 py-2 border rounded-lg text-black">

                        <!-- REMARKS -->
                        <input x-model="primary.remarks" placeholder="Remarks"
                            class="w-full px-4 py-2 border rounded-lg text-black">

                        <!-- YEAR START / END -->
                        <div class="grid grid-cols-2 gap-2 md:col-span-2">
                            <input type="number" x-model="primary.year_start" placeholder="Start Year (e.g. 2002)"
                                class="w-full px-4 py-2 border rounded-lg text-black">

                            <input type="number" x-model="primary.year_end" placeholder="End Year (e.g. 2008)"
                                class="w-full px-4 py-2 border rounded-lg text-black">
                        </div>

                    </div>
                </div>
                <div class="space-y-4 pt-4 border-t border-gray-100">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-blue-600 uppercase tracking-wide text-sm">2. Vocational</h3>
                        <button type="button" @click="addVocational"
                            class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border border-blue-200 hover:bg-blue-100 transition-colors font-semibold">
                            + Add Vocational
                        </button>
                    </div>

                    <div class="space-y-4">
                        <template x-for="(voc, index) in vocational" :key="index">
                            <div
                                class="relative p-5 bg-white border border-gray-200 rounded-xl shadow-sm space-y-4 animate-fadeIn">
                                <button type="button" @click="vocational.splice(index, 1)"
                                    class="absolute top-3 right-3 text-gray-400 hover:text-red-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>

                                <div class="grid md:grid-cols-2 gap-4">
                                    <input x-model="voc.course"
                                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-black placeholder-gray-400 focus:ring-2 focus:ring-blue-500 outline-none"
                                        placeholder="Vocational Course">
                                    <input x-model="voc.authority"
                                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-black placeholder-gray-400 focus:ring-2 focus:ring-blue-500 outline-none"
                                        placeholder="Awarding Authority">
                                    <input x-model="voc.school"
                                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-black placeholder-gray-400 focus:ring-2 focus:ring-blue-500 outline-none"
                                        placeholder="School">
                                    <input x-model="voc.period"
                                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-black placeholder-gray-400 focus:ring-2 focus:ring-blue-500 outline-none"
                                        placeholder="Period">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="space-y-4 pt-4 border-t border-gray-100">


                    <!-- ================= A. COAST GUARD ================= -->
                    <div class="space-y-4 pt-4 border-t">
                        <div class="flex justify-between items-center">
                            <h3 class="font-bold text-blue-600 uppercase tracking-wide text-sm">3.Trainings /
                                Seminars
                            </h3>
                            <h3 class="font-bold text-blue-600 text-sm">a. Coast Guard</h3>

                            <button type="button"
                                @click="trainings.coastguard.push({
                                    course:'', type:'', authority:'', school:'', period:'', remarks:''
                                })"
                                class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border">
                                + Add Training
                            </button>
                        </div>

                        <template x-for="(train, index) in trainings.coastguard" :key="index">
                            <div class="p-5 bg-white border rounded-xl shadow-sm space-y-4 relative">

                                <button @click="trainings.coastguard.splice(index,1)"
                                    class="absolute top-2 right-2 text-red-500">✕</button>

                                <div class="grid md:grid-cols-2 gap-4 text-black">

                                    <!-- TYPE -->
                                    <select x-model="train.type" class="w-full px-4 py-2 border rounded-lg">
                                        <option value="" disabled>Select Training Type</option>
                                        <option>Advance Training</option>
                                        <option>Strategic Training</option>
                                        <option>PCG Admiral Staff Course</option>
                                    </select>

                                    <!-- COURSE -->
                                    <input x-model="train.course" placeholder="Course Name"
                                        class="w-full px-4 py-2 border rounded-lg">

                                    <!-- AUTHORITY -->
                                    <input x-model="train.authority" placeholder="Authority (Order)"
                                        class="w-full px-4 py-2 border rounded-lg">

                                    <!-- SCHOOL -->
                                    <input x-model="train.school" placeholder="School / Provider"
                                        class="w-full px-4 py-2 border rounded-lg">

                                    <!-- PERIOD -->
                                    <input x-model="train.period" placeholder="Period"
                                        class="w-full px-4 py-2 border rounded-lg">

                                    <!-- REMARKS -->
                                    <input x-model="train.remarks" placeholder="Remarks"
                                        class="w-full px-4 py-2 border rounded-lg">
                                </div>
                            </div>
                        </template>
                    </div>
                    <!-- ================= A.1 OTHERS LOCAL ================= -->
                    <div class="space-y-4 mt-6">
                        <div class="flex justify-between items-center">
                            <h3 class="font-bold text-blue-600 text-sm">a.1 Others: Courses/Seminars (Local)</h3>

                            <button type="button"
                                @click="trainings.others_local.push({
                                course:'', authority:'', school:'', period:'', remarks:''
                            })"
                                class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border">
                                + Add
                            </button>
                        </div>

                        <template x-for="(train, index) in trainings.others_local" :key="index">
                            <div class="p-5 bg-white border rounded-xl shadow-sm grid md:grid-cols-2 gap-4 text-black">

                                <input x-model="train.course" placeholder="Course Name"
                                    class="px-4 py-2 border rounded-lg">
                                <input x-model="train.authority" placeholder="Authority (Order)"
                                    class="px-4 py-2 border rounded-lg">
                                <input x-model="train.school" placeholder="School / Provider"
                                    class="px-4 py-2 border rounded-lg">
                                <input x-model="train.period" placeholder="Period"
                                    class="px-4 py-2 border rounded-lg">
                                <input x-model="train.remarks" placeholder="Remarks"
                                    class="px-4 py-2 border rounded-lg">

                            </div>
                        </template>
                    </div>

                    <!-- ================= A.2 OTHERS FOREIGN ================= -->
                    <div class="space-y-4 mt-6">
                        <div class="flex justify-between items-center">
                            <h3 class="font-bold text-blue-600 text-sm">a.2 Others: Courses/Seminars (Foreign)</h3>

                            <button type="button"
                                @click="trainings.others_foreign.push({
                                course:'', authority:'', school:'', period:'', remarks:''
                            })"
                                class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border">
                                + Add
                            </button>
                        </div>

                        <template x-for="(train, index) in trainings.others_foreign" :key="index">
                            <div class="p-5 bg-white border rounded-xl shadow-sm grid md:grid-cols-2 gap-4 text-black">

                                <input x-model="train.course" placeholder="Course Name"
                                    class="px-4 py-2 border rounded-lg">
                                <input x-model="train.authority" placeholder="Authority (Order)"
                                    class="px-4 py-2 border rounded-lg">
                                <input x-model="train.school" placeholder="School / Provider"
                                    class="px-4 py-2 border rounded-lg">
                                <input x-model="train.period" placeholder="Period"
                                    class="px-4 py-2 border rounded-lg">
                                <input x-model="train.remarks" placeholder="Remarks"
                                    class="px-4 py-2 border rounded-lg">

                            </div>
                        </template>
                    </div>
                    <!-- ================= A.3 MILITARY (AFP) ================= -->
                    <div class="space-y-4 mt-6">
                        <div class="flex justify-between items-center">
                            <h3 class="font-bold text-blue-600 text-sm">a.3. Military (AFP)</h3>

                            <button type="button"
                                @click="trainings.military.push({
                            type:'', course:'', authority:'', school:'', period:'', remarks:''
                        })"
                                class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border">
                                + Add
                            </button>
                        </div>

                        <template x-for="(train, index) in trainings.military" :key="index">
                            <div class="p-5 bg-white border rounded-xl shadow-sm grid md:grid-cols-2 gap-4 text-black">

                                <!-- DROPDOWN -->
                                <select x-model="train.type" class="w-full px-4 py-2 border rounded-lg bg-white">
                                    <option value="" disabled selected>Select Training Type</option>
                                    <option value="Basic Training">Basic Training</option>
                                    <option value="Advance Training">Advance Training</option>
                                    <option value="Strategic Training">Strategic Training</option>
                                    <option value="General Staff Course">General Staff Course</option>
                                </select>

                                <input x-model="train.course" placeholder="Course / Training Name"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.authority" placeholder="Authority (Order)"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.school" placeholder="School / Unit / Location"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.period" placeholder="Period"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.remarks" placeholder="Remarks"
                                    class="px-4 py-2 border rounded-lg">

                            </div>
                        </template>
                    </div>
                    <!-- ================= A.4 MILITARY OTHERS (LOCAL) ================= -->
                    <div class="space-y-4 mt-6">
                        <div class="flex justify-between items-center">
                            <h3 class="font-bold text-blue-600 text-sm">a.4. Military Others (Local)</h3>

                            <button type="button"
                                @click="trainings.military_others_local.push({
                            type:'', course:'', authority:'', school:'', period:'', remarks:''
                        })"
                                class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border">
                                + Add
                            </button>
                        </div>

                        <template x-for="(train, index) in trainings.military_others_local" :key="index">
                            <div class="p-5 bg-white border rounded-xl shadow-sm grid md:grid-cols-2 gap-4 text-black">

                                <input x-model="train.course" placeholder="Course / Training Name"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.authority" placeholder="Authority (Order)"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.school" placeholder="School / Unit / Location"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.period" placeholder="Period"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.remarks" placeholder="Remarks"
                                    class="px-4 py-2 border rounded-lg">

                            </div>
                        </template>
                    </div>
                    <!-- ================= A.4 MILITARY OTHERS (FOREIGN) ================= -->
                    <div class="space-y-4 mt-6">
                        <div class="flex justify-between items-center">
                            <h3 class="font-bold text-blue-600 text-sm">a.4. Military Others (Foreign)</h3>

                            <button type="button"
                                @click="trainings.military_others_foreign.push({
                            type:'', course:'', authority:'', school:'', period:'', remarks:''
                        })"
                                class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg border">
                                + Add
                            </button>
                        </div>

                        <template x-for="(train, index) in trainings.military_others_foreign" :key="index">
                            <div class="p-5 bg-white border rounded-xl shadow-sm grid md:grid-cols-2 gap-4 text-black">

                                <input x-model="train.course" placeholder="Course / Training Name"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.authority" placeholder="Authority (Order)"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.school" placeholder="School / Unit / Location"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.period" placeholder="Period"
                                    class="px-4 py-2 border rounded-lg">

                                <input x-model="train.remarks" placeholder="Remarks"
                                    class="px-4 py-2 border rounded-lg">

                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ================= STEP 5 ================= -->
            <div x-show="step === 5" class="space-y-10">
                <div class="space-y-4">
                    <div class="border-b pb-2 flex justify-between items-end">
                        <div>
                            <h2 class="text-xl font-bold text-white-800">Step 5: F. Appointments & Promotions</h2>
                            <p class="text-xs text-blue-600 font-medium flex items-center gap-1 mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                </svg>
                                Order: Present to Past
                            </p>
                        </div>
                        <button type="button" @click="appointments.push({description:'', date:'', authority:''})"
                            class="text-xs bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors shadow-sm font-semibold">
                            + Add Appointment
                        </button>
                    </div>

                    <div class="space-y-4">
                        <template x-for="(a, i) in appointments" :key="i">
                            <div
                                class="relative p-5 bg-white border border-gray-200 rounded-xl shadow-sm space-y-4 animate-fadeIn">
                                <button type="button" @click="appointments.splice(i, 1)"
                                    class="absolute top-3 right-3 text-gray-400 hover:text-red-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>

                                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div class="md:col-span-2 lg:col-span-1">
                                        <input x-model="a.description"
                                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-black placeholder-gray-400 focus:ring-2 focus:ring-blue-500 outline-none"
                                            placeholder="Description (e.g. Promotion to PO2)">
                                    </div>
                                    <input x-model="a.date" type="date"
                                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-black focus:ring-2 focus:ring-blue-500 outline-none uppercase text-sm">
                                    <input x-model="a.authority"
                                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-black placeholder-gray-400 focus:ring-2 focus:ring-blue-500 outline-none"
                                        placeholder="Authority/Orders">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="space-y-6 pt-6 border-t border-gray-200">

                    <!-- HEADER -->
                    <div>
                        <h2 class="text-xl font-bold text-white-800">
                            G. DUTY ASSIGNMENTS AND ADDITIONAL DUTIES
                        </h2>
                        <p class="text-xs text-blue-600 mt-1">
                            Entry shall be from Present to Past
                        </p>
                    </div>

                    <!-- ========================================================= -->
                    <!-- 1. SHIPBOARD -->
                    <!-- ========================================================= -->
                    <div class="space-y-4">
                        <h3 class="font-bold text-blue-600 text-sm">1. SHIPBOARD</h3>

                        <button type="button"
                            @click="duties.shipboard.push({unit:'', position:'', period:'', authority:''})"
                            class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">
                            + Add Shipboard
                        </button>

                        <template x-for="(d, i) in duties.shipboard" :key="i">
                            <div class="grid md:grid-cols-4 gap-3 bg-white p-4 border rounded-lg">

                                <input x-model="d.unit" placeholder="UNIT" class="border p-2 rounded text-black">
                                <input x-model="d.position" placeholder="DUTY / POSITION"
                                    class="border p-2 rounded text-black">
                                <input x-model="d.period" placeholder="INCLUSIVE PERIOD (From-To)"
                                    class="border p-2 rounded text-black">
                                <input x-model="d.authority" placeholder="AUTHORITY"
                                    class="border p-2 rounded text-black">

                            </div>
                        </template>
                    </div>

                    <!-- 1.A COLLATERAL (SHIPBOARD) -->
                    <div class="space-y-4">
                        <h3 class="font-bold text-blue-600 text-sm">1.a Collateral Duties (Shipboard)</h3>

                        <button type="button"
                            @click="duties.shipboard_collateral.push({unit:'', position:'', period:'', authority:''})"
                            class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">
                            + Add Collateral
                        </button>

                        <template x-for="(d, i) in duties.shipboard_collateral" :key="i">
                            <div class="grid md:grid-cols-4 gap-3 bg-white p-4 border rounded-lg">

                                <input x-model="d.unit" placeholder="UNIT" class="border p-2 rounded text-black">

                                <input x-model="d.position" placeholder="DUTY / POSITION"
                                    class="border p-2 rounded text-black">

                                <input x-model="d.period" placeholder="INCLUSIVE PERIOD (From-To)"
                                    class="border p-2 rounded text-black">

                                <input x-model="d.authority" placeholder="AUTHORITY"
                                    class="border p-2 rounded text-black">

                            </div>
                        </template>
                    </div>

                    <!-- ========================================================= -->
                    <!-- 2. SHORE BASED -->
                    <!-- ========================================================= -->
                    <div class="space-y-4 pt-4 border-t">
                        <h3 class="font-bold text-blue-600 text-sm">2. SHORE BASED</h3>

                        <button type="button"
                            @click="duties.shorebased.push({unit:'', position:'', period:'', authority:''})"
                            class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">
                            + Add Shore Based
                        </button>

                        <template x-for="(d, i) in duties.shorebased" :key="i">
                            <div class="grid md:grid-cols-4 gap-3 bg-white p-4 border rounded-lg">
                                <input x-model="d.unit" placeholder="UNIT" class="border p-2 rounded text-black">

                                <input x-model="d.position" placeholder="DUTY / POSITION"
                                    class="border p-2 rounded text-black">

                                <input x-model="d.period" placeholder="INCLUSIVE PERIOD (From-To)"
                                    class="border p-2 rounded text-black">

                                <input x-model="d.authority" placeholder="AUTHORITY"
                                    class="border p-2 rounded text-black">

                            </div>
                        </template>
                    </div>

                    <!-- 2.A COLLATERAL -->
                    <div class="space-y-4">
                        <h3 class="font-bold text-blue-600 text-sm">2.a Collateral Duties (Shore Based)</h3>

                        <button type="button"
                            @click="duties.shorebased_collateral.push({unit:'', position:'', period:'', authority:''})"
                            class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">
                            + Add Collateral
                        </button>

                        <template x-for="(d, i) in duties.shorebased_collateral" :key="i">
                            <div class="grid md:grid-cols-4 gap-3 bg-white p-4 border rounded-lg">
                                <input x-model="d.unit" placeholder="UNIT" class="border p-2 rounded text-black">

                                <input x-model="d.position" placeholder="DUTY / POSITION"
                                    class="border p-2 rounded text-black">

                                <input x-model="d.period" placeholder="INCLUSIVE PERIOD (From-To)"
                                    class="border p-2 rounded text-black">

                                <input x-model="d.authority" placeholder="AUTHORITY"
                                    class="border p-2 rounded text-black">

                            </div>
                        </template>
                    </div>

                    <!-- ========================================================= -->
                    <!-- 3. SPECIAL DUTIES -->
                    <!-- ========================================================= -->
                    <div class="space-y-4 pt-4 border-t">
                        <h3 class="font-bold text-blue-600 text-sm">3. SPECIAL DUTIES AND ASSIGNMENTS</h3>

                        <button type="button"
                            @click="duties.special.push({unit:'', position:'', period:'', authority:''})"
                            class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg">
                            + Add Special Duty
                        </button>

                        <template x-for="(d, i) in duties.special" :key="i">
                            <div class="grid md:grid-cols-4 gap-3 bg-white p-4 border rounded-lg">
                                <input x-model="d.unit" placeholder="UNIT" class="border p-2 rounded text-black">

                                <input x-model="d.position" placeholder="DUTY / POSITION"
                                    class="border p-2 rounded text-black">

                                <input x-model="d.period" placeholder="INCLUSIVE PERIOD (From-To)"
                                    class="border p-2 rounded text-black">

                                <input x-model="d.authority" placeholder="AUTHORITY"
                                    class="border p-2 rounded text-black">

                            </div>
                        </template>
                    </div>

                    <!-- ========================================================= -->
                    <!-- 4. OTHERS -->
                    <!-- ========================================================= -->
                    <div class="space-y-4 pt-4 border-t">

                        <h3 class="font-bold text-blue-600 text-sm">4. OTHERS</h3>

                        <!-- 4.A GOVERNMENT -->
                        <div>
                            <h4 class="text-sm font-semibold">4.a Other Government Service</h4>

                            <button type="button" @click="duties.govt.push({position:'', agency:'', period:''})"
                                class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg mt-2">
                                + Add Gov Service
                            </button>

                            <template x-for="(d, i) in duties.govt" :key="i">
                                <div class="grid md:grid-cols-3 gap-3 bg-white p-4 border rounded-lg mt-2">

                                    <input x-model="d.position" placeholder="POSITION/DESIGNATION"
                                        class="border p-2 rounded text-black">
                                    <input x-model="d.agency" placeholder="AGENCY"
                                        class="border p-2 rounded text-black">
                                    <input x-model="d.period" placeholder="INCLUSIVE PERIOD"
                                        class="border p-2 rounded text-black">

                                </div>
                            </template>
                        </div>

                        <!-- 4.B NON-GOVERNMENT -->
                        <div class="mt-4">
                            <h4 class="text-sm font-semibold">4.b Non-Government Service</h4>

                            <button type="button" @click="duties.non_govt.push({position:'', agency:'', period:''})"
                                class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg mt-2">
                                + Add Non-Gov Service
                            </button>

                            <template x-for="(d, i) in duties.non_govt" :key="i">
                                <div class="grid md:grid-cols-3 gap-3 bg-white p-4 border rounded-lg mt-2">

                                    <input x-model="d.position" placeholder="POSITION"
                                        class="border p-2 rounded text-black">

                                    <input x-model="d.agency" placeholder="AGENCY"
                                        class="border p-2 rounded text-black">

                                    <input x-model="d.period" placeholder="INCLUSIVE PERIOD (From-To)"
                                        class="border p-2 rounded text-black">

                                </div>
                            </template>
                        </div>

                    </div>

                </div>
            </div>

            <!-- ================= STEP 6 ================= -->
            <div x-show="step === 6" class="space-y-10">
                <div class="space-y-6">
                    <div class="border-b pb-2">
                        <h2 class="text-xl font-bold text-white-800">Step 6: H. Awards and Recognitions</h2>
                        <p class="text-sm text-gray-500 mt-1">Please categorize entries by service branch and type.</p>
                    </div>

                    <div class="space-y-6 p-6 bg-gray-50 rounded-2xl border border-gray-200">
                        <h3 class="flex items-center gap-2 font-bold text-blue-700 uppercase tracking-tight">
                            <span class="w-2 h-6 bg-blue-600 rounded-full"></span>
                            1. Coast Guard
                        </h3>

                        <div class="grid grid-cols-1 gap-8">
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <h4 class="text-sm font-semibold text-gray-700">a. Awards and Decorations</h4>
                                    <button type="button" @click="addCgDecoration()"
                                        class="text-xs text-blue-600 hover:underline font-bold">+ Add Award</button>
                                </div>
                                <template x-for="(a,i) in cgAwardsDecorations" :key="i">
                                    <div class="flex gap-2 items-start mb-2 animate-fadeIn">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
                                            <select x-model="a.type"
                                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black">

                                                <option value="">Select Award Type</option>

                                                <!-- ================= COAST GUARD ================= -->
                                                <optgroup label="Coast Guard Awards">
                                                    <option>Coast Guard Medal of Valor and Ribbon</option>
                                                    <option>Coast Guard Legion of Honor - LAKAN</option>
                                                    <option>Coast Guard Legion of Honor - DATU</option>
                                                    <option>Coast Guard Legion of Honor - MAGINOO</option>
                                                    <option>Coast Guard Distinguished Conduct Star</option>
                                                    <option>Coast Guard Distinguished Service Medal</option>
                                                    <option>Coast Guard Outstanding Achievement Medal</option>
                                                    <option>Coast Guard Superior Achievement Medal</option>
                                                    <option>Coast Guard Bronze Cross Medal</option>
                                                    <option>Coast Guard Search and Rescue Medal</option>
                                                    <option>Coast Guard Merit Medal</option>
                                                    <option>Coast Guard Commendation Medal</option>
                                                    <option>Coast Guard Wounded Personnel Medal</option>
                                                    <option>Coast Guard Civic Action Ribbon</option>
                                                    <option>Coast Guard Good Conduct Ribbon</option>
                                                    <option>Coast Guard Long Service Ribbon</option>
                                                </optgroup>

                                                <optgroup label="Coast Guard Unit Awards">
                                                    <option>Presidential Unit Citation</option>
                                                    <option>CPCG Unit Citation</option>
                                                    <option>Meritorious Unit Commendation</option>
                                                    <option>Meritorious Team Commendation</option>
                                                    <option>Efficiency Ribbon</option>
                                                </optgroup>

                                                <!-- ================= BUCOR ================= -->
                                                <optgroup label="BuCor - Heroism">
                                                    <option>Medal of Valor (MOV)</option>
                                                    <option>Distinguished Conduct Medal (DCM)</option>
                                                    <option>Gold Cross Medal (GCM)</option>
                                                    <option>Silver Cross Medal (SCM)</option>
                                                    <option>Bronze Cross Medal (BCM)</option>
                                                    <option>Gallantry Medal</option>
                                                    <option>Merit Medal</option>
                                                </optgroup>

                                                <optgroup label="BuCor - Service">
                                                    <option>Distinguished Service Star (DSS)</option>
                                                    <option>Distinguished Service Medal (DSM)</option>
                                                    <option>BuCor Commendation Medal</option>
                                                    <option>BuCor Good Conduct Medal</option>
                                                    <option>BuCor Service Medal</option>
                                                    <option>Special Service Medal</option>
                                                </optgroup>

                                                <!-- ================= AFP ================= -->
                                                <optgroup label="AFP Awards">
                                                    <option>Medal of Valor</option>
                                                    <option>Philippine Legion of Honor</option>
                                                    <option>Outstanding Achievement Medal</option>
                                                    <option>Distinguished Service Medal</option>
                                                    <option>Gold Cross</option>
                                                    <option>Silver Cross</option>
                                                    <option>Bronze Cross</option>
                                                    <option>Military Merit Medal</option>
                                                    <option>Military Commendation Medal</option>
                                                </optgroup>

                                                <!-- ================= PNP ================= -->
                                                <optgroup label="PNP Awards">

                                                    <option>Medalya ng Kagitingan (PNP Medal of Valor)</option>
                                                    <option>Medalya ng Kabayanihan (PNP Distinguished Conduct Medal)
                                                    </option>
                                                    <option>Medalya ng Katapatan sa Paglilingkod (PNP Distinguished
                                                        Service Medal)</option>
                                                    <option>Medalya ng Katapangan (PNP Bravery Medal)</option>
                                                    <option>Medalya ng Katangitanging Gawa (PNP Outstanding Achievement
                                                        Medal)</option>
                                                    <option>Medalya ng Pambihirang Paglilingkod (PNP Special Service
                                                        Medal)</option>
                                                    <option>Medalya ng Kadakilaan (PNP Heroism Medal)</option>
                                                    <option>Medalya ng Katangitanging Asal (PNP Outstanding Conduct
                                                        Medal)</option>

                                                    <option>Medalya ng Kagalingan (PNP Medal of Merit)</option>
                                                    <option>Medalya ng Kasanayan (PNP Efficiency Medal)</option>
                                                    <option>Medalya ng Papuri (PNP Commendation Medal)</option>
                                                    <option>Medalya ng Sugatang Magiting (PNP Wounded Personnel Medal)
                                                    </option>

                                                    <option>Medalya ng Ugnayang Pampulisya (PNP Police Relations Medal)
                                                    </option>
                                                    <option>Medalya ng Mabuting Asal (PNP Good Conduct Medal)</option>
                                                    <option>Medalya ng Paglilingkod (PNP Service Medal)</option>

                                                </optgroup>
                                                <optgroup label="PNP Unit Decorations">
                                                    <option>Presidential Unit Citation Badge</option>
                                                    <option>Martial Law Unit Citation</option>
                                                    <option>People Power I Unit Citation</option>
                                                    <option>People Power II Unit Citation</option>
                                                </optgroup>

                                                <optgroup label="PNP Campaign & Service Medals">
                                                    <option>PNP Anti-Dissidence Campaign Medal</option>
                                                    <option>PNP Disaster Relief and Rehabilitation Medal</option>
                                                    <option>PNP Luzon Campaign Medal</option>
                                                    <option>PNP Visayas Campaign Medal</option>
                                                    <option>PNP Mindanao Campaign Medal</option>
                                                </optgroup>
                                                <!-- ================= BJMP ================= -->
                                                <optgroup label="BJMP Awards">

                                                    <option>Medalya ng Kagitingan (BJMP Medal of Valor)</option>
                                                    <option>Medalya ng Kabayanihan (BJMP Distinguished Conduct Medal)
                                                    </option>
                                                    <option>Medalya ng Kagalingan (BJMP Medal of Merit)</option>
                                                    <option>Medalya ng Natatanging Gawa (BJMP Outstanding Achievement
                                                        Medal)</option>
                                                    <option>Medalya ng Kadakilaan (BJMP Gallantry Medal)</option>
                                                    <option>Medalya ng Sugatang Magiting (BJMP Wounded Personnel Medal)
                                                    </option>

                                                    <option>Medalya ng Katapatan sa Paglilingkod (BJMP Distinguished
                                                        Service Medal)</option>
                                                    <option>Medalya ng Katapatan (BJMP Loyalty Medal)</option>
                                                    <option>Medalya ng Kasanayan (BJMP Efficiency Medal)</option>
                                                    <option>Medalya ng Papuri (BJMP Commendation Medal)</option>
                                                    <option>Medalya ng Paglilingkod (BJMP Service Medal)</option>
                                                    <option>Medalya ng Pambihirang Paglilingkod (BJMP Special Service
                                                        Medal)</option>

                                                    <!-- Special Action Medal (with levels) -->
                                                    <option>Medalya ng Katangi-Tanging Asal - Raja</option>
                                                    <option>Medalya ng Katangi-Tanging Asal - Datu</option>
                                                    <option>Medalya ng Katangi-Tanging Asal - Lakan</option>
                                                    <option>Medalya ng Katangi-Tanging Asal - Maginoo</option>

                                                    <!-- Special Service Medal (with levels) -->
                                                    <option>BJMP Special Service Medal - Raja</option>
                                                    <option>BJMP Special Service Medal - Datu</option>
                                                    <option>BJMP Special Service Medal - Lakan</option>
                                                    <option>BJMP Special Service Medal - Maginoo</option>

                                                    <option>Medalya ng Ugnayang Pangkomunidad (BJMP Community Relations
                                                        Medal)</option>
                                                    <option>Disaster Relief Operation Ribbon</option>
                                                    <option>Medalya ng Mabuting Asal (BJMP Good Conduct Medal)</option>

                                                    <option>BJMP Luzon Service Medal</option>
                                                    <option>BJMP Visayas Service Medal</option>
                                                    <option>BJMP Mindanao Service Medal</option>

                                                    <option>Training Institute Ribbon</option>
                                                    <option>BJMP Academic Excellence Medal</option>
                                                    <option>BJMP Academic Excellence Ribbon</option>
                                                    <option>BJMP Training Excellence Ribbon</option>

                                                    <option>BJMP’s Best Medal</option>
                                                    <option>Letter of Commendation</option>

                                                </optgroup>

                                                <optgroup label="BJMP Promotions (Awards)">
                                                    <option>Meritorious Promotion</option>
                                                    <option>Special Promotion Based on Awards</option>
                                                    <option>On-the-Spot Promotion</option>
                                                    <option>Posthumous Promotion</option>
                                                </optgroup>

                                            </select>
                                            <input x-model="a.authority"
                                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                                placeholder="Authority">
                                        </div>
                                        <button type="button" @click="cgAwardsDecorations.splice(i, 1)"
                                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div class="space-y-3 border-t border-gray-200 pt-4">
                                <div class="flex justify-between items-center">
                                    <h4 class="text-sm font-semibold text-gray-700">b. Letters of Commendation</h4>
                                    <button type="button" @click="addCgLetter()"
                                        class="text-xs text-blue-600 hover:underline font-bold">+ Add Letter</button>
                                </div>
                                <template x-for="(a,i) in cgLetters" :key="i">
                                    <div class="flex gap-2 items-start mb-2">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
                                            <input x-model="a.type"
                                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                                placeholder="Letter Type">
                                            <input x-model="a.authority"
                                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                                placeholder="Authority">
                                        </div>
                                        <button type="button" @click="cgLetters.splice(i, 1)"
                                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div class="space-y-3 border-t border-gray-200 pt-4">
                                <div class="flex justify-between items-center">
                                    <h4 class="text-sm font-semibold text-gray-700">c. Appreciation</h4>
                                    <button type="button" @click="addCgAppreciation()"
                                        class="text-xs text-blue-600 hover:underline font-bold">+ Add
                                        Appreciation</button>
                                </div>

                                <template x-for="(a,i) in cgAppreciations" :key="i">
                                    <div class="flex gap-2 items-start mb-2">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
                                            <input x-model="a.type"
                                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                                placeholder="Appreciation Type">

                                            <input x-model="a.authority"
                                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                                placeholder="Authority">
                                        </div>

                                        <button type="button" @click="cgAppreciations.splice(i, 1)"
                                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div class="space-y-3 border-t border-gray-200 pt-4">
                                <div class="flex justify-between items-center">
                                    <h4 class="text-sm font-semibold text-gray-700">d. Plaque</h4>
                                    <button type="button" @click="addCgPlaque()"
                                        class="text-xs text-blue-600 hover:underline font-bold">+ Add Plaque</button>
                                </div>

                                <template x-for="(a,i) in cgPlaques" :key="i">
                                    <div class="flex gap-2 items-start mb-2">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
                                            <input x-model="a.type"
                                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                                placeholder="Plaque Type">

                                            <input x-model="a.authority"
                                                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                                placeholder="Authority">
                                        </div>

                                        <button type="button" @click="cgPlaques.splice(i, 1)"
                                            class="p-2 text-red-500 hover:bg-red-50 rounded-lg">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-6 p-6 bg-blue-50/50 rounded-2xl border border-blue-100">

                        <!-- HEADER -->
                        <h3 class="flex items-center gap-2 font-bold text-gray-700 uppercase tracking-tight">
                            <span class="w-2 h-6 bg-gray-400 rounded-full"></span>
                            2. Other Awards and Recognitions
                        </h3>

                        <div class="space-y-4">

                            <!-- LABEL + ADD BUTTON -->
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-gray-500 uppercase">
                                    Awards
                                </label>

                                <button type="button" @click="addMilitaryAward()"
                                    class="px-3 py-1 text-xs font-semibold text-blue-600 bg-blue-100 hover:bg-blue-200 rounded-lg transition">
                                    + Add Award
                                </button>
                            </div>

                            <!-- AWARDS LIST -->
                            <template x-for="(a,i) in militaryAwards" :key="i">
                                <div
                                    class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition space-y-3">

                                    <!-- ROW -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

                                        <!-- BRANCH -->
                                        <select x-model="a.branch" @change="a.category=''; a.type=''"
                                            class="w-full px-3 py-2 border rounded-lg text-black">

                                            <option value="">Branch</option>
                                            <option value="bucor">BuCor</option>
                                            <option value="afp">AFP</option>
                                            <option value="pnp">PNP</option>
                                            <option value="bjmp">BJMP</option>

                                        </select>

                                        <!-- CATEGORY -->
                                        <select x-model="a.category" @change="a.type=''"
                                            class="w-full px-3 py-2 border rounded-lg text-black">

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
                                        <!-- TYPE -->
                                        <select x-model="a.type"
                                            class="w-full px-3 py-2 border rounded-lg text-black">

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

                                    </div>

                                    <!-- REMOVE -->
                                    <div class="flex justify-end">
                                        <button type="button" @click="militaryAwards.splice(i, 1)"
                                            class="text-sm text-red-500 hover:text-red-700">
                                            ✕ Remove
                                        </button>
                                    </div>

                                </div>
                            </template>

                        </div>
                        <!-- OTHER LETTER OF COMMENDATION -->
                        <div class="space-y-3 border-t border-gray-200 pt-4">

                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-semibold text-gray-700">
                                    e. Other Letter of Commendation
                                </h4>

                                <button type="button" @click="otherLetters.push({ type:'', authority:'' })"
                                    class="text-xs text-blue-600 hover:underline font-bold">
                                    + Add Letter
                                </button>
                            </div>

                            <template x-for="(a,i) in otherLetters" :key="i">
                                <div class="flex gap-2 items-start mb-2">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">

                                        <input x-model="a.type"
                                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                            placeholder="Letter Type">

                                        <input x-model="a.authority"
                                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                            placeholder="Authority">

                                    </div>

                                    <button type="button" @click="otherLetters.splice(i, 1)"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg">

                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">

                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>

                                    </button>

                                </div>
                            </template>

                        </div>

                        <!-- OTHER APPRECIATION -->
                        <div class="space-y-3 border-t border-gray-200 pt-4">

                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-semibold text-gray-700">
                                    f. Other Appreciation
                                </h4>

                                <button type="button" @click="otherAppreciations.push({ type:'', authority:'' })"
                                    class="text-xs text-blue-600 hover:underline font-bold">
                                    + Add Appreciation
                                </button>
                            </div>

                            <template x-for="(a,i) in otherAppreciations" :key="i">
                                <div class="flex gap-2 items-start mb-2">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">

                                        <input x-model="a.type"
                                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                            placeholder="Appreciation Type">

                                        <input x-model="a.authority"
                                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                            placeholder="Authority">

                                    </div>

                                    <button type="button" @click="otherAppreciations.splice(i, 1)"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg">

                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">

                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>

                                    </button>

                                </div>
                            </template>

                        </div>

                        <!-- OTHER PLAQUE -->
                        <div class="space-y-3 border-t border-gray-200 pt-4">

                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-semibold text-gray-700">
                                    g. Other Plaque
                                </h4>

                                <button type="button" @click="otherPlaques.push({ type:'', authority:'' })"
                                    class="text-xs text-blue-600 hover:underline font-bold">
                                    + Add Plaque
                                </button>
                            </div>

                            <template x-for="(a,i) in otherPlaques" :key="i">
                                <div class="flex gap-2 items-start mb-2">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">

                                        <input x-model="a.type"
                                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                            placeholder="Plaque Type">

                                        <input x-model="a.authority"
                                            class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                            placeholder="Authority">

                                    </div>

                                    <button type="button" @click="otherPlaques.splice(i, 1)"
                                        class="p-2 text-red-500 hover:bg-red-50 rounded-lg">

                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">

                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>

                                    </button>

                                </div>
                            </template>

                        </div>
                    </div>
                </div>
                <!-- AWARDS LIST -->
                <div class="space-y-6 border-t pt-8 bg-white rounded-lg p-6 shadow-sm">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-800 italic">I. Career Management Profile</h2>

                        <button type="button" @click="addCareer()"
                            class="px-3 py-1 bg-gray-800 text-white text-xs rounded-lg hover:bg-black transition">+
                            Add
                            Career Row</button>
                    </div>
                    <div class="space-y-3 border-t border-gray-200 pt-4">

                        <div class="flex justify-between items-center">
                            <h4 class="text-sm font-semibold text-gray-700">
                                3.a Civilian (Commendations/Appreciations/Plaque)
                            </h4>

                            <button type="button" @click="civilianCommendations.push({ type:'', authority:'' })"
                                class="text-xs text-blue-600 hover:underline font-bold">
                                + Add Commendation
                            </button>
                        </div>

                        <template x-for="(a,i) in civilianCommendations" :key="i">
                            <div class="flex gap-2 items-start mb-2">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">

                                    <input x-model="a.type"
                                        class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                        placeholder="Commendations/Appreciations/Plaque Type">

                                    <input x-model="a.authority"
                                        class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm text-black"
                                        placeholder="Authority">

                                </div>

                                <button type="button" @click="civilianCommendations.splice(i, 1)"
                                    class="p-2 text-red-500 hover:bg-red-50 rounded-lg">
                                    ✕
                                </button>

                            </div>
                        </template>

                    </div>
                    <div class="grid grid-cols-1 gap-4">

                        <template x-for="(c,i) in careers" :key="i">
                            <div
                                class="flex gap-2 items-center p-4 bg-gray-50 rounded-xl border border-dashed border-gray-300 relative">

                                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 flex-1">

                                    <!-- Student Tour -->
                                    <div class="relative">
                                        <label
                                            class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1">
                                            Student Tour
                                        </label>
                                        <input x-model="c.student"
                                            class="w-full px-3 pt-5 pb-2 bg-transparent text-sm text-black outline-none"
                                            placeholder="Yrs/Mos/Days">
                                    </div>

                                    <!-- Sea Duty -->
                                    <div class="relative">
                                        <label
                                            class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1">
                                            Sea Duty
                                        </label>
                                        <input x-model="c.sea"
                                            class="w-full px-3 pt-5 pb-2 bg-transparent text-sm text-black outline-none"
                                            placeholder="Yrs/Mos/Days">
                                    </div>

                                    <!-- Staff Duty -->
                                    <div class="relative">
                                        <label
                                            class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1">
                                            Staff Duty
                                        </label>
                                        <input x-model="c.staff"
                                            class="w-full px-3 pt-5 pb-2 bg-transparent text-sm text-black outline-none"
                                            placeholder="Yrs/Mos/Days">
                                    </div>

                                    <!-- Instructor Duty -->
                                    <div class="relative">
                                        <label
                                            class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1">
                                            Instructor Duty
                                        </label>
                                        <input x-model="c.instructor"
                                            class="w-full px-3 pt-5 pb-2 bg-transparent text-sm text-black outline-none"
                                            placeholder="Yrs/Mos/Days">
                                    </div>

                                    <!-- Command Duty -->
                                    <div class="relative">
                                        <label
                                            class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1">
                                            Command (Special / Major Unit)
                                        </label>
                                        <input x-model="c.command"
                                            class="w-full px-3 pt-5 pb-2 bg-transparent text-sm text-black outline-none"
                                            placeholder="Yrs/Mos/Days">
                                    </div>
                                    <div class="relative">
                                        <label
                                            class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1">
                                            Organization Affiliation
                                        </label>

                                        <input type="text" x-model="c.organization_affiliation"
                                            class="w-full px-3 pt-5 pb-2 bg-transparent text-sm text-black outline-none"
                                            placeholder="N/A">
                                    </div>
                                    <div class="relative">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1">
                                            Civil Service Eligibility / Licensure
                                        </label>

                                        <input type="text" x-model="c.civil_service_eligibility"
                                            class="w-full px-3 pt-5 pb-2 bg-transparent text-sm text-black outline-none"
                                            placeholder="This text is Sample. Delete this.">
                                    </div>
                                    <div class="relative">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase absolute left-3 top-1">
                                            Date of Compulsory Retirement
                                        </label>

                                        <input type="text" x-model="c.compulsory_retirement"
                                            class="w-full px-3 pt-5 pb-2 bg-transparent text-sm text-black outline-none"
                                            placeholder="DD Month YYYY (Birth year + 56)">
                                    </div>

                                </div>

                                <!-- REMOVE -->
                                <button type="button" x-show="careers.length > 1" @click="careers.splice(i, 1)"
                                    class="text-red-500 hover:bg-red-100 p-2 rounded-full">
                                    ✕
                                </button>

                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <!-- NAVIGATION -->
            <div class="flex items-center justify-between mt-12 pt-6 border-t border-gray-200">

                <div>
                    <button type="button" x-show="step > 1" @click="step--"
                        class="flex items-center gap-2 px-6 py-2.5 text-sm font-bold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 hover:text-gray-800 transition-all duration-200 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>
                </div>

                <div class="hidden md:flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-gray-400">
                        Progress:
                    </span>

                    <template x-for="i in 6" :key="i">
                        <button type="button" @click="step = i"
                            class="w-8 h-8 rounded-full text-xs font-bold transition"
                            :class="step === i ?
                                'bg-blue-600 text-white' :
                                'bg-gray-200 text-gray-600 hover:bg-blue-100'"
                            x-text="i"></button>
                    </template>
                </div>

                <div class="flex gap-2">

                    <!-- Step 1 Next -->
                    <button type="button" x-show="step === 1" @click="saveStep1()"
                        class="flex items-center gap-2 px-8 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 shadow-md">
                        Next Step
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <!-- Step 2 Next -->
                    <button type="button" x-show="step === 2" @click="saveStep2()"
                        class="flex items-center gap-2 px-8 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 shadow-md">
                        Next Step
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <!-- Step 3 Next -->
                    <button type="button" x-show="step === 3" @click="saveStep3()"
                        class="flex items-center gap-2 px-8 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 shadow-md">
                        Next Step
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <!-- Step 4 Next -->
                    <button type="button" x-show="step === 4" @click="saveStep4()"
                        class="flex items-center gap-2 px-8 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 shadow-md">
                        Next Step
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <!-- Step 5 Next -->
                    <button type="button" x-show="step === 5" @click="saveStep5()"
                        class="flex items-center gap-2 px-8 py-2.5 bg-green-600 text-white text-sm font-bold rounded-xl hover:bg-green-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 shadow-md">

                        Next Step

                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5l7 7-7 7" />
                        </svg>
                    </button>

                    <!-- Step 6 Final Submit -->
                    <button type="submit" x-show="step === 6" @click="saveStep6()"
                        class="flex items-center gap-2 px-8 py-2.5 bg-green-600 text-white text-sm font-bold rounded-xl hover:bg-green-700 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 shadow-md">

                        Submit Application

                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">

                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />

                        </svg>
                    </button>

                </div>
            </div>
        </form>
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
                    async saveStep1() {
                        try {
                            console.log('Sending Step 1 data: ', this.basic);
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
                        }
                    },

                    // ================= STEP 2 TEMP SAVE =================
                    async saveStep2() {
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

                            console.log('Sending Step 2 data: ', step2Data);

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
                        }
                    },

                    // ================= STEP 3 TEMP SAVE =================
                    async saveStep3() {
                        try {
                            // Collect all dependent data
                            let step3Data = {
                                dependents: this.dependents // x-model is already bound to `this.dependents`
                            };

                            console.log('Sending Step 3 data:', step3Data);

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
                        }
                    },
                    async saveStep4() {
                        try {
                            let step4Data = {
                                academic: this.academic,
                                masteral: this.masteral,
                                secondary: this.secondary,
                                primary: this.primary,
                                vocational: this.vocational,
                                trainings: this.trainings
                            };

                            console.log('Sending Step 4 data:', step4Data);

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
                        }
                    },
                    // ================= ADD METHODS =================
                    async saveStep5() {
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

                            console.log('Sending Step 5 data:', step5Data);

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
                        }
                    },
                    // STEP 6 SAVE
                    async saveStep6() {

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

                            console.log('Sending Step 6 data:', step6Data);

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

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Step 6 Saved!',
                                        text: data.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.href = '/dashboard';
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
    </div>


</body>

</html>
