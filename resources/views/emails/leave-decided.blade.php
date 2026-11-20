<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:8px;border-top:4px solid {{ $approved ? '#0038a8' : '#ce1126' }}">
        <tr><td style="padding:24px">
            <p style="margin:0 0 4px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280">{{ $system }}</p>
            <h1 style="margin:0 0 16px;font-size:20px;color:#0a1b3f">Your leave is {{ $approved ? 'approved' : 'declined' }}</h1>
            <p style="margin:0 0 16px;font-size:14px">Hello {{ $name }},</p>
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:14px">
                <tr><td style="padding:4px 16px 4px 0;color:#6b7280">Leave</td><td style="padding:4px 0"><strong>{{ $type }}</strong></td></tr>
                <tr><td style="padding:4px 16px 4px 0;color:#6b7280">Dates</td><td style="padding:4px 0">{{ $dates }} ({{ $days }} {{ $days === 1 ? 'day' : 'days' }})</td></tr>
                @if ($note)
                    <tr><td style="padding:4px 16px 4px 0;color:#6b7280;vertical-align:top">Note</td><td style="padding:4px 0">{{ $note }}</td></tr>
                @endif
            </table>
            <p style="margin:0 0 20px"><a href="{{ $url }}" style="display:inline-block;padding:10px 18px;background:#0a1b3f;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold">Open my leave</a></p>
            <p style="margin:0;font-size:13px;color:#6b7280">{{ $approved ? 'It is on your unit\'s leave calendar now.' : 'You can file new dates on the leave page.' }}</p>
        </td></tr>
    </table>
</body>
</html>
