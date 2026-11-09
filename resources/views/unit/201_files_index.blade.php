@extends('layouts.unit')

@section('title', '201 file review' . (session('unit_role') === \App\Models\UnitUser::ROLE_M1 ? ' · all units' : ''))

@section('content')
    @include('unit.partials.review', ['allUnits' => $allUnits, 'mode' => '201'])
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/unit-checker.js') }}"></script>
@endsection
