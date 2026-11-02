@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @php $isOfficer = $type === 'officer'; @endphp
    <div x-data="{ open: true }" x-show="open"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-50">

        <!-- Modal Container -->
        <div class="bg-white rounded-3xl shadow-2xl relative overflow-hidden inline-block z-[10000]">

            <!-- Close Button Top Right -->
            <button @click="open = false"
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-800 text-2xl font-bold z-20">
                &times;
            </button>

            <!-- Modal Content (Image) -->
            <img src="{{ asset('backgrounds/MEPCOM 6.jpg') }}" alt="RADM SUPANGAN PCG Message"
                class="block max-w-full max-h-[90vh] object-contain rounded-3xl shadow-inner">
        </div>
    </div>
    <div
        class="min-h-screen flex items-center justify-center p-6
@if ($isOfficer) bg-gradient-to-br from-slate-900 via-blue-900 to-black @else bg-gray-100 @endif">

        <!-- MAIN FORM CONTAINER -->
        <div x-data="{ ...stepForm(), ...unitDropdown() }" class="w-full max-w-5xl">

            <!-- HEADER -->
            <div class="text-center mb-6 text-black">
                <h1 class="text-3xl font-bold">Registration Portal</h1>
                <p class="text-gray-300 text-sm">
                    {{ $isOfficer ? 'Commissioned Officer' : 'Enlisted Personnel' }}
                </p>
            </div>

            <!-- CARD -->
            <div class="bg-white rounded-3xl shadow-2xl p-8">

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
                    <input type="hidden" name="registration_type" value="{{ request('type') }}">
                    <!-- STEP 1: BASIC INFO -->
                    <div x-show="step === 0" class="space-y-5">
                        <h3 class="font-semibold text-lg">Basic Information</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <select name="rank"
                                style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;"
                                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59, 130, 246, 0.3)'"
                                onblur="this.style.borderColor='#ccc'; this.style.boxShadow='none'">
                                <option value="">Rank</option>
                                @if (!$isOfficer)
                                    <option>ASN</option>
                                    <option>SN2</option>
                                    <option>SN1</option>
                                    <option>PO3</option>
                                    <option>PO2</option>
                                @else
                                    <option>ENS</option>
                                    <option>LTJG</option>
                                    <option>LT</option>
                                    <option>LCDR</option>
                                    <option>CDR</option>
                                @endif
                            </select>

                            <input type="text" name="serial_number" placeholder="Serial Number"
                                style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;"
                                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59, 130, 246, 0.3)'"
                                onblur="this.style.borderColor='#ccc'; this.style.boxShadow='none'">
                        </div>

                        <input type="text" name="name" placeholder="Full Name"
                            style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;"
                            onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59, 130, 246, 0.3)'"
                            onblur="this.style.borderColor='#ccc'; this.style.boxShadow='none'">
                        <input type="email" name="email" placeholder="Email"
                            style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;"
                            onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59, 130, 246, 0.3)'"
                            onblur="this.style.borderColor='#ccc'; this.style.boxShadow='none'">
                    </div>

                    <!-- STEP 2: SERVICE DETAILS -->
                    <div x-show="step === 1" class="space-y-5">
                        <h3 class="font-semibold text-lg">Service Details</h3>

                        <div class="grid grid-cols-3 gap-4">

                            <!-- Date of Birth -->
                            <div class="flex flex-col">
                                <label for="dob" class="text-sm font-medium mb-1">Date of Birth</label>
                                <input type="date" id="dob" name="dob"
                                    style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;"
                                    onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59, 130, 246, 0.3)'"
                                    onblur="this.style.borderColor='#ccc'; this.style.boxShadow='none'">
                            </div>

                            <!-- Date of Appointment -->
                            <div class="flex flex-col">
                                <label for="date_of_appointment" class="text-sm font-medium mb-1">Date of
                                    Appointment</label>
                                <input type="date" id="date_of_appointment" name="date_of_appointment"
                                    style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;"
                                    onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59, 130, 246, 0.3)'"
                                    onblur="this.style.borderColor='#ccc'; this.style.boxShadow='none'">
                            </div>

                            <!-- ETE -->
                            <div class="flex flex-col">
                                <label for="ete" class="text-sm font-medium mb-1">ETE</label>
                                <input type="date" id="ete" name="ete" readonly
                                    class="input-modern bg-gray-100">
                            </div>

                        </div>

                        <p class="text-sm text-gray-500">Age: <span id="age"></span></p>
                    </div>

                    <!-- STEP 3: UNIT ASSIGNMENT -->
                    <div x-data="unitDropdown()" x-show="step === 2" class="space-y-5">

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
                    <div x-show="step === 3" class="space-y-5">
                        <h3 class="font-semibold text-lg">Security & Verification</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <input type="password" name="password" placeholder="Password"
                                style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;"
                                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59, 130, 246, 0.3)'"
                                onblur="this.style.borderColor='#ccc'; this.style.boxShadow='none'">
                            <input type="password" name="password_confirmation" placeholder="Confirm Password"
                                style="width: 100%; padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 0.5rem; outline: none; transition: 0.3s; font-size: 1rem;"
                                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 2px rgba(59, 130, 246, 0.3)'"
                                onblur="this.style.borderColor='#ccc'; this.style.boxShadow='none'">
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
    <script>
        function stepForm() {
            return {
                step: 0,
                steps: ['Basic', 'Service', 'Unit', 'Verify'],
                next() {
                    if (this.step < 3) this.step++;
                },
                prev() {
                    if (this.step > 0) this.step--;
                },
                goTo(index) {
                    this.step = index;
                }
            }
        }
    </script>
    <script>
        function unitDropdown() {
            return {
                selectedCommand: '',
                selectedCategory: '',
                selectedUnit: '',
                selectedSubUnit: '',

                // ✅ UPDATED BASED ON YOUR CSV FILE
                units: {
                    "HSS- MEPCOM": {
                        "HSS MEPCOM": {},
                        "HMEPCOM": {
                            "Flag": [],
                            "Deputy": [],
                            "Chief of MEP Staff": [],
                            "Command Master Chief Perry Officer": [],
                            "Chief Master-at-Arms (CMAA), MEPCOM": []
                        },
                        "Special Staff": {
                            "Procurement Office": [],
                            "Supply Accountable Officer": [],
                            "Office of the Strategic Management": [],
                            "Office of the Quality Management System": [],
                            "Human Resource Management Unit": [],
                            "Real Estate Management Office": []
                        },
                        "Staff Office": {
                            "DCMEPS for HRM, M-1": ["M-1", "Adjutant"],
                            "DCMEPS for Intelligence, Health and Safety, M-2": [],
                            "DCMEPS for Operations, M-3": ["M3", "Command Center", "Public Accountable Officer"],
                            "DCMEPS for Logistics, M-4": [],
                            "DCMEPS for Plans and Programs, and International Affairs, M-5": [],
                            "DCMEPS for Comptrollership, M-6": [],
                            "DCMEPS for Weapons, Communication, Electronics, and Information System, M-11": [],
                            "DCMEPS for Education and Training, M-12": []
                        }
                    },
                    "Operational Support Unit": {
                        "NOCOP": {
                            "HNOCOP": [],
                            "OSRU": [
                                "OSRU NCRCL", "OSRU NELZN", "OSRU NWLZN", "OSRU STL", "OSRU BCL",
                                "OSRU PAL", "OSRU EV", "OSRU WV", "OSRU CV", "OSRU NM",
                                "OSRU NEM", "OSRU SM", "OSRU SEM", "OSRU SWM", "OSRU BARMM"
                            ]
                        },
                        "MSFIS": {
                            "HMSFISP": [],
                            "MSFIU": [
                                "MSFIU NCRCL", "MSFIU NELZN", "MSFIU NWLZN", "MSFIU STL", "MSFIU BCL",
                                "MSFIU PAL", "MSFIU KIL", "MSFIU EV", "MSFIU WV", "MSFIU CV",
                                "MSFIU NM", "MSFIU NEM", "MSFIU SM", "MSFIU SEM", "MSFIU SWM", "MSFIU BARMM"
                            ]
                        },
                        "MEPLEF": {
                            "HMEPLEF": [],
                            "MEPLEU": [
                                "MEPLEU NCRCL", "MEPLEU NELZN", "MEPLEU NWLZN", "MEPLEU STL", "MEPLEU BCL",
                                "MEPLEU PAL", "MEPLEU KIG (Kalayaan Island Group)", "MEPLEU EV", "MEPLEU WV",
                                "MEPLEU CV", "MEPLEU NM", "MEPLEU NEM", "MEPLEU SM", "MEPLEU SEM",
                                "MEPLEU SWM", "MEPLEU BARMM"
                            ]
                        }
                    },
                    "Administrative Support Unit": {
                        "MEPAcCeS": {
                            "HMEPAcCeS": [],
                            "VCA": [
                                "VCA NCRCL", "VCA NELZN", "VCA NWLZN", "VCA STL", "VCA BCL",
                                "VCA PAL", "VCA KIG (Kalayaan Island Group)", "VCA EV", "VCA WV",
                                "VCA CV", "VCA NM", "VCA NEM", "VCA SM", "VCA SEM", "VCA SWM", "VCA BARMM"
                            ]
                        },
                        "MEPRCGS": {
                            "MEPRCGS": ["MEPRCGS"]
                        },
                        "MEPTDI": {
                            "HMEPTDI": [],
                            "Satelite MEPTDI": []
                        }
                    },
                    "Operating Unit": {
                        "MEPGroup (MEPG)": {
                            "MEPGroup NCRCL": [
                                "MEPUnit Manila", "MEPUnit Cavite", "MEPUnit Bataan", "MEPUnit Zambales",
                                "MEPUnit Laguna", "MEPUnit Rizal", "MEPUnit Malacañang", "MEPUnit Pampanga"
                            ],
                            "MEPGroup NELZN": ["MEPUnit Cagayan", "MEPUnit Calayan", "MEPUnit Aurora", "MEPUnit Batanes",
                                "MEPUnit Isabela"
                            ],
                            "MEPGroup NWLZN": ["MEPUnit Ilocos Norte", "MEPUnit Ilocos Sur", "MEPUnit La Union",
                                "MEPUnit Pangasinan"
                            ],
                            "MEPGroup STL": [
                                "MEPUnit Batangas", "MEPUnit Oriental Mindoro", "MEPUnit Occidental Mindoro",
                                "MEPUnit Southern Quezon", "MEPUnit Marinduque", "MEPUnit Romblon",
                                "MEPUnit Northern Quezon"
                            ],
                            "MEPGroup BCL": [
                                "MEPUnit Albay", "MEPUnit Sorsogon", "MEPUnit Camarines Norte",
                                "MEPUnit Catanduanes", "MEPUnit Masbate", "MEPUnit Camarines Sur"
                            ],
                            "MEPGroup PAL": [
                                "MEPUnit Central Palawan", "MEPUnit South Central Palawan", "MEPUnit Southern Palawan",
                                "MEPUnit Northeastern Palawan", "MEPUnit Northern Palawan", "MEPUnit Eastern Palawan",
                                "MEPUnit Western Palawan", "MEPUnit South Western Palawan"
                            ],
                            "MEPGroup KIG Kalayaan Island Group": ["MEPUnit Parola", "MEPUnit Panatag", "MEPUnit Panata"],
                            "MEPGroup EV": [
                                "MEPUnit Western Leyte", "MEPUnit Eastern Leyte", "MEPUnit Southern Leyte",
                                "MEPUnit Biliran", "MEPUnit Northern Samar", "MEPUnit Western Samar",
                                "MEPUnit Eastern Samar"
                            ],
                            "MEPGroup WV": ["MEPUnit Antique", "MEPUnit Aklan", "MEPUnit Iloilo", "MEPUnit Capiz",
                                "MEPUnit Guimaras"
                            ],
                            "MEPGroup CV": [
                                "MEPUnit Central Cebu", "MEPUnit Western Bohol", "MEPUnit Eastern Bohol",
                                "MEPUnit Southern Cebu", "MEPUnit Camotes", "MEPUnit Northern Cebu",
                                "MEPUnit Siquijor", "MEPUnit Negros Oriental"
                            ],
                            "MEPGroup SV": [
                                "MEPUnit Northern Negros Occidental", "MEPUnit Negros Oriental",
                                "MEPUnit Siquijor", "MEPUnit Southern Negros Occidental"
                            ],
                            "MEPGroup NM": ["MEPUnit Misamis Oriental", "MEPUnit Cagayan de Oro", "MEPUnit Lanao del Norte",
                                "MEPUnit Misamis Occidental", "MEPUnit Camiguin"
                            ],
                            "MEPGroup NEM": ["MEPUnit Siargao", "MEPUnit Surigao del Norte", "MEPUnit Surigao del Sur",
                                "MEPUnit Dinagat Islands", "MEPUnit Agusan del Norte", "MEPUnit Bucas Grande"
                            ],
                            "MEPGroup SM": ["MEPUnit South Cotabato", "MEPUnit Sarangani", "MEPUnit Sultan Kudarat"],
                            "MEPGroup SEM": [
                                "MEPUnit Davao", "MEPUnit Davao Oriental", "MEPUnit Davao de Oro",
                                "MEPUnit Davao Occidental", "MEPUnit Igacos", "MEPUnit Davao del Norte",
                                "MEPUnit Davao del Sur"
                            ],
                            "MEPGroup SWM": ["MEPUnit Zamboanga", "MEPUnit Zamboanga del Norte",
                                "MEPUnit Zamboanga Sibugay", "MEPUnit Isabela"
                            ],
                            "MEPGroup BARMM": [
                                "MEPUnit Maguindanao", "MEPUnit Western Sulu", "MEPUnit Northern Tawi Tawi",
                                "MEPUnit Western Tawi Tawi", "MEPUnit Central Tawi Tawi", "MEPUnit Basilan",
                                "MEPUnit Eastern Sulu", "MEPUnit Lanao del Sur"
                            ]
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
                }
            }
        }
    </script>

    <!-- MAIN JS -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {

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
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                const data = canvas.toDataURL('image/png');
                selfie.value = data;
                preview.src = data;
                preview.classList.remove('hidden');
                Swal.fire('Captured!', 'Selfie saved.', 'success');
            });

            // SUBMIT
            document.getElementById('registerForm').addEventListener('submit', () => {
                Swal.fire({
                    title: 'Processing...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
            });

        });
    </script>
