@extends('layouts.admin')

@section('title', 'Personnel')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Personnel</h2>
                <p>
                    @if ($scopeUnit)
                        Personnel currently assigned to <strong>{{ $scopeUnit->code }}</strong> and its subordinate units.
                    @else
                        All personnel records (organisation-wide access).
                    @endif
                    One record per person, identified by serial number.
                </p>
            </div>
            <a class="pcg-btn pcg-btn--primary" href="{{ route('admin.personnel.create') }}">Add personnel</a>
        </div>

        @if ($unassignedCount)
            <div class="pcg-alert pcg-alert--warning" role="status">
                {{ $unassignedCount }} {{ \Illuminate\Support\Str::plural('record', $unassignedCount) }} have no current assignment and are visible only to organisation-wide admins.
                <a href="{{ route('admin.personnel.index', ['unassigned' => 1]) }}">Show them</a>
            </div>
        @endif

        <section class="pcg-card">
            <div class="pcg-card__body">
                <form method="GET" action="{{ route('admin.personnel.index') }}" class="pcg-filters" role="search">
                    <div class="pcg-field">
                        <label class="pcg-label" for="q">Name or serial number</label>
                        <input class="pcg-input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100">
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="unit">Unit (incl. subordinate units)</label>
                        <select class="pcg-input" id="unit" name="unit">
                            <option value="">All</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}" @selected((string) ($filters['unit'] ?? '') === (string) $u->id)>{{ str_repeat('— ', $u->depth) }}{{ $u->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="classification">Classification</label>
                        <select class="pcg-input" id="classification" name="classification">
                            <option value="">All</option>
                            @foreach (\App\Models\Rank::CLASSIFICATIONS as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['classification'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pcg-field">
                        <label class="pcg-label" for="status">Status</label>
                        <select class="pcg-input" id="status" name="status">
                            <option value="">All</option>
                            @foreach (\App\Models\Personnel::STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (!empty($filters['unassigned']))
                        <input type="hidden" name="unassigned" value="1">
                    @endif
                    <div class="pcg-filters__actions">
                        <button type="submit" class="pcg-btn pcg-btn--secondary">Filter</button>
                        <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.personnel.index') }}">Reset</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="pcg-card pcg-mt" aria-labelledby="list-heading">
            <div class="pcg-card__header">
                <h3 id="list-heading">{{ $personnel->total() }} {{ \Illuminate\Support\Str::plural('record', $personnel->total()) }}{{ !empty($filters['unassigned']) ? ' without assignment' : '' }}</h3>
            </div>
            @if ($personnel->isEmpty())
                <p class="pcg-empty">No personnel match. @if (!$scopeUnit) Existing registrations are copied in with <code>php artisan pcg:personnel-sync</code>. @endif</p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Serial number</th>
                                <th scope="col">Rank</th>
                                <th scope="col">Current assignment</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($personnel as $p)
                                <tr>
                                    <td><a href="{{ route('admin.personnel.show', $p->id) }}"><strong>{{ $p->full_name }}</strong></a></td>
                                    <td>{{ $p->serial_number }}</td>
                                    <td>{{ $p->rank?->code ?? '—' }}</td>
                                    <td>
                                        @if ($p->currentAssignment)
                                            {{ $p->currentAssignment->orgUnit->code }}
                                            <span class="pcg-table__muted">since {{ $p->currentAssignment->start_date->format('d M Y') }}</span>
                                        @else
                                            <span class="pcg-badge pcg-badge--warning">Unassigned</span>
                                        @endif
                                    </td>
                                    <td><span class="pcg-badge {{ $p->service_status === 'active' ? 'pcg-badge--success' : '' }}">{{ \App\Models\Personnel::STATUSES[$p->service_status] ?? $p->service_status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pcg-card__body">{{ $personnel->links('admin.partials.pagination') }}</div>
            @endif
        </section>
    </div>
@endsection
