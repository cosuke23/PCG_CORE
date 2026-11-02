@extends('layouts.app')

@section('title', 'Profile Setup')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @if (session('success'))
        <script>
            Swal.fire({
                title: 'Success!',
                text: '{{ session('success') }}',
                icon: 'success',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK'
            });
        </script>
    @endif
    <div x-data="{ step: 1 }"
        class="relative min-h-screen bg-gradient-to-b from-blue-900 via-blue-800 to-blue-700 text-white px-6 py-16 sm:p-10">
        <div class="bg-white/10 backdrop-blur-md p-8 rounded-3xl border border-white/20 max-w-4xl mx-auto shadow-2xl">

            <!-- Title -->
            <h2 class="text-3xl font-bold mb-6 text-center">Complete Your Profile</h2>

            <!-- Step Indicators -->
            <!-- Step Indicators (Clickable) -->
            <div class="flex justify-center mb-8 space-x-4">
                <template x-for="i in [1,2,3]">
                    <div @click="step = i"
                        :class="step === i ? 'bg-blue-500 text-white scale-110 shadow-lg' :
                            'bg-white/20 text-gray-300 hover:bg-white/30 cursor-pointer'"
                        class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-all duration-200"
                        x-text="i"></div>
                </template>
            </div>


            <!-- Step Forms -->
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-8" enctype="multipart/form-data">
                @csrf

                <div x-show="step === 1" x-transition>
                    <h3 class="text-xl font-semibold mb-4 text-center">Personal Information</h3>

                    <div class="flex justify-center mb-6">
                        <div class="relative">
                            <label for="photo" class="cursor-pointer flex flex-col items-center">
                                <img id="photoPreview" src="{{ asset('images/default-2x2.png') }}" alt="2x2 Photo"
                                    class="w-24 h-24 rounded-lg object-cover border-2 border-white/30">
                                <span class="text-xs text-gray-300 mt-2">Upload 2x2 Photo</span>
                            </label>
                            <input type="file" id="photo" name="photo" accept="image/*" class="hidden"
                                onchange="previewPhoto(event)">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1 text-black">District</label>
                        <select name="district" required class="w-full px-4 py-2 border rounded text-black bg-white">
                            <option value="" disabled selected class="text-gray-500">
                                Select HRMU
                            </option>

                            <option value="HRMU-NCRCL" class="text-black">HRMU-NCRCL</option>
                            <option value="HRMU-NM" class="text-black">HRMU-NM</option>
                            <option value="HRMU-NELZN" class="text-black">HRMU-NELZN</option>
                            <option value="HRMU-NWLZN" class="text-black">HRMU-NWLZN</option>
                            <option value="HRMU-STL" class="text-black">HRMU-STL</option>
                            <option value="HRMU-BICOL" class="text-black">HRMU-BICOL</option>
                            <option value="HRMU-PAL" class="text-black">HRMU-PAL</option>
                            <option value="HRMU-WV" class="text-black">HRMU-WV</option>
                            <option value="HRMU-EV" class="text-black">HRMU-EV</option>
                            <option value="HRMU-CV" class="text-black">HRMU-CV</option>
                            <option value="HRMU-SM" class="text-black">HRMU-SM</option>
                            <option value="HRMU-NEM" class="text-black">HRMU-NEM</option>
                            <option value="HRMU-SEM" class="text-black">HRMU-SEM</option>
                            <option value="HRMU-SWM" class="text-black">HRMU-SWM</option>
                            <option value="HRMU-BARMM" class="text-black">HRMU-BARMM</option>
                            <option value="HRMU-SV" class="text-black">HRMU-SV</option>
                        </select>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-200 mb-1">First Name</label>
                                <input type="text" name="first_name"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="Enter first name" required>
                            </div>
                            <div>
                                <label class="block text-gray-200 mb-1">Middle Name</label>
                                <input type="text" name="middle_name"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="Enter middle name">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-200 mb-1">Last Name</label>
                                <input type="text" name="last_name"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="Enter last name" required>
                            </div>
                            <div>
                                <label class="block text-gray-200 mb-1">Extension (e.g., Jr., III)</label>
                                <input type="text" name="extension_name"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="Optional">
                            </div>
                        </div>

                        <!-- Hidden Email Field -->
                        <input type="hidden" name="email" value="{{ $user->email ?? '' }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Birthday + Age -->
                            <div>
                                <label class="block text-gray-200 mb-1">Birthday</label>
                                <input type="date" name="birthday" id="birthday"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    onchange="calculateAge()">
                                <p id="ageDisplay" class="text-sm text-gray-300 mt-1"></p>
                            </div>

                            <!-- Gender (Radio Buttons) -->
                            <div>
                                <label class="block text-gray-200 mb-1">Sex</label>
                                <div class="flex items-center space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="sex" value="Male"
                                            class="form-radio text-blue-500 focus:ring-0">
                                        <span class="ml-2 text-gray-200">Male</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="sex" value="Female"
                                            class="form-radio text-pink-500 focus:ring-0">
                                        <span class="ml-2 text-gray-200">Female</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 text-right">
                        <button type="button" @click="step = 2"
                            class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg font-semibold transition">Next
                            →</button>
                    </div>
                </div>

                <!-- Scripts -->
                <script>
                    function previewPhoto(event) {
                        const reader = new FileReader();
                        reader.onload = function() {
                            document.getElementById('photoPreview').src = reader.result;
                        }
                        reader.readAsDataURL(event.target.files[0]);
                    }

                    function calculateAge() {
                        const birthday = document.getElementById('birthday').value;
                        if (!birthday) {
                            document.getElementById('ageDisplay').textContent = '';
                            return;
                        }
                        const birthDate = new Date(birthday);
                        const today = new Date();
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const m = today.getMonth() - birthDate.getMonth();
                        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        document.getElementById('ageDisplay').textContent = `Age: ${age} years old`;
                    }
                </script>

                <!-- ✅ STEP 2: Address -->
                <div x-show="step === 2" x-transition>
                    <h3 class="text-xl font-semibold mb-4 text-center">Address Information</h3>
                    <div class="text-center my-6">
                        <hr class="border-black-300 my-2" />
                        <p class="text-lg text-blue font-semibold text-blue-700 mt-4">PERMANENT ADDRESS (where you
                                are
                                officially residing)</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mx-auto">
                        <div>
                            <label for="region" class="text-blue  block text-sm font-medium text-white-700">Region
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="region" id="region"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"></select>
                            <input type="hidden" name="region" id="region-text" required>
                            <input type="text" name="email" value="{{ old('email', Auth::user()->email) }}"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500 capitalize hidden" />
                        </div>
                        <div>
                            <label for="province" class=" block text-sm font-medium text-white-700">Province
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="province" id="province"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"></select>
                            <input type="hidden" name="province" id="province-text" required>
                        </div>
                        <div>
                            <label for="city" class="text-blue  block text-sm font-medium text-white-700">City
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="city" id="city"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"></select>
                            <input type="hidden" name="city" id="city-text" required>
                        </div>
                        <div>
                            <label for="barangay" class="block text-sm font-medium text-white-700">Barangay
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="barangay" id="barangay"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"></select>
                            <input type="hidden" name="barangay" id="barangay-text" required>
                        </div>
                        <div>
                            <label for="address" class="text-blue  block text-sm font-medium text-white-700">Bldg.
                                & Street
                                No. <span class="text-red-500">*</span>
                            </label>
                            <input id="address" placeholder="Bldg. & Street No." type="text"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-black-500"
                                name="address" value="{{ old('address') }}" required>
                        </div>
                        <div>
                            <label for="residency" class=" block text-sm font-medium text-white-700">Years of
                                Residency <span class="text-red-500">*</span>
                            </label>
                            <input id="residency" placeholder="Years of Residency" type="number" max="36"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"
                                name="residency" value="{{ old('residency') }}" required>
                        </div>
                    </div>

                    <!-- Present Address (Centered) -->
                    <div class="text-center my-6">
                        <hr class="border-black-300 my-2" />
                        <p class="text-lg text-blue font-semibold text-white-700 mt-4">PRESENT ADDRESS (where you currently
                            reside)</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mx-auto">
                        <div>
                            <label for="region1" class="text-blue  block text-sm font-medium text-white-700">Region
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="region1" id="region1"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"></select>
                            <input type="hidden" name="region1" id="region-text1" required>
                        </div>
                        <div>
                            <label for="province1" class="text-blue  block text-sm font-medium text-white-700">Province
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="province1" id="province1"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"></select>
                            <input type="hidden" name="province1" id="province-text1" required>
                        </div>
                        <div>
                            <label for="city1" class="text-blue  block text-sm font-medium text-white-700">City
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="city1" id="city1"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"></select>
                            <input type="hidden" name="city1" id="city-text1" required>
                        </div>
                        <div>
                            <label for="barangay1" class="text-blue  block text-sm font-medium text-white-700">Barangay
                                <span class="text-red-500">*</span>
                            </label>
                            <select name="barangay1" id="barangay1"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"></select>
                            <input type="hidden" name="barangay1" id="barangay-text1" required>
                        </div>
                        <div>
                            <label for="address1" class="text-blue  block text-sm font-medium text-white-700">Street
                                Address <span class="text-red-500">*</span>
                            </label>
                            <input id="address1" placeholder="Bldg. & Street No." type="text"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"
                                name="address1" value="{{ old('address1') }}" required>
                        </div>
                        <div>
                            <label for="residency1" class="text-blue  block text-sm font-medium text-white-700">Years
                                of
                                Residency <span class="text-red-500">*</span>
                            </label>
                            <input id="residency1" placeholder="Years of Residency" max="30" type="number"
                                class="text-black bg-white mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring focus:ring-blue-500"
                                name="residency1" value="{{ old('residency1') }}" required>
                        </div>
                    </div>


                    <div class="mt-6 flex justify-between">
                        <button type="button" @click="step = 1"
                            class="bg-gray-500 hover:bg-gray-600 px-6 py-2 rounded-lg font-semibold transition">←
                            Back</button>

                        <button type="button" @click="step = 3"
                            class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg font-semibold transition">Next
                            →</button>
                    </div>
                </div>


                <!-- ✅ STEP 3: Educational Background -->
                <div x-show="step === 3" x-transition x-data="{ highestLevel: '' }">
                    <h3 class="text-xl font-semibold mb-4 text-center">Educational Background</h3>

                    <!-- 🎓 Highest Educational Attainment -->
                    <div class="mb-6">
                        <label class="block text-gray-200 mb-1">Highest Educational Attainment</label>
                        <select x-model="highestLevel" name="education_level"
                            class="w-full rounded-lg p-3 bg-black/70 border border-white/20 text-white focus:ring focus:ring-blue-500/40 focus:outline-none">
                            <option value="">Select</option>
                            <option value="Elementary">Elementary</option>
                            <option value="Secondary">Secondary</option>
                            <option value="Tertiary">Tertiary</option>
                        </select>
                    </div>

                    <!-- 🎓 Tertiary Education -->
                    <div x-show="highestLevel === 'Tertiary'" x-transition>
                        <h4 class="text-lg font-semibold text-gray-100 mb-2">Tertiary Education</h4>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-200 mb-1">Course / Major</label>
                                <input type="text" name="tertiary_course"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="e.g., BS in Information Technology">
                            </div>

                            <div>
                                <label class="block text-gray-200 mb-1">School / University</label>
                                <input type="text" name="tertiary_school"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="Name of University / College">
                            </div>

                            <div>
                                <label class="block text-gray-200 mb-1">Year Graduated</label>
                                <input type="text" name="tertiary_year_graduated"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="YYYY">
                            </div>
                        </div>
                    </div>

                    <!-- 🏫 Secondary Education -->
                    <div x-show="highestLevel === 'Secondary' || highestLevel === 'Tertiary'" x-transition>
                        <h4 class="text-lg font-semibold text-gray-100 mb-2 mt-6">Secondary Education</h4>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-200 mb-1">School Name</label>
                                <input type="text" name="secondary_school"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="Name of High School / Senior High">
                            </div>

                            <div>
                                <label class="block text-gray-200 mb-1">Track / Strand</label>
                                <input type="text" name="secondary_strand"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="e.g., STEM, HUMSS, GAS, TVL">
                            </div>

                            <div>
                                <label class="block text-gray-200 mb-1">Year Graduated</label>
                                <input type="text" name="secondary_year_graduated"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="YYYY">
                            </div>
                        </div>
                    </div>

                    <!-- 🏫 Elementary Education -->
                    <div x-show="highestLevel === 'Elementary' || highestLevel === 'Secondary' || highestLevel === 'Tertiary'"
                        x-transition>
                        <h4 class="text-lg font-semibold text-gray-100 mb-2 mt-6">Elementary Education</h4>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-200 mb-1">School Name</label>
                                <input type="text" name="elementary_school"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="Name of Elementary School">
                            </div>

                            <div>
                                <label class="block text-gray-200 mb-1">Year Graduated</label>
                                <input type="text" name="elementary_year_graduated"
                                    class="w-full rounded-lg p-3 bg-white/10 border border-white/20 text-white"
                                    placeholder="YYYY">
                            </div>
                        </div>
                    </div>

                    <!-- ✅ Navigation Buttons -->
                    <div class="mt-6 flex justify-between">
                        <button type="button" @click="step = 2"
                            class="bg-gray-500 hover:bg-gray-600 px-6 py-2 rounded-lg font-semibold transition">
                            ← Back
                        </button>

                        <button type="submit"
                            class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded-lg font-semibold transition">
                            Submit
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    /**
     * __________________________________________________________________
     *
     * Phillipine Address Selector
     * __________________________________________________________________
     *
     * MIT License
     *
     * Copyright (c) 2020 Wilfred V. Pine
     *
     * Permission is hereby granted, free of charge, to any person obtaining a copy
     * of this software and associated documentation files (the "Software"), to deal
     * in the Software without restriction, including without limitation the rights
     * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
     * copies of the Software, and to permit persons to whom the Software is
     * furnished to do so, subject to the following conditions:
     *
     * The above copyright notice and this permission notice shall be included in
     * all copies or substantial portions of the Software.
     *
     * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
     * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
     * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
     * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
     * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
     * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
     * THE SOFTWARE.
     *
     * @package Phillipine Address Selector
     * @author Wilfred V. Pine <only.master.red@gmail.com>
     * @copyright Copyright 2020 (https://dev.confired.com)
     * @link https://github.com/redmalmon/philippine-address-selector
     * @license https://opensource.org/licenses/MIT MIT License
     */

    var my_handlers = {
        // fill province
        fill_provinces: function() {
            //selected region
            var region_code = $(this).val();

            // set selected text to input
            var region_text = $(this).find("option:selected").text();
            let region_input = $('#region-text');
            region_input.val(region_text);
            //clear province & city & barangay input
            $('#province-text').val('');
            $('#city-text').val('');
            $('#barangay-text').val('');

            //province
            let dropdown = $('#province');
            dropdown.empty();
            dropdown.append('<option selected="true" disabled>Choose State/Province</option>');
            dropdown.prop('selectedIndex', 0);

            //city
            let city = $('#city');
            city.empty();
            city.append('<option selected="true" disabled></option>');
            city.prop('selectedIndex', 0);

            //barangay
            let barangay = $('#barangay');
            barangay.empty();
            barangay.append('<option selected="true" disabled></option>');
            barangay.prop('selectedIndex', 0);

            // filter & fill
            const url = "{{ asset('ph_json/province.json') }}";
            $.getJSON(url, function(data) {
                var result = data.filter(function(value) {
                    return value.region_code == region_code;
                });

                result.sort(function(a, b) {
                    return a.province_name.localeCompare(b.province_name);
                });

                $.each(result, function(key, entry) {
                    dropdown.append($('<option></option>').attr('value', entry.province_code)
                        .text(entry.province_name));
                })

            });
        },
        // fill city
        fill_cities: function() {
            //selected province
            var province_code = $(this).val();

            // set selected text to input
            var province_text = $(this).find("option:selected").text();
            let province_input = $('#province-text');
            province_input.val(province_text);
            //clear city & barangay input
            $('#city-text').val('');
            $('#barangay-text').val('');

            //city
            let dropdown = $('#city');
            dropdown.empty();
            dropdown.append('<option selected="true" disabled>Choose City/Municipality</option>');
            dropdown.prop('selectedIndex', 0);

            //barangay
            let barangay = $('#barangay');
            barangay.empty();
            barangay.append('<option selected="true" disabled></option>');
            barangay.prop('selectedIndex', 0);

            // filter & fill

            const url = "{{ asset('ph_json/city.json') }}";
            $.getJSON(url, function(data) {
                var result = data.filter(function(value) {
                    return value.province_code == province_code;
                });

                result.sort(function(a, b) {
                    return a.city_name.localeCompare(b.city_name);
                });

                $.each(result, function(key, entry) {
                    dropdown.append($('<option></option>').attr('value', entry.city_code).text(
                        entry.city_name));
                })

            });
        },
        // fill barangay
        fill_barangays: function() {
            // selected barangay
            var city_code = $(this).val();

            // set selected text to input
            var city_text = $(this).find("option:selected").text();
            let city_input = $('#city-text');
            city_input.val(city_text);
            //clear barangay input
            $('#barangay-text').val('');

            // barangay
            let dropdown = $('#barangay');
            dropdown.empty();
            dropdown.append('<option selected="true" disabled>Choose Barangay</option>');
            dropdown.prop('selectedIndex', 0);

            // filter & Fill
            const url = "{{ asset('ph_json/barangay.json') }}";

            $.getJSON(url, function(data) {
                var result = data.filter(function(value) {
                    return value.city_code == city_code;
                });

                result.sort(function(a, b) {
                    return a.brgy_name.localeCompare(b.brgy_name);
                });

                $.each(result, function(key, entry) {
                    dropdown.append($('<option></option>').attr('value', entry.brgy_code).text(
                        entry.brgy_name));
                })

            });
        },

        onchange_barangay: function() {
            // set selected text to input
            var barangay_text = $(this).find("option:selected").text();
            let barangay_input = $('#barangay-text');
            barangay_input.val(barangay_text);
        },

    };


    $(function() {
        // events
        $('#region').on('change', my_handlers.fill_provinces);
        $('#province').on('change', my_handlers.fill_cities);
        $('#city').on('change', my_handlers.fill_barangays);
        $('#barangay').on('change', my_handlers.onchange_barangay);

        // load region
        let dropdown = $('#region');
        dropdown.empty();
        dropdown.append('<option selected="true" disabled>Choose Region</option>');
        dropdown.prop('selectedIndex', 0);
        const url = "{{ asset('ph_json/region.json') }}";
        // Populate dropdown with list of regions
        $.getJSON(url, function(data) {
            $.each(data, function(key, entry) {
                dropdown.append($('<option></option>').attr('value', entry.region_code).text(
                    entry.region_name));
            })
        });

    });
</script>
<script>
    /**
     * @package Phillipine Address Selector
     * @author Wilfred V. Pine <only.master.red@gmail.com>
     * @copyright Copyright 2020 (https://dev.confired.com)
     * @link https://github.com/redmalmon/philippine-address-selector
     * @license https://opensource.org/licenses/MIT MIT License
     */

    var my_handlers1 = {
        // fill province
        fill_provinces1: function() {
            //selected region
            var region_code = $(this).val();

            // set selected text to input
            var region_text1 = $(this).find("option:selected").text();
            let region_input1 = $('#region-text1');
            region_input1.val(region_text1);
            //clear province & city1 & barangay input
            $('#province-text1').val('');
            $('#city-text1').val('');
            $('#barangay-text1').val('');

            //province
            let dropdown1 = $('#province1');
            dropdown1.empty();
            dropdown1.append('<option selected="true" disabled>Choose State/Province</option>');
            dropdown1.prop('selectedIndex1', 0);

            //city
            let city1 = $('#city1');
            city1.empty();
            city1.append('<option selected="true" disabled></option>');
            city1.prop('selectedIndex1', 0);

            //barangay
            let barangay1 = $('#barangay1');
            barangay1.empty();
            barangay1.append('<option selected="true" disabled></option>');
            barangay1.prop('selectedIndex1', 0);

            // filter & fill

            const url1 = "{{ asset('ph_json/province.json') }}";
            $.getJSON(url1, function(data) {
                var result1 = data.filter(function(value) {
                    return value.region_code == region_code;
                });

                result1.sort(function(a, b) {
                    return a.province_name.localeCompare(b.province_name);
                });

                $.each(result1, function(key, entry) {
                    dropdown1.append($('<option></option>').attr('value', entry.province_code)
                        .text(entry.province_name));
                })

            });
        },
        // fill city1
        fill_cities1: function() {
            //selected province
            var province_code = $(this).val();

            // set selected text to input
            var province_text1 = $(this).find("option:selected").text();
            let province_input1 = $('#province-text1');
            province_input1.val(province_text1);
            //clear city1 & barangay input
            $('#city-text1').val('');
            $('#barangay-text1').val('');

            //city
            let dropdown1 = $('#city1');
            dropdown1.empty();
            dropdown1.append('<option selected="true" disabled>Choose City/Municipality</option>');
            dropdown1.prop('selectedIndex1', 0);

            //barangay
            let barangay1 = $('#barangay1');
            barangay1.empty();
            barangay1.append('<option selected="true" disabled></option>');
            barangay1.prop('selectedIndex1', 0);

            // filter & fill

            const url1 = "{{ asset('ph_json/city.json') }}";

            $.getJSON(url1, function(data) {
                var result1 = data.filter(function(value) {
                    return value.province_code == province_code;
                });

                result1.sort(function(a, b) {
                    return a.city_name.localeCompare(b.city_name);
                });

                $.each(result1, function(key, entry) {
                    dropdown1.append($('<option></option>').attr('value', entry.city_code).text(
                        entry.city_name));
                })

            });
        },
        // fill barangay
        fill_barangays1: function() {
            // selected barangay
            var city_code = $(this).val();

            // set selected text to input
            var city_text1 = $(this).find("option:selected").text();
            let city_input1 = $('#city-text1');
            city_input1.val(city_text1);
            //clear barangay input
            $('#barangay-text1').val('');

            // barangay
            let dropdown1 = $('#barangay1');
            dropdown1.empty();
            dropdown1.append('<option selected="true" disabled>Choose Barangay</option>');
            dropdown1.prop('selectedIndex1', 0);

            // filter & Fill
            const url1 = "{{ asset('ph_json/barangay.json') }}";

            $.getJSON(url1, function(data) {
                var result1 = data.filter(function(value) {
                    return value.city_code == city_code;
                });

                result1.sort(function(a, b) {
                    return a.brgy_name.localeCompare(b.brgy_name);
                });

                $.each(result1, function(key, entry) {
                    dropdown1.append($('<option></option>').attr('value', entry.brgy_code).text(
                        entry.brgy_name));
                })

            });
        },

        onchange_barangay1: function() {
            // set selected text to input
            var barangay_text1 = $(this).find("option:selected").text();
            let barangay_input1 = $('#barangay-text1');
            barangay_input1.val(barangay_text1);
        },

    };


    $(function() {
        // events
        $('#region1').on('change', my_handlers1.fill_provinces1);
        $('#province1').on('change', my_handlers1.fill_cities1);
        $('#city1').on('change', my_handlers1.fill_barangays1);
        $('#barangay1').on('change', my_handlers1.onchange_barangay1);

        // load region
        let dropdown1 = $('#region1');
        dropdown1.empty();
        dropdown1.append('<option selected="true" disabled>Choose Region</option>');
        dropdown1.prop('selectedIndex1', 0);
        const url1 = "{{ asset('ph_json/region.json') }}";
        // Populate dropdown1 with list of regions
        $.getJSON(url1, function(data) {
            $.each(data, function(key, entry) {
                dropdown1.append($('<option></option>').attr('value', entry.region_code).text(
                    entry.region_name));
            })
        });

    });
</script>
