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
    // review locks (App\Support\Review201): waiting = nothing changes; after the
    // review only returned and never-filled sections open
    $waiting = \App\Support\Review201::awaitingReview($docs);
    $lockedFields = \App\Support\Review201::lockedFields($docs);
    $canSave = \App\Support\Review201::canSave($docs);
    $returned = $returned ?? [];
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
                <p class="ud-lede">Your 201 file, section by section (A to K); only the files you choose are sent. Your unit checker then reviews them. During the review nothing can be changed; afterwards only the sections the checker returns, and sections you have not filled yet, open again.</p>
            </div>
            <div class="ud-progress" aria-label="{{ $done }} of {{ $total }} sections uploaded">
                <div class="ud-progress__top"><strong>{{ $done }} of {{ $total }}</strong><span>sections uploaded</span></div>
                <div class="ud-progress__bar"><span style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span></div>
            </div>
        </section>

        <p class="ud-secure">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <span>Every file is encrypted with <strong>AES-256</strong> as soon as it arrives and stored under a random name. Only you, your unit's checker, the M1 checker and authorised HR staff can open it, and each opening is logged.</span>
        </p>

        @if ($waiting && $docs)
            <p class="ud-review" role="status">
                <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                <span><strong>Your 201 file is with your unit checker.</strong>
                    Nothing can be uploaded until the review is done. You will see here which sections, if any, were returned.</span>
            </p>
        @elseif ($returned)
            <p class="ud-review ud-review--returned" role="status">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                <span><strong>Your unit checker returned {{ count($returned) === 1 ? '1 section' : count($returned) . ' sections' }}.</strong>
                    Upload {{ count($returned) === 1 ? 'it' : 'them' }} again below; the sections the checker accepted stay locked.</span>
            </p>
        @elseif ($docs && !$canSave)
            <p class="ud-review ud-review--done" role="status">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span><strong>Your unit checker accepted your 201 file.</strong>
                    It is locked. If a section must change, ask your unit checker to return it.</span>
            </p>
        @endif

        <form id="uploadForm" action="{{ route('upload.201.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <section class="ud-group" aria-labelledby="g-201">
                <h2 id="g-201">Sections</h2>
                <div class="ud-grid">
                    @foreach ($categories as $field => [$letter, $label])
                        @php
                            $have = !empty($docs->$field);
                            $isLocked = in_array($field, $lockedFields, true);
                            $isReturned = array_key_exists($field, $returned) && !$have;
                            $lockKind = !$isLocked ? null : ($waiting ? ($have ? 'waiting' : 'later') : 'accepted');
                            $look = $lockKind === 'waiting' ? 'review' : ($lockKind === 'accepted' ? 'ok' : ($isReturned ? 'expired' : ($have ? 'ok' : 'missing')));
                            $chip = match (true) {
                                $lockKind === 'waiting' => 'Waiting for review',
                                $lockKind === 'accepted' => 'Accepted',
                                $isReturned => 'Returned',
                                $have => 'Uploaded',
                                default => 'Not uploaded',
                            };
                        @endphp
                        <article class="ud-doc ud-doc--{{ $look }}">
                            <div class="ud-doc__head">
                                <h3 id="lbl-{{ $field }}"><span class="ud-letter">{{ $letter }}.</span> {{ $label }}</h3>
                                <span class="ud-chip ud-chip--{{ $look }}">{{ $chip }}</span>
                            </div>

                            @if ($have)
                                <p class="ud-doc__meta">
                                    <a href="{{ $fileUrl($docs->$field) }}" target="_blank" rel="noopener"><i class="fa-regular fa-eye" aria-hidden="true"></i> View current file</a>
                                </p>
                            @endif

                            @if ($isReturned)
                                <p class="ud-returned"><strong>Returned by your unit checker:</strong> {{ $returned[$field] !== '' ? $returned[$field] : 'No comment given.' }}</p>
                            @endif

                            @if ($isLocked)
                                <div class="ud-locked">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    @if ($lockKind === 'waiting')
                                        <span><strong>Locked while your unit checker reviews it</strong>
                                            <small>If it is returned, you can upload it again here.</small></span>
                                    @elseif ($lockKind === 'later')
                                        <span><strong>Opens after the review</strong>
                                            <small>You can upload it once your unit checker has reviewed your current 201 file.</small></span>
                                    @else
                                        <span><strong>Accepted and locked</strong>
                                            <small>Ask your unit checker to return it if it must change.</small></span>
                                    @endif
                                </div>
                            @else
                            <input class="ud-file" type="file" id="f-{{ $field }}" name="{{ $field }}"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                aria-labelledby="lbl-{{ $field }}" data-on-change="pcg.file-types" data-args='["application/pdf","image/jpeg","image/png"]'>
                            <label class="ud-drop" for="f-{{ $field }}">
                                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                                <span class="ud-drop__text" data-default="{{ $have ? 'Choose a new file to replace it' : 'Choose a file' }}">{{ $have ? 'Choose a new file to replace it' : 'Choose a file' }}</span>
                                <span class="ud-drop__hint">PDF, JPG or PNG, up to 10 MB</span>
                            </label>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <div class="ud-bar">
                <div class="ud-bar__row">
                    <span class="ud-bar__count" id="ud-count" aria-live="polite" data-locked-text="{{ $canSave ? '' : ($waiting ? 'Waiting for your unit checker: nothing can be uploaded until the review is done.' : 'Every section is accepted and locked.') }}">{{ $canSave ? 'No new files chosen' : ($waiting ? 'Waiting for your unit checker: nothing can be uploaded until the review is done.' : 'Every section is accepted and locked.') }}</span>
                    <button type="submit" id="submitBtn" class="pcg-btn pcg-btn--primary" @disabled(!$canSave) @if (!$canSave) data-locked="1" @endif>
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
