@php
    $class = ['pending' => 'pcg-badge--info', 'verified' => 'pcg-badge--success', 'rejected' => 'pcg-badge--danger'][$doc->status] ?? '';
@endphp
<span class="pcg-badge {{ $class }}">{{ \App\Models\PersonnelDocument::STATUSES[$doc->status] ?? $doc->status }}</span>
@if ($doc->isExpired()) <span class="pcg-badge pcg-badge--danger">Expired</span> @endif
