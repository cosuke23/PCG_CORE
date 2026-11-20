{{ $system }} alert

{{ $title }}

@foreach ($lines as $line)
{{ $line }}
@endforeach

Site: {{ $url }}

This message was sent automatically by the HRMIS monitoring (php artisan pcg:monitor, pcg:backup-verify, error alerts).
