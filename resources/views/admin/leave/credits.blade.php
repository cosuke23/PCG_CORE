@extends('layouts.admin')

@section('title', 'Leave credits')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/leave-calendar.css') }}">
@endsection

@section('content')
    @php $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d); @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>
                    @if ($notSet)
                        {{ $notSet === 1 ? '1 member on this page has' : $notSet . ' members on this page have' }} no opening balance yet
                    @else
                        Leave credits of {{ number_format($members->total()) }} {{ $members->total() === 1 ? 'member' : 'members' }}
                    @endif
                </h2>
                <p>Set each member's opening balance of ordinary leave credits once; from then 2.5 days are added every month. Add RNR days after a deployment or sea duty. Days are taken off when leave is approved.</p>
            </div>
            <div class="lc-review__actions">
                @if (\App\Support\AdminPermissions::can('leave.credits'))
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.leave.credits.import') }}"><i class="fa-solid fa-file-import" aria-hidden="true"></i> Import from CSV</a>
                @endif
                <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.leave.index', array_filter(['unit' => $unit])) }}"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Leave requests</a>
            </div>
        </div>

        <section class="pcg-card pcg-mb">
            <div class="pcg-card__body">
                <form method="GET" action="{{ route('admin.leave.credits') }}" class="pcg-filters" role="search" data-pcg-no-busy>
                    <div class="pcg-field">
                        <label class="pcg-label" for="unit">Unit</label>
                        <select class="pcg-input" id="unit" name="unit">
                            <option value="">All units</option>
                            @foreach ($units as $u)
                                <option value="{{ $u }}" @selected(strcasecmp($unit, $u) === 0)>{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="q">Name or serial number</label>
                        <input class="pcg-input" type="search" id="q" name="q" value="{{ $q }}" maxlength="100" autocomplete="off">
                    </div>
                    <div class="pcg-filters__actions">
                        <button type="submit" class="pcg-btn pcg-btn--secondary">Show</button>
                        @if ($unit !== '' || $q !== '')
                            <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.leave.credits') }}">Clear</a>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        <section class="pcg-card">
            @if ($members->isEmpty())
                <p class="pcg-empty">No members found.</p>
            @else
                <table class="pcg-table">
                    <thead>
                        <tr>
                            <th scope="col">Member</th>
                            <th scope="col">Unit</th>
                            @foreach ($pools as $p)<th scope="col" class="lcr-num">{{ $p['short'] }}</th>@endforeach
                            <th scope="col"><span class="pcg-visually-hidden">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $m)
                            <tr>
                                <td><strong>{{ trim(($m->rank ?? '') . ' ' . $m->name) }}</strong> <span class="lc-muted pcg-mono">{{ $m->serial_number }}</span></td>
                                <td>{{ $m->mother_unit }}</td>
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
                                <td><a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('admin.leave.credits.show', $m->id) }}">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="pcg-card__body">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
@endsection
