@extends('layouts.admin')

@section('title', 'Messages')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Contact messages</h2>
                <p>Sent from the public Contact page. Reply by email; mark messages read or archive them here.</p>
            </div>
            <nav class="pcg-actions" aria-label="Filter messages">
                @foreach (['new' => 'New', 'read' => 'Read', 'archived' => 'Archived', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.messages.index', ['status' => $key]) }}"
                        class="pcg-btn pcg-btn--sm {{ $status === $key ? 'pcg-btn--primary' : 'pcg-btn--secondary' }}"
                        @if ($status === $key) aria-current="page" @endif>
                        {{ $label }}@if ($key !== 'all') ({{ $counts[$key] ?? 0 }})@endif
                    </a>
                @endforeach
            </nav>
        </div>

        <section class="pcg-card">
            @if ($messages->isEmpty())
                <p class="pcg-empty">No {{ $status === 'all' ? '' : $status }} messages.</p>
            @else
                <div class="pcg-table-wrap">
                    <table class="pcg-table">
                        <thead>
                            <tr>
                                <th scope="col">From</th>
                                <th scope="col">Message</th>
                                <th scope="col">Received</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $m)
                                <tr>
                                    <td>
                                        <strong>{{ $m->name }}</strong>
                                        <a class="pcg-table__muted" href="mailto:{{ $m->email }}?subject={{ rawurlencode('Re: ' . ($m->subject ?: 'Your message to MEPCOM HRMIS')) }}">{{ $m->email }}</a>
                                    </td>
                                    <td>
                                        @if ($m->subject)<strong>{{ $m->subject }}</strong><br>@endif
                                        <span style="white-space:pre-line">{{ $m->message }}</span>
                                    </td>
                                    <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('M d, Y H:i') }}</td>
                                    <td>
                                        <div class="pcg-actions">
                                            @foreach (['read' => 'Mark read', 'archived' => 'Archive', 'new' => 'Mark new'] as $to => $label)
                                                @if ($m->status !== $to)
                                                    <form method="POST" action="{{ route('admin.messages.status', $m->id) }}">
                                                        @csrf
                                                        <input type="hidden" name="status" value="{{ $to }}">
                                                        <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">{{ $label }}</button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pcg-card__body">{{ $messages->links('pagination::simple-default') }}</div>
            @endif
        </section>
    </div>
@endsection
