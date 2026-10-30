@extends('layouts.admin')

@section('title', 'HRMIS overview')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $root ? $root->code . ' personnel overview' : 'PCG personnel overview' }}</h2>
                <p>
                    Figures from the personnel master{{ $root ? ', limited to ' . $root->name . ' and its units' : ' (organisation-wide)' }}.
                    As of {{ now()->format('d M Y, H:i') }}.
                </p>
            </div>
            <a class="pcg-btn pcg-btn--secondary" href="{{ route('admin.personnel.index') }}">Open personnel list</a>
        </div>

        @if ($total === 0)
            <section class="pcg-card">
                <div class="pcg-card__body">
                    <p class="pcg-empty">
                        No personnel records {{ $root ? 'are assigned to ' . $root->code . ' yet' : 'yet' }}.
                        @if ($orgWide)
                            Copy approved registrations with <code>php artisan pcg:personnel-sync</code> (dry run first), or <a href="{{ route('admin.personnel.create') }}">add a record</a>.
                        @endif
                    </p>
                </div>
            </section>
        @endif

        <div class="pcg-stats">
            <div class="pcg-stat">
                <span class="pcg-stat__label">Personnel</span>
                <span class="pcg-stat__value">{{ number_format($total) }}</span>
                <span class="pcg-stat__hint">{{ $orgWide ? 'all records' : 'currently assigned in scope' }}</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">Officers</span>
                <span class="pcg-stat__value">{{ number_format($officers) }}</span>
                <span class="pcg-stat__hint">{{ number_format($nonOfficers) }} non-officers{{ $total - $officers - $nonOfficers > 0 ? ', ' . ($total - $officers - $nonOfficers) . ' not classified' : '' }}</span>
            </div>
            <div class="pcg-stat">
                <span class="pcg-stat__label">Movements, last 30 days</span>
                <span class="pcg-stat__value">{{ number_format($movementCount) }}</span>
                <span class="pcg-stat__hint">assignments that started</span>
            </div>
            <a class="pcg-stat pcg-stat--warning pcg-stat--link" href="{{ route('admin.personnel.index', ['incomplete' => 1]) }}">
                <span class="pcg-stat__label">Records needing attention</span>
                <span class="pcg-stat__value">{{ number_format($incomplete) }}</span>
                <span class="pcg-stat__hint">missing rank, birth date or service entry date</span>
            </a>
            @if ($orgWide)
                <a class="pcg-stat pcg-stat--warning pcg-stat--link" href="{{ route('admin.personnel.index', ['unassigned' => 1]) }}">
                    <span class="pcg-stat__label">Without assignment</span>
                    <span class="pcg-stat__value">{{ number_format($unassigned) }}</span>
                    <span class="pcg-stat__hint">not visible to command HR</span>
                </a>
                @if ($pendingRegistrations !== null)
                    <a class="pcg-stat pcg-stat--link" href="{{ route('admin.dashboard') }}">
                        <span class="pcg-stat__label">Pending registrations</span>
                        <span class="pcg-stat__value">{{ number_format($pendingRegistrations) }}</span>
                        <span class="pcg-stat__hint">awaiting approval</span>
                    </a>
                @endif
            @endif
        </div>

        <div class="pcg-grid pcg-grid--even">
            <section class="pcg-card" aria-labelledby="unit-heading">
                <div class="pcg-card__header"><h3 id="unit-heading">Personnel by {{ $root ? 'unit' : 'command' }}</h3></div>
                @include('admin.hrmis.partials.bars', [
                    'rows' => $byUnit, 'labelHeading' => $root ? 'Unit' : 'Command',
                    'caption' => 'Current personnel per unit, including subordinate units',
                    'empty' => 'No subordinate units yet. Add them under Organization.',
                ])
            </section>

            <section class="pcg-card" aria-labelledby="rank-heading">
                <div class="pcg-card__header"><h3 id="rank-heading">Personnel by rank</h3></div>
                @include('admin.hrmis.partials.bars', [
                    'rows' => $byRank, 'labelHeading' => 'Rank',
                    'caption' => 'Personnel per rank, officers first, most senior first',
                    'empty' => 'No personnel yet.',
                ])
            </section>
        </div>

        <section class="pcg-card pcg-mt" aria-labelledby="moves-heading">
            <div class="pcg-card__header"><h3 id="moves-heading">Recent movements</h3></div>
            @if ($recentMovements->isEmpty())
                <p class="pcg-empty">No assignments started in the last 30 days.</p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">Personnel</th>
                                <th scope="col">To</th>
                                <th scope="col">Effective</th>
                                <th scope="col">Order / reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentMovements as $m)
                                <tr>
                                    <td><a href="{{ route('admin.personnel.show', $m->personnel_id) }}">{{ $m->personnel->rank?->code }} {{ $m->personnel->full_name }}</a></td>
                                    <td>{{ $m->orgUnit->code }}@if ($m->position)<span class="pcg-table__muted">{{ $m->position }}</span>@endif</td>
                                    <td>{{ $m->start_date->format('d M Y') }}</td>
                                    <td>{{ $m->order_reference ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
