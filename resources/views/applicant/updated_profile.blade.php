@extends('layouts.personnel')

@section('title', 'SOI profile')

@section('styles')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/soi.css') }}">
@endsection

@php
    $dash = '—';
    $show = fn ($v) => filled($v) ? $v : $dash;
    $fullName = $profile
        ? trim(preg_replace('/\s+/', ' ', implode(' ', [$profile->first_name, $profile->middle_name, $profile->last_name, $profile->extension_name])))
        : '';
@endphp

@section('content')
    <div class="pcg-page soi-summary">
        @if ($profile)
            <div class="pcg-page__header">
                <div>
                    <h2>{{ $fullName !== '' ? $fullName : $user->name }}: Statement of Information</h2>
                    @php($soiSubmittedAt = \App\Support\SoiLock::submittedAt($user))
                    @if ($soiSubmittedAt)
                        <p>Submitted on <time class="pcg-num">{{ $soiSubmittedAt->timezone('Asia/Manila')->format('d M Y, h:i A') }}</time> and read-only. To change anything, contact your Adjutant or HR Unit Admin.</p>
                    @else
                        <p>Your SOI as saved. Complete the six steps and submit it; once submitted it can no longer be edited.</p>
                    @endif
                </div>
                <div class="soi-summary__actions">
                    <a class="pcg-btn pcg-btn--secondary" href="{{ route('generate.soi') }}">
                        <i class="fa-solid fa-print" aria-hidden="true"></i> Print my SOI
                    </a>
                    @if ($soiSubmittedAt)
                        <a class="pcg-btn pcg-btn--cta" href="{{ route('profile', ['edit' => 1]) }}">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i> View my SOI
                        </a>
                    @else
                        <a class="pcg-btn pcg-btn--cta" href="{{ route('profile', ['edit' => 1]) }}">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit SOI
                        </a>
                    @endif
                </div>
            </div>

            <div class="soi-summary__grid">
                <figure class="pcg-card soi-photo">
                    <img src="{{ $profile->photo ? \App\Support\Uploads::url($profile->photo) : asset('images/no-photo.svg') }}"
                        alt="{{ $profile->photo ? '2x2 photo of ' . $fullName : 'No photo uploaded' }}" width="192" height="192">
                    <figcaption>{{ $profile->photo ? '2x2 photo' : 'No 2x2 photo yet' }}</figcaption>
                </figure>

                <div class="pcg-stack">
                    <section class="pcg-card" aria-labelledby="soi-personal">
                        <div class="pcg-card__header"><h3 id="soi-personal">Personal details</h3></div>
                        <dl class="pcg-dl">
                            <div><dt>Profile code</dt><dd class="pcg-mono">{{ $show($profile->profile_code) }}</dd></div>
                            <div><dt>District</dt><dd>{{ $show($profile->district) }}</dd></div>
                            <div><dt>Full name</dt><dd>{{ $show($fullName) }}</dd></div>
                            <div><dt>Birthday</dt><dd><time class="pcg-num">{{ $profile->birthday ? \Carbon\Carbon::parse($profile->birthday)->format('F d, Y') : $dash }}</time></dd></div>
                            <div><dt>Sex</dt><dd>{{ $show($profile->sex) }}</dd></div>
                        </dl>
                    </section>

                    <section class="pcg-card" aria-labelledby="soi-addresses">
                        <div class="pcg-card__header"><h3 id="soi-addresses">Addresses</h3></div>
                        <div class="soi-summary__pair">
                            @foreach (['perm' => 'Permanent address', 'pres' => 'Present address'] as $prefix => $heading)
                                <section>
                                    <h4>{{ $heading }}</h4>
                                    <dl class="pcg-dl">
                                        <div><dt>Street</dt><dd>{{ $show($profile->{$prefix . '_street'}) }}</dd></div>
                                        <div><dt>City / municipality</dt><dd>{{ $show($profile->{$prefix . '_city'}) }}</dd></div>
                                        <div><dt>Province</dt><dd>{{ $show($profile->{$prefix . '_province'}) }}</dd></div>
                                        <div><dt>ZIP code</dt><dd class="pcg-num">{{ $show($profile->{$prefix . '_zipcode'}) }}</dd></div>
                                        <div><dt>Years of residency</dt><dd class="pcg-num">{{ $show($profile->{$prefix . '_years_residency'}) }}</dd></div>
                                    </dl>
                                </section>
                            @endforeach
                        </div>
                    </section>

                    <section class="pcg-card" aria-labelledby="soi-education">
                        <div class="pcg-card__header"><h3 id="soi-education">Education</h3></div>
                        <dl class="pcg-dl">
                            <div><dt>Highest level</dt><dd>{{ $show($profile->education_level) }}</dd></div>
                            @if ($profile->education_level == 'Tertiary')
                                <div><dt>Tertiary course</dt><dd>{{ $show($profile->tertiary_course) }}</dd></div>
                                <div><dt>Tertiary school</dt><dd>{{ $show($profile->tertiary_school) }}</dd></div>
                                <div><dt>Tertiary year graduated</dt><dd class="pcg-num">{{ $show($profile->tertiary_year_graduated) }}</dd></div>
                            @endif
                            @if (in_array($profile->education_level, ['Secondary', 'Tertiary']))
                                <div><dt>Secondary school</dt><dd>{{ $show($profile->secondary_school) }}</dd></div>
                                <div><dt>Track / strand</dt><dd>{{ $show($profile->secondary_strand) }}</dd></div>
                                <div><dt>Secondary year graduated</dt><dd class="pcg-num">{{ $show($profile->secondary_year_graduated) }}</dd></div>
                            @endif
                            <div><dt>Elementary school</dt><dd>{{ $show($profile->elementary_school) }}</dd></div>
                            <div><dt>Elementary year graduated</dt><dd class="pcg-num">{{ $show($profile->elementary_year_graduated) }}</dd></div>
                        </dl>
                    </section>
                </div>
            </div>
        @else
            <div class="pcg-page__header">
                <div>
                    <h2>You have not started your Statement of Information</h2>
                    <p>The SOI has six short steps. Each step is saved as you go.</p>
                </div>
            </div>
            <div class="pcg-card pcg-empty">
                <a class="pcg-btn pcg-btn--cta" href="{{ route('profile', ['edit' => 1]) }}">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Start my SOI
                </a>
            </div>
        @endif
    </div>
@endsection
