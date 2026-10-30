@php $isSelected = $selected && $selected->id === $unit->id; @endphp
<li class="pcg-tree__item">
    @if ($unit->children->isNotEmpty())
        <details class="pcg-tree__branch" @if ($unit->depth < 2 || in_array($unit->id, $openIds, true)) open @endif>
            <summary>
                @include('admin.organization.partials.label', ['unit' => $unit, 'isSelected' => $isSelected])
            </summary>
            <ul class="pcg-tree__list">
                @foreach ($unit->children as $child)
                    @include('admin.organization.partials.node', ['unit' => $child])
                @endforeach
            </ul>
        </details>
    @else
        <div class="pcg-tree__leaf">
            @include('admin.organization.partials.label', ['unit' => $unit, 'isSelected' => $isSelected])
        </div>
    @endif
</li>
