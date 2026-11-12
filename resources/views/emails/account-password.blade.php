<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:8px;border-top:4px solid #0a1b3f">
        <tr><td style="padding:24px">
            <p style="margin:0 0 4px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b7280">{{ $system }}</p>
            <h1 style="margin:0 0 16px;font-size:20px;color:#0a1b3f">{{ $reset ? 'Your password was reset' : 'Your account is ready' }}</h1>
            <p style="margin:0 0 16px;font-size:14px">Hello {{ $name }},</p>
            <p style="margin:0 0 16px;font-size:14px">{{ $reset ? 'An administrator reset the password of your account. Sign in with this temporary password:' : 'An administrator created an account for you. Sign in with these details:' }}</p>
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:14px">
                <tr><td style="padding:4px 16px 4px 0;color:#6b7280">Email</td><td style="padding:4px 0"><strong>{{ $email }}</strong></td></tr>
                <tr><td style="padding:4px 16px 4px 0;color:#6b7280">Temporary password</td><td style="padding:4px 0;font-family:Consolas,Menlo,monospace;font-size:18px;letter-spacing:.06em"><strong>{{ $password }}</strong></td></tr>
            </table>
            <p style="margin:0 0 20px"><a href="{{ $signInUrl }}" style="display:inline-block;padding:10px 18px;background:#0a1b3f;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold">Sign in</a></p>
            <p style="margin:0 0 8px;font-size:14px"><strong>You must choose your own password right after you sign in.</strong> The temporary password stops working then.</p>
            <p style="margin:0;font-size:13px;color:#6b7280">Do not forward this email or share the password. If you did not expect it, tell your super admin.</p>
        </td></tr>
    </table>
</body>
</html>
