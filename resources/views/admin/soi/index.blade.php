@extends('layouts.admin')

@section('title', 'SOI submissions')

@section('content')
    @php
        $tab = fn ($v) => route('admin.soi.index', array_filter(['show' => $v, 'q' => $q]));
    @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ number_format($counts['submitted']) }} {{ $counts['submitted'] === 1 ? 'member has' : 'members have' }} submitted their SOI</h2>
                <p>A submitted SOI is read-only for the member. Reopen one, with the reason, when something must be corrected; the member then corrects it and submits again. Every reopening is in the audit log.</p>
            </div>
        </div>

        <section class="pcg-card">
            <div class="pcg-card__body">
                <form method="GET" action="{{ route('admin.soi.index') }}" class="pcg-filters" role="search" data-pcg-no-busy>
                    <input type="hidden" name="show" value="{{ $show }}">
                    <div class="pcg-field">
                        <label class="pcg-label" for="q">Name or serial number</label>
                        <input class="pcg-input" type="search" id="q" name="q" value="{{ $q }}" maxlength="100" autocomplete="off">
                    </div>
                    <div class="pcg-filters__actions">
                        <button type="submit" class="pcg-btn pcg-btn--secondary">Search</button>
                        @if ($q !== '')
                            <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.soi.index', ['show' => $show]) }}">Clear</a>
                        @endif
                    </div>
                </form>
                <nav class="pcg-tabs pcg-mt" aria-label="Show">
                    <a href="{{ $tab('submitted') }}" @if ($show === 'submitted') aria-current="page" @endif>Submitted <span class="pcg-num">{{ $counts['submitted'] }}</span></a>
                    <a href="{{ $tab('open') }}" @if ($show === 'open') aria-current="page" @endif>Not submitted <span class="pcg-num">{{ $counts['open'] }}</span></a>
                    <a href="{{ $tab('all') }}" @if ($show === 'all') aria-current="page" @endif>All</a>
                </nav>
            </div>
        </section>

        <section class="pcg-card pcg-mt" aria-labelledby="soi-list">
            <div class="pcg-card__header"><h3 id="soi-list">{{ number_format($members->total()) }} {{ $members->total() === 1 ? 'member' : 'members' }}</h3></div>
            @if ($members->isEmpty())
                <p class="pcg-empty">
                    {{ $q !== '' ? 'No member matches “' . $q . '”.' : ($show === 'submitted' ? 'No member in your scope has submitted an SOI yet.' : 'No member to show.') }}
                </p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">Member</th>
                                <th scope="col">Unit</th>
                                <th scope="col">SOI</th>
                                <th scope="col"><span class="pcg-visually-hidden">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $m)
                                <tr @class(['pcg-flash-row' => session('reopened') === $m->id])>
                                    <td><strong>{{ trim(($m->rank ? $m->rank . ' ' : '') . $m->name) }}</strong><span class="pcg-table__muted pcg-mono">{{ $m->serial_number }}</span></td>
                                    <td>{{ $m->mother_unit ?: '—' }}</td>
                                    <td>
                                        @if ($m->soi_submitted_at)
                                            <span class="pcg-badge pcg-badge--success">Submitted</span>
                                            <span class="pcg-table__muted"><time class="pcg-num">{{ \Carbon\Carbon::parse($m->soi_submitted_at)->timezone('Asia/Manila')->format('d M Y, h:i A') }}</time></span>
                                        @else
                                            <span class="pcg-badge">Not submitted</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($m->soi_submitted_at)
                                            <form method="POST" action="{{ route('admin.soi.reopen', $m->id) }}" class="pcg-inline-form">
                                                @csrf
                                                <label class="pcg-visually-hidden" for="reason-{{ $m->id }}">Reason for reopening the SOI of {{ $m->name }}</label>
                                                <input class="pcg-input pcg-input--sm" id="reason-{{ $m->id }}" name="reason" required minlength="5" maxlength="500" placeholder="Reason, e.g. wrong date of appointment">
                                                <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                                    data-pcg-confirm="Reopen the SOI of {{ $m->name }}? They will be able to edit it and must submit it again.">
                                                    <i class="fa-solid fa-lock-open" aria-hidden="true"></i> Reopen
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pcg-card__body">{{ $members->links() }}</div>
            @endif
        </section>
    </div>
@endsection
