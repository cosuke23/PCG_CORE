{{ $system }}

Your term of enlistment ends in about {{ $months }} months

Hello {{ $name }},

Your ETE (end of term of enlistment) is {{ \Illuminate\Support\Carbon::parse($ete)->format('F j, Y') }}.
If you intend to re-enlist, start gathering the requirements with your unit or HR now:

@foreach ($requirements as $r)
- {{ $r }}
@endforeach

Your re-enlistment progress is kept by HR; ask them for its status.

Open the portal: {{ $url }}

You get this reminder 6 months and 3 months before your ETE.
