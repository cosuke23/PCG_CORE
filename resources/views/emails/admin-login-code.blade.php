<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:8px;border-top:4px solid #0a1b3f">
        <tr><td style="padding:24px">
            <p style="margin:0 0 4px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280">MEPCOM HRMIS · Administration</p>
            <h1 style="margin:0 0 16px;font-size:20px;color:#0a1b3f">Your sign-in code</h1>
            <p style="margin:0 0 16px;font-size:32px;font-weight:bold;letter-spacing:.2em;color:#0a1b3f">{{ $code }}</p>
            <p style="margin:0 0 8px;font-size:14px">It expires in {{ $minutes }} minutes and works once.</p>
            <p style="margin:0 0 16px;font-size:12px;color:#6b7280">Requested from IP {{ request()->ip() }} at {{ now()->format('d M Y H:i') }}.</p>
            <p style="margin:0;font-size:13px">If you did not try to sign in, do not share this code. Change your password and tell your super admin.</p>
        </td></tr>
    </table>
</body>
</html>
