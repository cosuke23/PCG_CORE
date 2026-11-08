@extends('layouts.personnel')

@section('title', 'Required documents')

@section('styles')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/leaflet-geocoder/Control.Geocoder.css') }}">
    <link rel="stylesheet" href="{{ asset('css/upload-documents.css') }}">
@endsection

@php
    $docs = $documents ?? null;
    $today = \Carbon\Carbon::now('Asia/Manila')->startOfDay();
    $fileUrl = fn ($path) => \App\Support\Uploads::url('documents_toupload/' . $path);
    // Ombudsman and Sandiganbayan clearances: shown to everyone, as before
    // (the old page meant to hide them for enlisted ranks but never did).
    $groups = [
        'Forms' => [
            'soi' => ['Statement of Information (SOI)', 'pdf', false],
            'ofr' => ['OFR / NOPER', 'pdf', false],
        ],
        'Clearances' => [
            'nbi_clearance' => ['NBI clearance', 'pdf', true],
            'ombudsman_clearance' => ['Ombudsman clearance', 'pdf', true],
            'sandiganbayan_clearance' => ['Sandiganbayan clearance', 'pdf', true],
            'medical_clearance' => ['Medical clearance', 'pdf', false],
            'pft_clearance' => ['PFT clearance', 'pdf', false],
        ],
        'Photos and sketch' => [
            'photo_2x2' => ['2x2 photo', 'image', false],
            'whole_body_picture' => ['Whole-body photo', 'image', false],
            'residential_sketch' => ['Residential sketch', 'pdf-or-image', false],
        ],
    ];
    $types = [
        'pdf' => ['accept' => '.pdf,application/pdf', 'mimes' => '["application/pdf"]', 'hint' => 'PDF, up to 10 MB'],
        'image' => ['accept' => '.jpg,.jpeg,.png,image/jpeg,image/png', 'mimes' => '["image/jpeg","image/png"]', 'hint' => 'JPG or PNG, up to 10 MB'],
        'pdf-or-image' => ['accept' => '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png', 'mimes' => '["application/pdf","image/jpeg","image/png"]', 'hint' => 'PDF, JPG or PNG, up to 10 MB'],
    ];
    $total = collect($groups)->flatten(1)->count();
    $done = collect($groups)->map(fn ($g) => array_keys($g))->flatten()->filter(fn ($f) => !empty($docs->$f))->count();
@endphp

@section('content')
    <div class="ud-overlay" id="ud-overlay" hidden>
        <div class="ud-overlay__box" role="status" aria-live="polite">
            <span class="ud-spinner" aria-hidden="true"></span>
            <p>Encrypting and uploading your files…</p>
        </div>
    </div>

    <div class="pcg-page ud ud-main">
        <section class="ud-hero">
            <div>
                <p class="ud-eyebrow">Statement of Information · requirements</p>
                <h1>Required documents</h1>
                <p class="ud-lede">Upload each document here. You can upload some now and the rest later; only the files you choose are sent. Your unit checker reviews them and tells you if one needs to be uploaded again.</p>
            </div>
            <div class="ud-progress" aria-label="{{ $done }} of {{ $total }} documents uploaded">
                <div class="ud-progress__top"><strong>{{ $done }} of {{ $total }}</strong><span>uploaded</span></div>
                <div class="ud-progress__bar"><span style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span></div>
            </div>
        </section>

        <p class="ud-secure">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <span>Every file is encrypted with <strong>AES-256</strong> as soon as it arrives and stored under a random name. Only you, your unit's document checker and authorised HR staff can open it, and each opening is logged.</span>
        </p>

        <form id="uploadForm" action="/documents/upload" method="POST" enctype="multipart/form-data">
            @csrf

            @foreach ($groups as $groupName => $fields)
                <section class="ud-group" aria-labelledby="g-{{ $loop->index }}">
                    <h2 id="g-{{ $loop->index }}">{{ $groupName }}</h2>
                    <div class="ud-grid">
                        @foreach ($fields as $field => [$label, $kind, $hasExpiry])
                            @php
                                $have = !empty($docs->$field);
                                $exp = $hasExpiry && !empty($docs->{$field . '_expiration'}) ? \Carbon\Carbon::parse($docs->{$field . '_expiration'}) : null;
                                $state = !$have ? 'missing' : ($exp && $exp->lt($today) ? 'expired' : ($exp && $exp->lte($today->copy()->addDays(30)) ? 'soon' : 'ok'));
                                $t = $types[$kind];
                            @endphp
                            <article class="ud-doc ud-doc--{{ $state }}">
                                <div class="ud-doc__head">
                                    <h3 id="lbl-{{ $field }}">{{ $label }}</h3>
                                    <span class="ud-chip ud-chip--{{ $state }}">
                                        @switch($state)
                                            @case('missing') Not uploaded @break
                                            @case('expired') Expired @break
                                            @case('soon') Expires soon @break
                                            @default Uploaded
                                        @endswitch
                                    </span>
                                </div>

                                @if ($have)
                                    <p class="ud-doc__meta">
                                        <a href="{{ $fileUrl($docs->$field) }}" target="_blank" rel="noopener"><i class="fa-regular fa-eye" aria-hidden="true"></i> View current file</a>
                                        @if ($exp)
                                            <span>Valid until {{ $exp->format('d M Y') }}</span>
                                        @endif
                                    </p>
                                @endif

                                <input class="ud-file" type="file" id="f-{{ $field }}" name="{{ $field }}" accept="{{ $t['accept'] }}"
                                    aria-labelledby="lbl-{{ $field }}" data-on-change="pcg.file-types" data-args='{{ $t['mimes'] }}'>
                                <label class="ud-drop" for="f-{{ $field }}">
                                    <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                                    <span class="ud-drop__text" data-default="{{ $have ? 'Choose a new file to replace it' : 'Choose a file' }}">{{ $have ? 'Choose a new file to replace it' : 'Choose a file' }}</span>
                                    <span class="ud-drop__hint">{{ $t['hint'] }}</span>
                                </label>

                                @if ($hasExpiry)
                                    <div class="ud-exp">
                                        <label class="pcg-label" for="e-{{ $field }}">Valid until</label>
                                        <input class="pcg-input" type="date" id="e-{{ $field }}" name="{{ $field }}_expiration"
                                            value="{{ $docs->{$field . '_expiration'} ?? '' }}">
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section class="ud-group" aria-labelledby="g-map">
                <h2 id="g-map">Residence location</h2>
                <p class="ud-note">Search for your address, then drag the pin or tap the map to mark your house. Your current position is shown too, if your browser allows it.</p>
                <div id="map" class="ud-map" role="application" aria-label="Map to pin your residence"
                    data-lat="{{ is_numeric($docs->latitude ?? null) ? $docs->latitude : '' }}"
                    data-lng="{{ is_numeric($docs->longitude ?? null) ? $docs->longitude : '' }}"></div>
                <p class="ud-coords" id="coords" aria-live="polite"></p>
                <input type="hidden" name="latitude" id="latitude" value="{{ is_numeric($docs->latitude ?? null) ? $docs->latitude : '' }}">
                <input type="hidden" name="longitude" id="longitude" value="{{ is_numeric($docs->longitude ?? null) ? $docs->longitude : '' }}">
            </section>

            <div class="ud-bar">
                <div class="ud-bar__row">
                    <span class="ud-bar__count" id="ud-count" aria-live="polite">No new files chosen</span>
                    <button type="submit" id="submitBtn" class="pcg-btn pcg-btn--primary">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Save and upload
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @php(app()->instance('csp.allow_osm', true)) {{-- map tiles and address search: OpenStreetMap on this page only --}}
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('vendor/leaflet-geocoder/Control.Geocoder.js') }}"></script>
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ asset('js/upload-documents.js') }}"></script>
@endsection
