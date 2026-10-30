{{-- Flash + validation messages for signed-in pages (layouts/admin, layouts/unit). --}}
@php
    $flash = array_filter([
        'success' => session('success') ?? session('swal_success'),
        'danger' => session('error'),
        'warning' => session('warning'),
        'info' => session('info'),
    ]);
@endphp
@if ($flash || $errors->any())
    <div class="pcg-console__flash">
        @foreach ($flash as $type => $message)
            <div class="pcg-alert pcg-alert--{{ $type }}" role="{{ $type === 'danger' ? 'alert' : 'status' }}">{{ $message }}</div>
        @endforeach
        @if ($errors->any())
            <div class="pcg-alert pcg-alert--danger" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
