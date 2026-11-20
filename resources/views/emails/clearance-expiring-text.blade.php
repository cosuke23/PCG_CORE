{{ $system }}

Your {{ $label }} {{ $daysLeft <= 0 ? 'expires today' : 'expires in ' . $daysLeft . ($daysLeft === 1 ? ' day' : ' days') }}

Hello {{ $name }},

The {{ $label }} in your required documents is valid until {{ \Illuminate\Support\Carbon::parse($validUntil)->format('F j, Y') }}.
Get a new one and upload it with its new valid-until date{{ $daysLeft > 0 ? ' once it has expired; the box opens again the day after' : '; the box opens again tomorrow' }}.

Open my documents: {{ $url }}

You get this reminder 30 days, 7 days and on the day a clearance expires.
