@extends('layouts.admin')

@section('title', 'Service certificates')

@section('content')
    @php $n = $counts['pending'] ?? 0; @endphp
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $n === 1 ? '1 certificate request is' : $n . ' certificate requests are' }} waiting in {{ $scopeName }}</h2>
                <p>Members request certificates of service or of present assignment. Issuing one freezes the details from the personnel master (name, rank, unit, dates) and gives it a QR code for the public check.@unless ($canIssue) Your role can view requests but not decide them.@endunless</p>
            </div>
        </div>
        @unless ($ready)
            <p class="pcg-alert pcg-alert--warning">Needs a database update that has not been applied yet.</p>
        @endunless

        <nav class="lc-filter pcg-mb" aria-label="Show">
            @foreach (['pending' => 'Waiting', 'issued' => 'Issued', 'declined' => 'Declined', 'revoked' => 'Revoked', 'all' => 'All'] as $k => $label)
                <a class="pcg-btn pcg-btn--sm {{ $status === $k ? 'pcg-btn--primary' : 'pcg-btn--ghost' }}" href="{{ route('admin.certificates', ['status' => $k]) }}" @if ($status === $k) aria-current="page" @endif>
                    {{ $label }} <span class="pcg-num">{{ $k === 'all' ? $counts->sum() : ($counts[$k] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        <section class="pcg-card">
            @if ($list->isEmpty())
                <p class="pcg-empty">No requests here.</p>
            @else
                <table class="pcg-table">
                    <thead><tr><th scope="col">Member</th><th scope="col">Certificate</th><th scope="col">Requested</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                        @foreach ($list as $c)
                            <tr>
                                <td><strong>{{ trim(($c->rank ?? '') . ' ' . $c->name) }}</strong><br><span class="pcg-mono">{{ $c->serial_number }}</span> · {{ $c->mother_unit }}</td>
                                <td>{{ \App\Support\ServiceCertificates::KINDS[$c->kind] ?? $c->kind }}<br><span class="lc-muted">{{ $c->purpose }}</span></td>
                                <td class="lcr-nowrap">{{ \Illuminate\Support\Carbon::parse($c->created_at)->timezone('Asia/Manila')->format('M j, Y') }}</td>
                                <td>{{ \App\Support\ServiceCertificates::STATUSES[$c->status] ?? $c->status }}@if ($c->code)<br><span class="pcg-mono">{{ $c->code }}</span>@endif @if ($c->decision_note)<br><span class="lc-muted">{{ $c->decision_note }}</span>@endif</td>
                                <td class="um-fix">
                                    @if ($c->status === 'issued')
                                        <a class="pcg-btn pcg-btn--secondary pcg-btn--sm" href="{{ route('admin.certificates.print', $c->id) }}">Print</a>
                                    @endif
                                    @if ($canIssue && $c->status === 'pending')
                                        <form method="POST" action="{{ route('admin.certificates.decide', $c->id) }}" class="lc-review">
                                            @csrf
                                            <input class="pcg-input pcg-input--sm" name="note" maxlength="500" placeholder="Note (needed to decline)" aria-label="Note">
                                            <button type="submit" name="decision" value="issue" class="pcg-btn pcg-btn--primary pcg-btn--sm">Issue</button>
                                            <button type="submit" name="decision" value="decline" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Decline</button>
                                        </form>
                                    @elseif ($canIssue && $c->status === 'issued')
                                        <form method="POST" action="{{ route('admin.certificates.decide', $c->id) }}" class="lc-review">
                                            @csrf
                                            <input class="pcg-input pcg-input--sm" name="note" maxlength="500" placeholder="Why revoke" aria-label="Why revoke">
                                            <button type="submit" name="decision" value="revoke" class="pcg-btn pcg-btn--ghost pcg-btn--sm"
                                                data-pcg-confirm="Revoke this certificate? The QR check will show it as revoked." data-pcg-confirm-danger="1" data-pcg-confirm-ok="Revoke">Revoke</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
@endsection
