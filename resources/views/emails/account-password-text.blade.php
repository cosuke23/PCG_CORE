{{ $system }}

Hello {{ $name }},

{{ $reset ? 'An administrator reset the password of your account. Sign in with this temporary password:' : 'An administrator created an account for you. Sign in with these details:' }}

Email: {{ $email }}
Temporary password: {{ $password }}
Sign in: {{ $signInUrl }}

You must choose your own password right after you sign in. The temporary password stops working then.

Do not forward this email or share the password. If you did not expect it, tell your super admin.
