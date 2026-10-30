@extends('layouts.admin')

@section('title', 'Registrations')

@section('content')
    @php $status = $filters['status'] ?? null; @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Personnel registrations</h2>
                <p>New personnel enter the master records only through an approved registration. The approver cannot be the person who prepared or submitted it.</p>
            </div>
            @if (\App\Support\AdminPermissions::can('registration.create'))
                <a class="pcg-btn pcg-btn--primary" href="{{ route('admin.registrations.create') }}">New registration</a>
            @endif
        </div>

        <nav class="pcg-tabs" aria-label="Filter by status">
            <a href="{{ route('admin.registrations.index') }}" @if (!$status) aria-current="page" @endif>All <span>{{ $counts->sum() }}</span></a>
            @foreach (\App\Models\PersonnelRegistration::STATUSES as $key => $label)
                <a href="{{ route('admin.registrations.index', ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </nav>

        <section class="pcg-card pcg-mt">
            <div class="pcg-card__body">
                <form method="GET" class="pcg-filters" role="search">
                    @if ($status) <input type="hidden" name="status" value="{{ $status }}"> @endif
                    <div class="pcg-field">
                        <label class="pcg-label" for="q">Name or serial number</label>
                        <input class="pcg-input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100">
                    </div>
                    <div class="pcg-filters__actions"><button class="pcg-btn pcg-btn--secondary" type="submit">Search</button></div>
                </form>
            </div>
            @if ($registrations->isEmpty())
                <p class="pcg-empty">No registrations{{ $status ? ' with this status' : '' }}.</p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr><th scope="col">Name</th><th scope="col">Serial number</th><th scope="col">Rank</th><th scope="col">Proposed unit</th><th scope="col">Status</th><th scope="col">Updated</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($registrations as $r)
                                <tr>
                                    <td><a href="{{ route('admin.registrations.show', $r->id) }}"><strong>{{ $r->fullName() }}</strong></a></td>
                                    <td>{{ $r->serial_number }}</td>
                                    <td>{{ $r->rank?->code ?? '—' }}</td>
                                    <td>{{ $r->orgUnit->code }}</td>
                                    <td>@include('admin.registrations.partials.status', ['status' => $r->status])</td>
                                    <td>{{ $r->updated_at?->format('d M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pcg-card__body">{{ $registrations->links('admin.partials.pagination') }}</div>
            @endif
        </section>
    </div>
@endsection
