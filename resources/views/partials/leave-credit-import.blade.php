{{-- Opening balances from a CSV: upload, preview, confirm (App\Support\LeaveCreditImport).
     Params: $uploadAction, $confirmAction, $templateUrl, $backUrl, optional $result + $token --}}
@php $fmt = fn ($d) => \App\Support\LeaveCredits::fmt($d); @endphp
<p><a href="{{ $backUrl }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Leave credits</a></p>

@if (empty($result))
    <section class="pcg-card pcg-mb" aria-labelledby="imp-heading">
        <div class="pcg-card__header"><h3 id="imp-heading">Upload the leave cards</h3></div>
        <div class="pcg-card__body">
            <ol class="lcr-steps">
                <li><a href="{{ $templateUrl }}" data-pcg-no-busy><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Download the template</a>:
                    every member you can manage, with their current balance where it is set.</li>
                <li>Fill in <strong>days</strong> (the ordinary leave credits on the leave card) and <strong>as_of</strong> (the date of that balance, YYYY-MM-DD or MM/DD/YYYY). Rows you leave empty are not changed; current_balance is only there to help you.</li>
                <li>Upload the file. You see every row checked first; nothing is saved until you confirm.</li>
            </ol>
            <form method="POST" action="{{ $uploadAction }}" enctype="multipart/form-data" class="lc-form">
                @csrf
                <div class="pcg-field">
                    <label class="pcg-label" for="imp-file">CSV file (up to 1 MB)</label>
                    <input class="pcg-input" type="file" id="imp-file" name="file" accept=".csv,text/csv" required>
                </div>
                <button type="submit" class="pcg-btn pcg-btn--primary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Check the file</button>
            </form>
            <p class="lc-muted">An opening balance replaces the member's earlier one; from its date, {{ $fmt(\App\Support\LeaveCredits::monthly()) }} days are added every month.</p>
        </div>
    </section>
@else
    <section class="pcg-card pcg-mb" aria-labelledby="imp-prev-heading">
        <div class="pcg-card__header">
            <h3 id="imp-prev-heading">{{ $result['ok'] }} {{ $result['ok'] === 1 ? 'row is' : 'rows are' }} ready to import{{ $result['errors'] ? ', ' . $result['errors'] . ' ' . ($result['errors'] === 1 ? 'needs' : 'need') . ' fixing' : '' }}{{ !empty($result['skipped']) ? ' (' . $result['skipped'] . ' left blank)' : '' }}</h3>
        </div>
        <div class="pcg-card__body">
            @if ($result['ok'])
                <form method="POST" action="{{ $confirmAction }}" class="lc-review__actions pcg-mb">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button type="submit" class="pcg-btn pcg-btn--primary"
                            data-pcg-confirm="Import {{ $result['ok'] }} opening {{ $result['ok'] === 1 ? 'balance' : 'balances' }}? Each replaces that member's earlier opening balance."
                            data-pcg-confirm-title="Import opening balances" data-pcg-confirm-ok="Import">
                        <i class="fa-solid fa-file-import" aria-hidden="true"></i> Import {{ $result['ok'] }} {{ $result['ok'] === 1 ? 'row' : 'rows' }}
                    </button>
                    <a class="pcg-btn pcg-btn--ghost" href="{{ $uploadAction }}">Upload another file</a>
                </form>
            @else
                <p><a class="pcg-btn pcg-btn--secondary" href="{{ $uploadAction }}">Upload a corrected file</a></p>
            @endif
            <table class="pcg-table">
                <thead><tr><th scope="col">Line</th><th scope="col">Member</th><th scope="col" class="lcr-num">Now</th><th scope="col" class="lcr-num">New</th><th scope="col">As of</th><th scope="col">Check</th></tr></thead>
                <tbody>
                    @foreach ($result['rows'] as $r)
                        <tr>
                            <td class="lcr-num">{{ $r['line'] }}</td>
                            <td>{{ $r['name'] ?? '' }} <span class="lc-muted pcg-mono">{{ $r['serial'] }}</span></td>
                            <td class="lcr-num">{{ $r['error'] === null ? ($r['current'] === null ? 'not set' : $fmt($r['current'])) : '' }}</td>
                            <td class="lcr-num">{{ $r['days'] !== null ? $fmt($r['days']) : '' }}</td>
                            <td class="lcr-nowrap">{{ $r['as_of'] ? \Illuminate\Support\Carbon::parse($r['as_of'])->format('M j, Y') : '' }}</td>
                            <td>
                                @if ($r['error'] === null)
                                    <span class="pcg-badge pcg-badge--success">Ready</span>
                                @else
                                    <span class="pcg-badge pcg-badge--danger">{{ $r['error'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
