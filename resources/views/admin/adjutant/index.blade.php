@extends('layouts.admin')

@section('title', 'Adjutant')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Coast Guard awards and schooling</h2>
                <p>
                    Record Coast Guard awards and decorations and Coast Guard schooling in members' Statement of Information.
                    {{ $scopeUnit ? 'Showing approved members assigned to ' . $scopeUnit->name . ' and its units.' : 'Showing every approved member.' }}
                </p>
            </div>
        </div>

        <section class="pcg-card">
            <div class="pcg-card__body">
                <form method="GET" action="{{ route('admin.adjutant.index') }}" class="pcg-filters" role="search">
                    <div class="pcg-field">
                        <label class="pcg-label" for="q">Name or serial number</label>
                        <input class="pcg-input" type="search" id="q" name="q" value="{{ $q }}" maxlength="100" autocomplete="off">
                    </div>
                    <div class="pcg-filters__actions">
                        <button type="submit" class="pcg-btn pcg-btn--primary">Search</button>
                        @if ($q !== '')
                            <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.adjutant.index') }}">Clear</a>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        <section class="pcg-card pcg-mt" aria-labelledby="adj-list">
            <div class="pcg-card__header"><h3 id="adj-list">{{ number_format($members->total()) }} {{ $members->total() === 1 ? 'member' : 'members' }}</h3></div>
            @if ($members->isEmpty())
                <p class="pcg-empty">
                    @if ($q !== '')
                        No approved member matches “{{ $q }}”.
                    @elseif ($scopeUnit)
                        No approved member is assigned to {{ $scopeUnit->code }} in the personnel master yet. Members appear here once HR records their assignment.
                    @else
                        No approved members yet.
                    @endif
                </p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Serial number</th>
                                <th scope="col">Unit</th>
                                <th scope="col">CG awards</th>
                                <th scope="col">CG schooling</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $m)
                                <tr>
                                    <td><a href="{{ route('admin.adjutant.show', $m->id) }}">{{ trim(($m->rank ? $m->rank . ' ' : '') . $m->name) }}</a></td>
                                    <td>{{ $m->serial_number }}</td>
                                    <td>{{ $m->mother_unit ?: '—' }}@if ($m->unit_assignment)<span class="pcg-table__muted">{{ $m->unit_assignment }}</span>@endif</td>
                                    <td>{{ (int) ($awardCounts[$m->serial_number] ?? 0) }}</td>
                                    <td>{{ (int) ($trainingCounts[$m->serial_number] ?? 0) }}</td>
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
