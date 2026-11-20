@extends('layouts.admin')

@section('title', 'Expiring certificates')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $soon === 1 ? '1 certificate expires' : $soon . ' certificates expire' }} within {{ $days }} days in {{ $scopeName }}{{ $expired ? ', ' . $expired . ' already expired' : '' }}</h2>
                <p>Training certificates (personnel training records) and 201 documents with an end date. Members are emailed and notified 30 days, 7 days and on the day.</p>
            </div>
        </div>
        <form method="GET" action="{{ route('admin.expiring') }}" class="lc-filter pcg-mb" data-pcg-no-busy>
            <label class="pcg-label" for="ex-days">Within</label>
            <select class="pcg-input pcg-input--sm" id="ex-days" name="days">
                @foreach ([30, 60, 90, 180] as $d)
                    <option value="{{ $d }}" @selected($days === $d)>{{ $d }} days</option>
                @endforeach
            </select>
            <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Show</button>
        </form>
        <section class="pcg-card">
            @if ($list->isEmpty())
                <p class="pcg-empty">Nothing expires in this period.</p>
            @else
                <table class="pcg-table">
                    <thead><tr><th scope="col">Member</th><th scope="col">Certificate or document</th><th scope="col">Kind</th><th scope="col">Valid until</th><th scope="col">When</th></tr></thead>
                    <tbody>
                        @foreach ($list as $c)
                            <tr>
                                <td><a href="{{ route('admin.personnel.show', $c->personnel_id) }}"><strong>{{ trim(($c->rank ?? '') . ' ' . $c->name) }}</strong></a><br><span class="pcg-mono">{{ $c->serial_number }}</span></td>
                                <td>{{ $c->title }}</td>
                                <td>{{ $c->source === '201' ? '201 document' : 'Training' }}</td>
                                <td class="lcr-nowrap">{{ \Illuminate\Support\Carbon::parse($c->valid_until)->format('M j, Y') }}</td>
                                <td><span class="pcg-badge {{ $c->days_left < 0 ? 'pcg-badge--danger' : ($c->days_left <= 30 ? 'pcg-badge--warning' : '') }}">{{ \App\Support\ClearanceExpiry::when($c->days_left) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
@endsection
