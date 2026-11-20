@extends('layouts.admin')

@section('title', 'Unit check')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $problems === 0 ? 'Every member\'s units agree' : ($problems === 1 ? '1 member\'s units disagree' : "{$problems} members' units disagree") }} in {{ $scopeName }}</h2>
                <p>A member has two units: the one chosen at registration (unit checkers, leave and the member portal use it) and their HRMIS assignment (the admin console uses it). Make them agree with one click; every change is in the audit log and assignment history is kept.@unless ($canFix) Your role can view this report but not change units.@endunless</p>
            </div>
        </div>

        <nav class="lc-filter pcg-mb" aria-label="Show">
            @foreach (['problems' => 'Needs attention', 'mismatch' => null, 'no_assignment' => null, 'unknown_unit' => null, 'no_mother_unit' => null, 'no_personnel' => null, 'ok' => null, 'all' => 'All'] as $k => $label)
                @php $n = $k === 'problems' ? $problems : ($k === 'all' ? $total : ($counts[$k] ?? 0)); @endphp
                @if ($n > 0 || in_array($k, ['problems', 'all'], true))
                    <a class="pcg-btn pcg-btn--sm {{ $state === $k ? 'pcg-btn--primary' : 'pcg-btn--ghost' }}" href="{{ route('admin.unit-check', ['state' => $k]) }}" @if ($state === $k) aria-current="page" @endif>
                        {{ $label ?? \App\Support\UnitMismatch::STATES[$k] }} <span class="pcg-num">{{ $n }}</span>
                    </a>
                @endif
            @endforeach
        </nav>

        <section class="pcg-card">
            @if ($rows->isEmpty())
                <p class="pcg-empty">Nothing to show here.</p>
            @else
                <table class="pcg-table">
                    <thead><tr><th scope="col">Member</th><th scope="col">Sign-up unit</th><th scope="col">HRMIS assignment</th><th scope="col">State</th>@if ($canFix)<th scope="col">Fix</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td><strong>{{ trim(($r->rank ?? '') . ' ' . $r->name) }}</strong><br><span class="pcg-mono">{{ $r->serial_number }}</span></td>
                                <td>{{ $r->mother_unit !== '' ? $r->mother_unit : '—' }}</td>
                                <td>@if ($r->assigned){{ $r->assigned->code }}<br><span class="lc-muted">since {{ \Illuminate\Support\Carbon::parse($r->assigned_since)->format('M j, Y') }}</span>@else — @endif</td>
                                <td><span class="pcg-badge {{ $r->state === 'ok' ? 'pcg-badge--success' : ($r->state === 'mismatch' ? 'pcg-badge--danger' : 'pcg-badge--warning') }}">{{ \App\Support\UnitMismatch::STATES[$r->state] }}</span></td>
                                @if ($canFix)
                                    <td class="um-fix">
                                        @if ($r->can_use_assignment)
                                            <form method="POST" action="{{ route('admin.unit-check.use-assignment', $r->user_id) }}">
                                                @csrf
                                                <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                                    data-pcg-confirm="Set {{ $r->name }}'s sign-up unit to {{ $r->suggested_mother_unit }}? Unit checkers of {{ $r->suggested_mother_unit }} will see their documents and leave{{ $r->mother_unit !== '' ? ', and those of ' . $r->mother_unit . ' will no longer' : '' }}."
                                                    data-pcg-confirm-title="Use the assignment" data-pcg-confirm-ok="Set to {{ $r->suggested_mother_unit }}">
                                                    Set sign-up unit to {{ $r->suggested_mother_unit }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($r->can_use_mother_unit)
                                            <form method="POST" action="{{ route('admin.unit-check.use-mother-unit', $r->user_id) }}">
                                                @csrf
                                                <button type="submit" class="pcg-btn pcg-btn--ghost pcg-btn--sm"
                                                    data-pcg-confirm="Assign {{ $r->name }} to {{ $r->mother_unit }} from today?{{ $r->assigned ? ' The assignment to ' . $r->assigned->code . ' ends yesterday and stays in the history.' : '' }}"
                                                    data-pcg-confirm-title="Assign to the sign-up unit" data-pcg-confirm-ok="Assign to {{ $r->mother_unit }}">
                                                    Assign to {{ $r->mother_unit }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($r->state === 'no_personnel')
                                            <span class="lc-muted">Run <span class="pcg-mono">pcg:personnel-sync</span> first</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
@endsection
