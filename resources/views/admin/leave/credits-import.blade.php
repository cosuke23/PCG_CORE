@extends('layouts.admin')

@section('title', 'Import leave credits')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ empty($result) ? 'Set opening balances from the leave cards' : 'Check the rows, then import' }}</h2>
                <p>Import the ordinary leave credits of many members at once from a CSV file.</p>
            </div>
        </div>
        @include('partials.leave-credit-import')
    </div>
@endsection
