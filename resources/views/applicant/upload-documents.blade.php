<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Upload Required Documents</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #1e3a8a, #1e40af);
        }

        .container {
            max-width: 1000px;
            margin: auto;
            padding: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }

        .btn-gray {
            background: gray;
        }

        .btn-green {
            background: green;
        }

        .btn-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .doc-box {
            background: rgba(255, 255, 255, 0.08);
            padding: 15px;
            border-radius: 14px;
            border: 1px dashed rgba(255, 255, 255, 0.4);
        }

        .uploaded {
            border: 1px solid #22c55e !important;
        }

        input[type="file"] {
            width: 100%;
            color: white;
        }

        #map {
            height: 320px;
            border-radius: 12px;
            border: 2px solid white;
            margin-top: 15px;
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #ccc;
            border-top: 6px solid #22c55e;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>

    <div class="loading-overlay" id="loadingOverlay">
        <div>
            <div class="spinner"></div>
            <p style="color:white;">Uploading...</p>
        </div>
    </div>

    <div class="container">
        <div class="card">

            <div class="btn-row">
                <a href="/profile" class="btn btn-gray">← Back</a>
            </div>

            <h2>Upload Required Documents</h2>

            @if (session('success'))
                <div role="status" style="background:#dcfce7;color:#166534;padding:10px 14px;border-radius:8px;margin-bottom:12px;">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div role="alert" style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:12px;">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div role="alert" style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:12px;">
                    <ul style="margin:0;padding-left:18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="uploadForm" action="/documents/upload" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="grid">

                    @php
                        $docs = $documents ?? null;
                    @endphp
                    <!-- SOI -->
                    <div class="doc-box {{ !empty($docs->soi) ? 'uploaded' : '' }}">
                        <label>SOI</label>

                        @if (!empty($docs->soi))
                            <small style="color:#22c55e;">
                                ✔ Uploaded:
                                <a href="{{ \App\Support\Uploads::url('documents_toupload/' . $docs->soi) }}" target="_blank">View</a>
                            </small>
                        @endif

                        <input type="file" name="soi" accept=".pdf,application/pdf"
                            onchange="
                            if(this.files[0] && this.files[0].type !== 'application/pdf'){
                                alert('PDF files only');
                                this.value='';
                            }
                        ">
                    </div>

                    <!-- NBI -->
                    <div class="doc-box {{ !empty($docs->nbi_clearance) ? 'uploaded' : '' }}">
                        <label>NBI Clearance</label>

                        @if (!empty($docs->nbi_clearance))
                            <small style="color:#22c55e;">
                                ✔ Uploaded:
                                <a href="{{ \App\Support\Uploads::url('documents_toupload/' . $docs->nbi_clearance) }}" target="_blank">View</a>
                            </small>
                            <br>
                            <small>Exp: {{ $docs->nbi_clearance_expiration }}</small>
                        @endif

                        <input type="file" name="nbi_clearance" accept=".pdf,application/pdf"
                            onchange="
                            if(this.files[0] && this.files[0].type !== 'application/pdf'){
                                alert('PDF files only');
                                this.value='';
                            }
                        ">
                        <input type="date" name="nbi_clearance_expiration"
                            value="{{ $docs->nbi_clearance_expiration ?? '' }}">
                    </div>

                    @php
                        $hideClearances = in_array($applicant->rank ?? null, [
                            'FMCPO',
                            'MCPO',
                            'SCPO',
                            'CPO',
                            'PO1',
                            'PO2',
                            'PO3',
                            'SN1',
                            'SN2',
                            'ASN',
                        ]);
                    @endphp

                    @if (!$hideClearances)

                        <!-- Ombudsman -->
                        <div class="doc-box {{ !empty($docs->ombudsman_clearance) ? 'uploaded' : '' }}">
                            <label> Ombudsman</label>

                            @if (!empty($docs->ombudsman_clearance))
                                <small style="color:#22c55e;">
                                    ✔ Uploaded:
                                    <a href="{{ \App\Support\Uploads::url('documents_toupload/' . $docs->ombudsman_clearance) }}"
                                        target="_blank">View</a>
                                </small>
                                <br>
                                <small>Exp: {{ $docs->ombudsman_clearance_expiration }}</small>
                            @endif

                            <input type="file" name="ombudsman_clearance" accept=".pdf,application/pdf"
                                onchange="
                                if(this.files[0] && this.files[0].type !== 'application/pdf'){
                                    alert('PDF files only');
                                    this.value='';
                                }
                            ">
                            <input type="date" name="ombudsman_clearance_expiration"
                                value="{{ $docs->ombudsman_clearance_expiration ?? '' }}">
                        </div>

                        <!-- Sandiganbayan -->
                        <div class="doc-box {{ !empty($docs->sandiganbayan_clearance) ? 'uploaded' : '' }}">
                            <label>Sandiganbayan</label>

                            @if (!empty($docs->sandiganbayan_clearance))
                                <small style="color:#22c55e;">
                                    ✔ Uploaded:
                                    <a href="{{ \App\Support\Uploads::url('documents_toupload/' . $docs->sandiganbayan_clearance) }}"
                                        target="_blank">View</a>
                                </small>
                                <br>
                                <small>Exp: {{ $docs->sandiganbayan_clearance_expiration }}</small>
                            @endif

                            <input type="file" name="sandiganbayan_clearance" accept=".pdf,application/pdf"
                                onchange="
                                if(this.files[0] && this.files[0].type !== 'application/pdf'){
                                    alert('PDF files only');
                                    this.value='';
                                }
                            ">
                            <input type="date" name="sandiganbayan_clearance_expiration"
                                value="{{ $docs->sandiganbayan_clearance_expiration ?? '' }}">
                        </div>

                    @endif

                    <!-- Remaining simple fields -->
                    @foreach ([
        'ofr' => 'OFR/NOPER',
        'whole_body_picture' => 'Whole Body',
        'photo_2x2' => '2x2 Photo',
        'medical_clearance' => 'Medical Clearance',
        'pft_clearance' => 'PFT Clearance',
        'residential_sketch' => 'Residential Sketch',
    ] as $field => $label)
                        <div class="doc-box {{ !empty($docs->$field) ? 'uploaded' : '' }}">
                            <label>{{ $label }}</label>

                            @if (!empty($docs->$field))
                                <small style="color:#22c55e;">
                                    ✔ Uploaded:
                                    <a href="{{ \App\Support\Uploads::url('documents_toupload/' . $docs->$field) }}" target="_blank">
                                        View
                                    </a>
                                </small>
                            @endif

                            <input type="file" name="{{ $field }}"
                                @if (in_array($field, ['whole_body_picture', 'photo_2x2'])) accept=".jpg,.jpeg,.png,image/jpeg,image/png"
    @else
        accept=".pdf,application/pdf" @endif
                                onchange="
        const file = this.files[0];

        if (!file) return;

        @if (in_array($field, ['whole_body_picture', 'photo_2x2'])) const allowedImages = ['image/jpeg', 'image/png'];

            if (!allowedImages.includes(file.type)) {
                alert('Only JPG and PNG images are allowed');
                this.value='';
            }

        @else

            if (file.type !== 'application/pdf') {
                alert('PDF files only');
                this.value='';
            } @endif
    ">
                        </div>
                    @endforeach

                </div>

                <div class="section-title">📍 Pin Location</div>
                <div id="map"></div>

                <input type="hidden" name="latitude" id="latitude" value="{{ $docs->latitude ?? '' }}">
                <input type="hidden" name="longitude" id="longitude" value="{{ $docs->longitude ?? '' }}">

                <p id="coords"></p>

                <div class="btn-row" style="margin-top:20px;">
                    <button type="submit" id="submitBtn" class="btn btn-green">Submit</button>
                </div>

            </form>

        </div>
    </div>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

    <script>
        let map, marker;
        let userMarker;

        window.onload = function() {
            initMap();

            const form = document.getElementById('uploadForm');
            if (form) {
                form.addEventListener('submit', function() {
                    document.getElementById('loadingOverlay').style.display = 'flex';
                });
            }
        };

        function initMap() {

            let lat = {{ $docs->latitude ?? 13.41 }};
            let lng = {{ $docs->longitude ?? 122.56 }};

            map = L.map('map').setView([lat, lng], 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: ''
            }).addTo(map);

            // =========================
            // SEARCH CONTROL (NEW)
            // =========================
            const geocoder = L.Control.geocoder({
                    defaultMarkGeocode: false
                })
                .on('markgeocode', function(e) {
                    const center = e.geocode.center;

                    map.setView(center, 15);

                    marker.setLatLng(center);
                    setCoords(center.lat, center.lng);
                })
                .addTo(map);

            // =========================
            // MAIN MARKER
            // =========================
            marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);

            setCoords(lat, lng);

            marker.on('dragend', function() {
                const pos = marker.getLatLng();
                setCoords(pos.lat, pos.lng);
            });

            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                setCoords(e.latlng.lat, e.latlng.lng);
            });

            // =========================
            // USER LOCATION
            // =========================
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(pos) {

                    let userLat = pos.coords.latitude;
                    let userLng = pos.coords.longitude;

                    userMarker = L.marker([userLat, userLng], {
                            icon: L.icon({
                                iconUrl: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png",
                                iconSize: [32, 32]
                            })
                        }).addTo(map)
                        .bindPopup("📍 Your Current Location")
                        .openPopup();

                });
            }
        }

        function setCoords(lat, lng) {
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            document.getElementById('coords').innerText =
                "Lat: " + lat.toFixed(6) + ", Lng: " + lng.toFixed(6);
        }
    </script>

</body>

</html>
