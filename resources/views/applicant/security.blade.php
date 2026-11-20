@extends('layouts.personnel')

@section('title', 'Sign-in and devices')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>{{ $codeOn ? 'Your account asks for an emailed code at sign-in' : 'Your password alone signs you in' }}</h2>
                <p>Protect your records: add an emailed code to your sign-in, and sign out phones or computers you no longer use.</p>
            </div>
        </div>

        <ul class="pcg-rows" aria-label="Sign-in code">
            <li class="pcg-row">
                <span class="pcg-row__icon pcg-row__icon--gold" aria-hidden="true"><i class="fa-solid fa-envelope-circle-check"></i></span>
                <span class="pcg-row__name">Emailed sign-in code<small>After your password we send a 6-digit code to {{ $maskedEmail }}. It works once and expires after 10 minutes.</small></span>
                <span class="pcg-row__status {{ $codeOn ? 'pcg-row__status--ok' : '' }}">{{ $codeOn ? 'On' : 'Off' }}</span>
                @if ($codeAvailable)
                    <form method="POST" action="{{ route('security.login-code') }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $codeOn ? '0' : '1' }}">
                        <button type="submit" class="pcg-btn {{ $codeOn ? 'pcg-btn--secondary' : 'pcg-btn--action' }}"
                            @if ($codeOn) data-pcg-confirm="Turn off the emailed code? Anyone with your password could then sign in." data-pcg-confirm-title="Turn off the code" data-pcg-confirm-ok="Turn off" data-pcg-confirm-danger="1" @endif>
                            {{ $codeOn ? 'Turn off' : 'Turn on' }}
                        </button>
                    </form>
                @else
                    <span class="lc-muted">Coming soon</span>
                @endif
            </li>
        </ul>

        <h3 class="pcg-section-title">{{ $devices->count() === 1 ? '1 device is signed in' : $devices->count() . ' devices are signed in' }}</h3>
        @if ($devices->isEmpty())
            <p class="pcg-empty">Only this device. The list fills as you sign in.</p>
        @else
            <ul class="pcg-rows" aria-label="Signed-in devices">
                @foreach ($devices as $d)
                    @php $isThis = $d->id === $thisDevice; @endphp
                    <li class="pcg-row">
                        <span class="pcg-row__icon pcg-row__icon--{{ $isThis ? 'teal' : 'blue' }}" aria-hidden="true"><i class="fa-solid fa-{{ str_contains((string) $d->label, 'Android') || str_contains((string) $d->label, 'iPhone') ? 'mobile-screen' : 'laptop' }}"></i></span>
                        <span class="pcg-row__name">{{ $d->label }}<small>Signed in {{ \Illuminate\Support\Carbon::parse($d->created_at)->timezone('Asia/Manila')->format('M j, Y g:i A') }} · network address {{ $d->ip }}</small></span>
                        <span class="pcg-row__status {{ $isThis ? 'pcg-row__status--ok' : '' }}">{{ $isThis ? 'This device' : 'Last used ' . \Illuminate\Support\Carbon::parse($d->last_seen_at)->diffForHumans() }}</span>
                        @if ($isThis)
                            <span></span>
                        @else
                            <form method="POST" action="{{ route('security.devices.one', $d->id) }}">
                                @csrf
                                <button type="submit" class="pcg-btn pcg-btn--secondary">Sign out</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($devices->count() > 1)
                <form method="POST" action="{{ route('security.devices.others') }}">
                    @csrf
                    <button type="submit" class="pcg-btn pcg-btn--secondary"
                        data-pcg-confirm="Sign out every device except this one? They will need your password (and code) again." data-pcg-confirm-title="Sign out everywhere else" data-pcg-confirm-ok="Sign out others">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign out everywhere else
                    </button>
                </form>
            @endif
        @endif
        <p class="pcg-help">If you see a device you do not know, sign it out and change your password. The Android app keeps its phone alerts until your password changes.</p>
    </div>
@endsection
