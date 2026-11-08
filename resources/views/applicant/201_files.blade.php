@extends('layouts.personnel')

@section('title', '201 files')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/upload-documents.css') }}">
@endsection

@php
    $docs = $documents ?? null;
    $fileUrl = fn ($path) => \App\Support\Uploads::url($path);
    // Personnel 201 file (folder sections A to K)
    $categories = [
        'soi' => ['A', 'Latest SOI'],
        'appointment' => ['B', 'Appointment'],
        'leave' => ['C', 'Leave balance'],
        'awards' => ['D', 'Awards'],
        'disciplinary' => ['E', 'Disciplinary action'],
        'performance' => ['F', 'Performance record'],
        'legal' => ['G', 'Legal documents'],
        'academic' => ['H', 'Academic records'],
        'financial' => ['I', 'Financial record'],
        'retirement' => ['J', 'Retirement record'],
        'body_picture' => ['K', 'Whole-body picture'],
    ];
    $total = count($categories);
    $done = collect(array_keys($categories))->filter(fn ($f) => !empty($docs->$f))->count();
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
                <p class="ud-eyebrow">Personnel record · 201 file</p>
                <h1>201 files</h1>
                <p class="ud-lede">Your 201 file, section by section (A to K). Upload a section now and the rest later; only the files you choose are sent. A new file replaces the one already in that section.</p>
            </div>
            <div class="ud-progress" aria-label="{{ $done }} of {{ $total }} sections uploaded">
                <div class="ud-progress__top"><strong>{{ $done }} of {{ $total }}</strong><span>sections uploaded</span></div>
                <div class="ud-progress__bar"><span style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span></div>
            </div>
        </section>

        <p class="ud-secure">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <span>Every file is encrypted with <strong>AES-256</strong> as soon as it arrives and stored under a random name. Only you, the M1 checker and authorised HR staff can open it, and each opening is logged.</span>
        </p>

        <form id="uploadForm" action="{{ route('upload.201.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <section class="ud-group" aria-labelledby="g-201">
                <h2 id="g-201">Sections</h2>
                <div class="ud-grid">
                    @foreach ($categories as $field => [$letter, $label])
                        @php($have = !empty($docs->$field))
                        <article class="ud-doc ud-doc--{{ $have ? 'ok' : 'missing' }}">
                            <div class="ud-doc__head">
                                <h3 id="lbl-{{ $field }}"><span class="ud-letter">{{ $letter }}.</span> {{ $label }}</h3>
                                <span class="ud-chip ud-chip--{{ $have ? 'ok' : 'missing' }}">{{ $have ? 'Uploaded' : 'Not uploaded' }}</span>
                            </div>

                            @if ($have)
                                <p class="ud-doc__meta">
                                    <a href="{{ $fileUrl($docs->$field) }}" target="_blank" rel="noopener"><i class="fa-regular fa-eye" aria-hidden="true"></i> View current file</a>
                                </p>
                            @endif

                            <input class="ud-file" type="file" id="f-{{ $field }}" name="{{ $field }}"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                aria-labelledby="lbl-{{ $field }}" data-on-change="pcg.file-types" data-args='["application/pdf","image/jpeg","image/png"]'>
                            <label class="ud-drop" for="f-{{ $field }}">
                                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                                <span class="ud-drop__text" data-default="{{ $have ? 'Choose a new file to replace it' : 'Choose a file' }}">{{ $have ? 'Choose a new file to replace it' : 'Choose a file' }}</span>
                                <span class="ud-drop__hint">PDF, JPG or PNG, up to 10 MB</span>
                            </label>
                        </article>
                    @endforeach
                </div>
            </section>

            <div class="ud-bar">
                <div class="ud-bar__row">
                    <span class="ud-bar__count" id="ud-count" aria-live="polite">No new files chosen</span>
                    <button type="submit" id="submitBtn" class="pcg-btn pcg-btn--primary">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload files
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/upload-documents.js') }}"></script>
@endsection
