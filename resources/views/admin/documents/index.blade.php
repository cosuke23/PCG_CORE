@extends('layouts.admin')

@section('title', '201 documents')

@section('content')
    @php $status = $filters['status'] ?? null; $canVerify = \App\Support\AdminPermissions::can('documents.verify'); @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>201 documents</h2>
                <p>Current versions of personnel documents in your scope. Upload documents from a person's record.</p>
            </div>
        </div>

        <nav class="pcg-tabs" aria-label="Filter by status">
            <a href="{{ route('admin.documents.index') }}" @if (!$status && empty($filters['expiry'])) aria-current="page" @endif>All <span>{{ $counts->sum() }}</span></a>
            @foreach (\App\Models\PersonnelDocument::STATUSES as $key => $label)
                <a href="{{ route('admin.documents.index', ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
            <a href="{{ route('admin.documents.index', ['expiry' => 'soon']) }}" @if (($filters['expiry'] ?? null) === 'soon') aria-current="page" @endif>Expiring in 60 days</a>
            <a href="{{ route('admin.documents.index', ['expiry' => 'expired']) }}" @if (($filters['expiry'] ?? null) === 'expired') aria-current="page" @endif>Expired</a>
        </nav>

        <section class="pcg-card pcg-mt">
            @if ($documents->isEmpty())
                <p class="pcg-empty">No documents match.</p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead><tr><th scope="col">Personnel</th><th scope="col">Document</th><th scope="col">Status</th><th scope="col">Expires</th><th scope="col">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($documents as $d)
                                <tr>
                                    <td><a href="{{ route('admin.personnel.show', $d->personnel_id) }}#documents">{{ $d->personnel->rank?->code }} {{ $d->personnel->full_name }}</a>
                                        <span class="pcg-table__muted">{{ $d->personnel->serial_number }}</span></td>
                                    <td><strong>{{ $d->title }}</strong><span class="pcg-table__muted">{{ $d->category->name }} · v{{ $d->version }}</span></td>
                                    <td>@include('admin.documents.partials.status', ['doc' => $d])</td>
                                    <td>{{ $d->expires_on?->format('d M Y') ?? '—' }}</td>
                                    <td>
                                        <div class="pcg-actions">
                                            <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('admin.documents.download', $d->id) }}">Download</a>
                                            @if ($canVerify && $d->status === 'pending')
                                                @include('admin.documents.partials.verify', ['doc' => $d])
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pcg-card__body">{{ $documents->links('admin.partials.pagination') }}</div>
            @endif
        </section>
    </div>
@endsection
