MEPCOM HRMIS · Unit checker

Hello {{ $name }},

Your sign-in code: {{ $code }}

It expires in {{ $minutes }} minutes and works once.
Requested from IP {{ request()->ip() }} at {{ now()->timezone('Asia/Manila')->format('d M Y H:i') }}.

If you did not try to sign in, do not share this code. Change your password and tell your administrator.
