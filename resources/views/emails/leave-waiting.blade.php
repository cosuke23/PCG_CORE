<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;border-top:4px solid #fcd116">
        <tr><td style="padding:24px">
            <p style="margin:0 0 4px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280">{{ $system }}</p>
            <h1 style="margin:0 0 16px;font-size:20px;color:#0a1b3f">{{ count($requests) }} leave {{ count($requests) === 1 ? 'request is' : 'requests are' }} waiting for your approval</h1>
            <p style="margin:0 0 16px;font-size:14px">Hello {{ $name }}, these requests in {{ $scope }} have waited more than a day or two:</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:14px;border-collapse:collapse">
                @foreach ($requests as $r)
                    <tr>
                        <td style="padding:8px 0;border-top:1px solid #e5e7eb"><strong>{{ $r['name'] }}</strong><br><span style="color:#6b7280">{{ $r['type'] }} · {{ $r['dates'] }}</span></td>
                        <td style="padding:8px 0;border-top:1px solid #e5e7eb;text-align:right;color:#6b7280;white-space:nowrap">waiting {{ $r['waiting'] }} {{ $r['waiting'] === 1 ? 'day' : 'days' }}</td>
                    </tr>
                @endforeach
            </table>
            <p style="margin:0 0 20px"><a href="{{ $url }}" style="display:inline-block;padding:10px 18px;background:#0a1b3f;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold">Review leave</a></p>
            <p style="margin:0;font-size:13px;color:#6b7280">You get this email once a day while requests are waiting.</p>
        </td></tr>
    </table>
</body>
</html>
