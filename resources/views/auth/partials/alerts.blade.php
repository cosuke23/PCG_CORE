{{-- Flash and validation messages for sign-in pages. --}}
@foreach (['registered', 'success'] as $key)
    @if (session($key))
        <div class="pcg-alert pcg-alert--success" role="status">{{ session($key) }}</div>
    @endif
@endforeach

@if (session('error'))
    <div class="pcg-alert pcg-alert--danger" role="alert" id="form-errors">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="pcg-alert pcg-alert--danger" role="alert" @unless (session('error')) id="form-errors" @endunless>
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
