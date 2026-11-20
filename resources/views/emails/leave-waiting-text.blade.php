{{ $system }}

{{ count($requests) }} leave {{ count($requests) === 1 ? 'request is' : 'requests are' }} waiting for your approval

Hello {{ $name }}, these requests in {{ $scope }} have waited more than a day or two:

@foreach ($requests as $r)
- {{ $r['name'] }}: {{ $r['type'] }}, {{ $r['dates'] }} (waiting {{ $r['waiting'] }} {{ $r['waiting'] === 1 ? 'day' : 'days' }})
@endforeach

Review leave: {{ $url }}

You get this email once a day while requests are waiting.
