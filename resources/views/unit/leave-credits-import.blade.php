@extends('layouts.unit')

@section('title', 'Import leave credits')

@section('content')
    <div class="pcg-page">
        <div class="uc-head">
            <h2>{{ empty($result) ? 'Set opening balances from the leave cards' : 'Check the rows, then import' }}</h2>
            <p>Import the ordinary leave credits of many members at once from a CSV file.</p>
        </div>
        @include('partials.leave-credit-import')
    </div>
@endsection
