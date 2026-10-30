{{-- Unit-checker navigation (replaces placeholder "#" links). M1 items only for M1 checkers. --}}
@php
    $isM1 = session('unit_role') === \App\Models\UnitUser::ROLE_M1;
    $item = 'flex items-center gap-3 px-4 py-3 rounded-xl transition';
    $idle = 'text-gray-600 hover:bg-gray-100';
    $current = 'bg-indigo-100 text-indigo-600 font-semibold';
@endphp
<nav class="px-4 space-y-2" aria-label="Unit checker">
    @if ($isM1)
        <a href="{{ route('unit.dashboard.m1') }}" class="{{ $item }} {{ request()->routeIs('unit.dashboard.m1') ? $current : $idle }}"
            @if (request()->routeIs('unit.dashboard.m1')) aria-current="page" @endif>
            <span aria-hidden="true">✔</span><span>Document review · all units</span>
        </a>
        <a href="{{ route('unit.201files') }}" class="{{ $item }} {{ request()->routeIs('unit.201files') ? $current : $idle }}"
            @if (request()->routeIs('unit.201files')) aria-current="page" @endif>
            <span aria-hidden="true">📁</span><span>201 files</span>
        </a>
    @else
        <a href="{{ route('unit.dashboard') }}" class="{{ $item }} {{ request()->routeIs('unit.dashboard') ? $current : $idle }}"
            @if (request()->routeIs('unit.dashboard')) aria-current="page" @endif>
            <span aria-hidden="true">✔</span><span>Document review</span>
        </a>
    @endif
    <p class="px-4 pt-2 text-xs text-gray-400">
        {{ session('unit_full_name') }}<br>{{ $isM1 ? 'M1 checker · all units' : 'Unit: ' . session('unit_name') }}
    </p>
</nav>
