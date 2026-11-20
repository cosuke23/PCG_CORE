<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:8px;border-top:4px solid {{ $daysLeft <= 7 ? '#ce1126' : '#0038a8' }}">
        <tr><td style="padding:24px">
            <p style="margin:0 0 4px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280">{{ $system }}</p>
            <h1 style="margin:0 0 16px;font-size:20px;color:#0a1b3f">Your {{ $label }} {{ $daysLeft <= 0 ? 'expires today' : 'expires in ' . $daysLeft . ($daysLeft === 1 ? ' day' : ' days') }}</h1>
            <p style="margin:0 0 16px;font-size:14px">Hello {{ $name }},</p>
            <p style="margin:0 0 16px;font-size:14px">The {{ $label }} in your required documents is valid until <strong>{{ \Illuminate\Support\Carbon::parse($validUntil)->format('F j, Y') }}</strong>.
                Get a new one and upload it with its new valid-until date{{ $daysLeft > 0 ? ' once it has expired; the box opens again the day after' : '; the box opens again tomorrow' }}.</p>
            <p style="margin:0 0 20px"><a href="{{ $url }}" style="display:inline-block;padding:10px 18px;background:#0a1b3f;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold">Open my documents</a></p>
            <p style="margin:0;font-size:13px;color:#6b7280">You get this reminder 30 days, 7 days and on the day a clearance expires.</p>
        </td></tr>
    </table>
</body>
</html>
