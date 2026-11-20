@extends('layouts.landing')

@section('title', 'Privacy notice · MEPCOM HRMIS')
@section('description', 'How MEPCOM HRMIS collects, uses, protects and keeps personal information, and your rights under the Data Privacy Act of 2012.')

@section('content')
    @php $p = config('pcg.privacy'); @endphp
    <section class="lp-pagehead lp-pagehead--short" aria-labelledby="privacy-title">
        <div class="lp-wrap">
            <p class="lp-eyebrow">Privacy notice · version {{ $p['notice_version'] }}, effective {{ \Illuminate\Support\Carbon::parse($p['notice_date'])->format('F j, Y') }}</p>
            <h1 id="privacy-title">How we handle your personal information.</h1>
            <p class="lp-pagehead__lead">MEPCOM HRMIS is the personnel records system of the Philippine Coast Guard Maritime Environmental Protection Command. This notice explains, in line with the Data Privacy Act of 2012 (Republic Act No. 10173), what we collect, why, who sees it, how long we keep it and what your rights are.</p>
        </div>
    </section>

    <section class="lp-section lp-section--tight" aria-label="Privacy notice">
        <div class="lp-wrap lp-privacy">
            <h2>What we collect</h2>
            <ul>
                <li><strong>Identity and service details:</strong> name, rank, serial number, email, birth date, date of appointment, ETE, command and unit.</li>
                <li><strong>Sign-up photo:</strong> the selfie taken at registration, to confirm that the account belongs to you.</li>
                <li><strong>Statement of Information (SOI):</strong> the personal, family, education, service and award details you enter.</li>
                <li><strong>Required documents and 201 file:</strong> clearances (NBI, Ombudsman, Sandiganbayan), medical and PFT results, photos, sketches and other records you upload, with their review results.</li>
                <li><strong>Residence location:</strong> the map pin you set for your residence.</li>
                <li><strong>Leave and training:</strong> leave requests and credits, MEPTDI classes and certificates.</li>
                <li><strong>Activity records:</strong> sign-ins, downloads and changes, with date, time and network address, kept for security.</li>
            </ul>

            <h2>Why we use it</h2>
            <p>To keep your personnel records, review your documents, manage leave, training and re-enlistment, plan manpower, and protect the system. We process this information because the Coast Guard needs it to perform its mandate and the duties of a government agency under law, and, for the selfie and your residence location, with your consent given at registration.</p>

            <h2>Who can see it</h2>
            <ul>
                <li>You.</li>
                <li>The unit checker of your mother unit, and the M1 checkers (all units), to review your documents and leave.</li>
                <li>HR administrators, records reviewers and approvers, auditors and the Adjutant, each only for the units assigned to them and only the parts their role needs.</li>
            </ul>
            <p>We do not sell your information or share it outside the Philippine Coast Guard, except when the law requires it.</p>

            <h2>How we protect it</h2>
            <ul>
                <li>The system is reachable only over the Coast Guard's private network connection.</li>
                <li>Every uploaded file is encrypted (AES-256) and is shown only after an access check; every download is recorded.</li>
                <li>Staff accounts use a second sign-in step (a code sent to their email or an authenticator app).</li>
                <li>Backups are made every day and checked every week.</li>
            </ul>

            <h2>How long we keep it</h2>
            <p>Personnel records are kept while you are in the service and afterwards for as long as government records rules require. Registrations that are never approved are reviewed for removal after {{ $p['retention']['pending_registration_days'] }} days; activity records are kept for {{ $p['retention']['audit_days'] }} days; messages sent through the contact form for {{ $p['retention']['contact_message_days'] }} days.</p>

            <h2>Your rights</h2>
            <p>Under the Data Privacy Act you have the right to be informed, to access your information, to have errors corrected, to object, to have information erased or blocked when the law allows, to data portability, to claim damages, and to file a complaint with the National Privacy Commission.</p>
            <p>To use these rights, or for any question about this notice, contact the {{ $p['dpo_name'] }}{{ $p['dpo_email'] ? ' at ' : '' }}@if ($p['dpo_email'])<a href="mailto:{{ $p['dpo_email'] }}">{{ $p['dpo_email'] }}</a>@endif, or write to us through the <a href="{{ route('contact') }}">contact page</a>.</p>
        </div>
    </section>
@endsection
