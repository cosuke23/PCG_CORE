{{ $system }}

Your leave is {{ $approved ? 'approved' : 'declined' }}

Hello {{ $name }},

Leave: {{ $type }}
Dates: {{ $dates }} ({{ $days }} {{ $days === 1 ? 'day' : 'days' }})
@if ($note)
Note: {{ $note }}
@endif

Open my leave: {{ $url }}
