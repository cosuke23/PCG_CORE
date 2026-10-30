{{-- Success / error messages for unit-checker pages (Tailwind-styled). --}}
@if (session('error'))
    <div role="alert" class="mb-6 rounded-xl border border-red-200 border-l-4 border-l-red-600 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div role="alert" class="mb-6 rounded-xl border border-red-200 border-l-4 border-l-red-600 bg-red-50 px-4 py-3 text-sm text-red-800">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif
