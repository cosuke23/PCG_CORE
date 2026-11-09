@extends('layouts.unit')

@section('title', 'Document review · all units')

@section('content')
    @include('unit.partials.review', ['allUnits' => true])

    <div class="pcg-page uc uc-disc">
        <section class="uc-panel" aria-labelledby="uc-disc-title">
            <header>
                <h2 id="uc-disc-title">File a disciplinary action</h2>
                <p class="uc-lede">Find the member by serial number, then upload the signed copies (PDF, JPG or PNG, up to 5 MB each).</p>
            </header>
            <form id="uc-serial-form" class="uc-serial" role="search" data-pcg-no-busy>
                <label class="pcg-label" for="uc-serial-input">Serial number</label>
                <div class="uc-serial__row">
                    <input class="pcg-input" id="uc-serial-input" type="text" autocomplete="off" required>
                    <button type="submit" class="pcg-btn pcg-btn--secondary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Find</button>
                </div>
            </form>
            <div id="uc-serial-result" aria-live="polite"></div>

            <form id="uc-disc-form" method="POST" action="{{ route('disciplinary.store') }}" enctype="multipart/form-data" hidden>
                @csrf
                <input type="hidden" name="serial_number" id="uc-disc-serial">
                <div id="uc-disc-files" class="uc-disc__files">
                    <div class="uc-disc__row">
                        <input class="pcg-input" type="file" name="disciplinary_files[]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-label="Disciplinary action file">
                        <button type="button" class="pcg-btn pcg-btn--ghost pcg-btn--sm" data-uc-remove aria-label="Remove this file"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="uc-disc__actions">
                    <button type="button" class="pcg-btn pcg-btn--secondary" id="uc-disc-add"><i class="fa-solid fa-plus" aria-hidden="true"></i> Another file</button>
                    <button type="submit" class="pcg-btn pcg-btn--cta"><i class="fa-solid fa-upload" aria-hidden="true"></i> Upload</button>
                </div>
            </form>
        </section>
    </div>
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/unit-checker.js') }}"></script>
@endsection
