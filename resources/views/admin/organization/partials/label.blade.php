<a class="pcg-tree__label {{ $isSelected ? 'is-selected' : '' }} {{ $unit->is_active ? '' : 'is-inactive' }}"
    href="{{ route('admin.organization.index', ['unit' => $unit->id]) }}" @if ($isSelected) aria-current="true" @endif>
    <strong>{{ $unit->code }}</strong>
    <span>{{ $unit->name }}</span>
    <span class="pcg-badge">{{ $unit->typeLabel() }}</span>
    @unless ($unit->is_active)
        <span class="pcg-badge pcg-badge--warning">Inactive</span>
    @endunless
</a>
