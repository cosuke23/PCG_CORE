@extends('layouts.personnel')

@section('title', 'Android app')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                @if (!$eligible)
                    <h2>Submit your SOI to get the Android app</h2>
                    <p>The PCG HRMIS app is available to personnel whose Statement of Information is submitted.</p>
                @elseif ($apk === null)
                    <h2>The Android app is being prepared</h2>
                    <p>Your SOI is submitted, so you can download the app as soon as it is published here.</p>
                @else
                    <h2>The PCG HRMIS Android app is ready for you</h2>
                    <p>Your SOI is submitted. Download the app to open your records on your phone.</p>
                @endif
            </div>
        </div>

        <div class="pcg-grid">
            <div>
                @if (!$eligible)
                    <section class="pcg-card" aria-labelledby="app-locked-heading">
                        <div class="pcg-card__header"><h3 id="app-locked-heading">Why the download is not shown yet</h3></div>
                        <div class="pcg-card__body">
                            <p>Your SOI has not been submitted. Complete all six steps of your SOI profile, then answer
                                <strong>Yes</strong> to "Is the data you entered correct?" on the last step. The download
                                appears on this page right after.</p>
                            <p><a class="pcg-btn pcg-btn--cta" href="{{ route('profile') }}">
                                <i class="fa-solid fa-id-card" aria-hidden="true"></i> Open my SOI profile
                            </a></p>
                        </div>
                    </section>
                @elseif ($apk === null)
                    <section class="pcg-card" aria-labelledby="app-pending-heading">
                        <div class="pcg-card__header"><h3 id="app-pending-heading">Not published yet</h3></div>
                        <p class="pcg-empty">There is no app file to download yet. Please check this page again later.</p>
                    </section>
                @else
                    <section class="pcg-card pcg-mb" aria-labelledby="app-download-heading">
                        <div class="pcg-card__header"><h3 id="app-download-heading">Download</h3></div>
                        <div class="pcg-card__body">
                            <dl class="pcg-dl">
                                <div><dt>App</dt><dd>PCG HRMIS for Android 7.0 or newer</dd></div>
                                @if ($apk['version'] !== '')
                                    <div><dt>Version</dt><dd class="pcg-mono">{{ $apk['version'] }}</dd></div>
                                @endif
                                <div><dt>Size</dt><dd>{{ $apk['size_mb'] }} MB</dd></div>
                                <div><dt>Published</dt><dd>{{ $apk['updated_at']->format('M d, Y') }}</dd></div>
                            </dl>
                            <p><a class="pcg-btn pcg-btn--cta" href="{{ route('mobile.app.download') }}" data-pcg-no-busy>
                                <i class="fa-solid fa-download" aria-hidden="true"></i> Download the Android app
                            </a></p>
                            <p class="pcg-table__muted">For your own use only. Do not share the file; colleagues download it here once their SOI is submitted.</p>
                        </div>
                    </section>

                    <section class="pcg-card" aria-labelledby="app-install-heading">
                        <div class="pcg-card__header"><h3 id="app-install-heading">How to install it</h3></div>
                        <div class="pcg-card__body">
                            <ol>
                                <li>Open this page on your Android phone and tap <strong>Download the Android app</strong>.</li>
                                <li>Open the downloaded file. If your phone asks, allow your browser or Files app to
                                    <strong>install unknown apps</strong> (this is needed because the app is not from the Play Store).</li>
                                <li>Tap <strong>Install</strong>, then open <strong>PCG HRMIS</strong> and sign in with your usual email address and password.</li>
                            </ol>
                            <p class="pcg-table__muted">The app shows the same records as this website. When a newer version is published here, download and install it the same way; your sign-in is kept.</p>
                        </div>
                    </section>
                @endif
            </div>

            <section class="pcg-card" aria-labelledby="app-about-heading">
                <div class="pcg-card__header"><h3 id="app-about-heading">What the app does</h3></div>
                <ul class="pcg-list">
                    <li><strong>Your records on your phone</strong><span class="pcg-table__muted">SOI, required documents, 201 files and training.</span></li>
                    <li><strong>Upload from your phone</strong><span class="pcg-table__muted">Pick a photo or file for your documents.</span></li>
                    <li><strong>Same sign-in and security</strong><span class="pcg-table__muted">It connects only to PCG HRMIS over an encrypted connection.</span></li>
                </ul>
            </section>
        </div>
    </div>
@endsection
