/*
 * Registration wizard (resources/views/pages/register.blade.php).
 * Loaded with `defer` BEFORE Alpine.js, so stepForm() and unitDropdown()
 * exist when Alpine reads x-data. Values from the server come from
 * #regWizard data attributes (start step, draft key).
 */

// Draft: what the person typed is kept in this browser tab (sessionStorage),
// so a refresh does not lose it. Passwords and the selfie are never kept.
// The tab's copy is removed once the registration is accepted (sign-in page),
// with "Start over", after 12 hours, or when the tab closes.
const REG_DRAFT_KEY = (document.getElementById('regWizard') || { dataset: {} }).dataset.draftKey || 'pcgRegisterDraft:non_officer';
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
    Swal.fire({ icon: 'warning', title: 'Please complete this step', html: list, confirmButtonColor: '#102a5c' });
}

function stepForm() {
    const root = document.getElementById('regWizard');
    const start = parseInt(root ? root.dataset.startStep : '0', 10);
    return {
        step: Number.isInteger(start) ? Math.min(Math.max(start, 0), 3) : 0,
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

// Cascading unit picker. Used twice (wizard root and the Unit step); restoreUnits() is called by the draft.
function unitDropdown() {
    return {
        selectedCommand: '',
        selectedCategory: '',
        selectedUnit: '',
        selectedSubUnit: '',

        // Unit tree: command > category > unit > sub-unit
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

document.addEventListener('DOMContentLoaded', () => {

    const errorBox = document.getElementById('registerErrors');
    if (errorBox) { errorBox.scrollIntoView({ block: 'center' }); errorBox.focus({ preventScroll: true }); }

    // AGE
    const dob = document.getElementById('dob');
    const ageSpan = document.getElementById('age');
    dob.addEventListener('change', () => {
        if (!dob.value) { ageSpan.textContent = ''; return; }
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
    const btnLabel = document.getElementById('captureLabel');
    const retry = document.getElementById('cameraRetry');
    const selfie = document.getElementById('selfieImage');
    const preview = document.getElementById('selfiePreview');
    const status = document.getElementById('cameraStatus');

    function setStatus(text, problem) {
        status.textContent = text;
        status.classList.toggle('is-problem', !!problem);
        retry.hidden = !problem;
    }

    function cameraProblem(err) {
        const name = err && err.name;
        if (name === 'NotAllowedError' || name === 'SecurityError') {
            return 'Camera access is blocked. Allow the camera for this site (the camera icon in the address bar), then try again.';
        }
        if (name === 'NotFoundError' || name === 'OverconstrainedError') return 'No camera was found on this device.';
        if (name === 'NotReadableError') return 'Another app is using the camera. Close it, then try again.';
        return 'Camera error: ' + ((err && err.message) || 'unknown');
    }

    function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('This browser cannot open the camera here. Use a current browser on a secure (https) address.', true);
            return;
        }
        setStatus('Starting the camera…', false);
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                video.srcObject = stream;
                setStatus(selfie.value ? 'Selfie captured. Capture again to replace it.' : 'Camera ready. Look at the camera, then capture.', false);
            })
            .catch(err => setStatus(cameraProblem(err), true));
    }
    startCamera();
    retry.addEventListener('click', startCamera);

    btn.addEventListener('click', () => {
        if (!video.videoWidth) {
            Swal.fire({ icon: 'warning', title: 'Camera not ready', text: 'Allow camera access for this site, wait for the picture, then capture again.', confirmButtonColor: '#102a5c' });
            return;
        }
        const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const data = canvas.toDataURL('image/jpeg', 0.85);
        selfie.value = data;
        preview.src = data;
        preview.hidden = false;
        btnLabel.textContent = 'Capture again';
        setStatus('Selfie captured. Capture again to replace it.', false);
    });

    // PASSWORD: strength (show/hide is pcg-ui.js, data-pcg-toggle-password)
    const pw = document.getElementById('regPassword');
    const pw2 = document.getElementById('regPasswordConfirm');
    const bar = document.getElementById('regPwBar');
    const help = document.getElementById('regPwHelp');
    const match = document.getElementById('regPwMatch');
    // [label, level class (colour in register.css), bar width %]
    const LEVELS = [
        ['Too short', 'weak', 10], ['Weak', 'weak', 25], ['Fair', 'fair', 50],
        ['Good', 'good', 75], ['Strong', 'strong', 100]
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
        const [label, tone, width] = LEVELS[level];
        bar.style.width = v ? width + '%' : '0';
        bar.dataset.level = tone;
        help.textContent = v ? 'Strength: ' + label + (level < 3 ? '. Add length, mixed case, numbers or symbols.' : '.') : 'Use 12 or more characters with upper and lower case letters, a number and a symbol.';
        rateMatch();
    }
    function rateMatch() {
        if (!pw2.value) { match.textContent = ''; match.removeAttribute('data-state'); return; }
        const same = pw2.value === pw.value;
        match.textContent = same ? 'Passwords match' : 'Passwords do not match yet';
        match.dataset.state = same ? 'ok' : 'bad';
    }
    pw.addEventListener('input', rate);
    pw2.addEventListener('input', rateMatch);

    // SUBMIT: check every step first; the button spinner comes from pcg-ui.js
    // (it skips submits cancelled here).
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
    });

});
