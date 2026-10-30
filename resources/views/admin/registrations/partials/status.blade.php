@php
    $class = ['submitted' => 'pcg-badge--info', 'returned' => 'pcg-badge--warning', 'approved' => 'pcg-badge--success', 'rejected' => 'pcg-badge--danger'][$status] ?? '';
@endphp
<span class="pcg-badge {{ $class }}">{{ \App\Models\PersonnelRegistration::STATUSES[$status] ?? $status }}</span>
