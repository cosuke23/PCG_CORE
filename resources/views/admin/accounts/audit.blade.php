@extends('layouts.admin')

@section('title', 'Audit log')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Audit log</h2>
                <p>Sign-ins (including failed attempts) and every change made in the admin area, newest first.</p>
            </div>
            <form method="GET" action="{{ route('admin.accounts.audit') }}" class="pcg-inline-form">
                <label class="pcg-visually-hidden" for="action">Action starts with</label>
                <input class="pcg-input pcg-input--sm" id="action" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Action, e.g. login">
                <label class="pcg-visually-hidden" for="admin">Admin email</label>
                <input class="pcg-input pcg-input--sm" id="admin" name="admin" value="{{ $filters['admin'] ?? '' }}" placeholder="Admin email">
                <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Filter</button>
            </form>
        </div>

        <section class="pcg-card">
            @if ($entries->isEmpty())
                <p class="pcg-empty">No entries.</p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">When</th>
                                <th scope="col">Admin</th>
                                <th scope="col">Action</th>
                                <th scope="col">Details</th>
                                <th scope="col">Result</th>
                                <th scope="col">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('M d, Y H:i:s') }}</td>
                                    <td>{{ $entry->admin_email ?? '—' }}</td>
                                    <td><code>{{ $entry->action }}</code>
                                        @if ($entry->subject)<span class="pcg-table__muted">{{ $entry->subject }}</span>@endif
                                    </td>
                                    <td><span class="pcg-table__muted">{{ $entry->details }}</span></td>
                                    <td>
                                        @if ($entry->status === null)
                                            —
                                        @elseif ($entry->status < 400)
                                            <span class="pcg-badge pcg-badge--success">{{ $entry->status }}</span>
                                        @else
                                            <span class="pcg-badge pcg-badge--danger">{{ $entry->status }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $entry->ip }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pcg-card__body">{{ $entries->links('pagination::simple-default') }}</div>
            @endif
        </section>
    </div>
@endsection
