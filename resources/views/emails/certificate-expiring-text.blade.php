{{ $system }}

Your {{ $label }} {{ $daysLeft <= 0 ? 'expires today' : 'expires in ' . $daysLeft . ($daysLeft === 1 ? ' day' : ' days') }}

Hello {{ $name }},

Your {{ $label }} is valid until {{ \Illuminate\Support\Carbon::parse($validUntil)->format('F j, Y') }}.
Renew it in time and give the new certificate to your unit or HR so your records stay current.

Open the portal: {{ $url }}

You get this reminder 30 days, 7 days and on the day it expires.
