@extends('layouts.unit')

@section('title', 'Document review')

@section('content')
    @include('unit.partials.review', ['allUnits' => false])
@endsection

@section('scripts')
    <script nonce="{{ $cspNonce ?? '' }}" src="{{ \App\Support\Asset::v('js/unit-checker.js') }}"></script>
@endsection
