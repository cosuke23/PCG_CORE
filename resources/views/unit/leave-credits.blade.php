@extends('layouts.unit')

@section('title', 'Leave credits')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    @php $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d); @endphp
    <div class="pcg-page">
        <div class="uc-head">
            <h2>
                @if ($notSet)
                    {{ $notSet === 1 ? '1 member on this page has' : $notSet . ' members on this page have' }} no opening balance yet
                @else
                    Leave credits of {{ $members->total() === 1 ? '1 member' : $members->total() . ' members' }}
                @endif
            </h2>
            <p>Set each member's opening balance of ordinary leave credits once; from then 2.5 days are added every month.
                Add RNR days after a deployment or sea duty. Days are taken off when you approve leave.</p>
        </div>

        <form method="GET" action="{{ route('unit.leave.credits') }}" class="lc-filter pcg-mb">
            @if ($isM1)
                <label class="pcg-label" for="unit-filter">Unit</label>
                <select class="pcg-input pcg-input--sm" id="unit-filter" name="unit">
                    <option value="">All units</option>
                    @foreach ($units as $u)
                        <option value="{{ $u }}" @selected(strcasecmp($unitFilter, $u) === 0)>{{ $u }}</option>
                    @endforeach
                </select>
            @endif
            <label class="pcg-label" for="q">Name or serial number</label>
            <input class="pcg-input pcg-input--sm" type="search" id="q" name="q" value="{{ $q }}" maxlength="100" autocomplete="off">
            <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm" data-pcg-no-busy>Search</button>
            <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('unit.leave.credits.import') }}"><i class="fa-solid fa-file-import" aria-hidden="true"></i> Import from CSV</a>
        </form>

        <section class="pcg-card">
            @if ($members->isEmpty())
                <p class="pcg-empty">No members found.</p>
            @else
                <table class="pcg-table">
                    <thead>
                        <tr>
                            <th scope="col">Member</th>
                            @if ($isM1)<th scope="col">Unit</th>@endif
                            @foreach ($pools as $p)<th scope="col" class="lcr-num">{{ $p['short'] }}</th>@endforeach
                            <th scope="col"><span class="pcg-visually-hidden">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $m)
                            <tr>
                                <td>
                                    <strong>{{ trim(($m->rank ?? '') . ' ' . $m->name) }}</strong>
                                    <span class="lc-muted pcg-mono">{{ $m->serial_number }}</span>
                                </td>
                                @if ($isM1)<td>{{ $m->mother_unit }}</td>@endif
                                @foreach ($pools as $key => $p)
                                    @php $c = $m->credits[$key]; @endphp
                                    <td class="lcr-num">
                                        @if (!$c['set'])
                                            <span class="pcg-badge pcg-badge--warning">Not set</span>
                                        @else
                                            {{ $fmt($c['available']) }}@if ($c['held'] > 0)<span class="lc-muted"> ({{ $fmt($c['held']) }} held)</span>@endif
                                        @endif
                                    </td>
                                @endforeach
                                <td><a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('unit.leave.credits.show', $m->id) }}">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="pcg-card__body">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
@endsection
