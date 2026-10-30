{{-- Shared add/edit form. $unit is null when adding; $prefix keeps ids unique. --}}
@php
    $value = fn ($field, $default = null) => old($field, $unit ? ($unit->$field instanceof \DateTimeInterface ? $unit->$field->format('Y-m-d') : $unit->$field) : $default);
    $excluded = $unit ? $unit->subtreeIds() : [];
@endphp
<form method="POST" action="{{ $unit ? route('admin.organization.update', $unit->id) : route('admin.organization.store') }}" data-pcg-busy>
    @csrf
    <input type="hidden" name="_form" value="{{ $unit ? 'edit' : 'add' }}">
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}parent_id">Parent unit</label>
        <select class="pcg-input" id="{{ $prefix }}parent_id" name="parent_id">
            <option value="">None (top level)</option>
            @foreach ($options as $option)
                @continue(in_array($option->id, $excluded, true))
                <option value="{{ $option->id }}" @selected((string) $value('parent_id', $defaultParent ?? null) === (string) $option->id) @disabled(!$option->is_active)>
                    {{ str_repeat('— ', $option->depth) }}{{ $option->code }} · {{ $option->name }}{{ $option->is_active ? '' : ' (inactive)' }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}code">Code</label>
        <input class="pcg-input" id="{{ $prefix }}code" name="code" value="{{ $value('code') }}" required maxlength="50">
        <p class="pcg-help">Short unique code, e.g. MEPLEF. Must match the official designation.</p>
    </div>
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}name">Name</label>
        <input class="pcg-input" id="{{ $prefix }}name" name="name" value="{{ $value('name') }}" required maxlength="255">
    </div>
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}type">Level</label>
        <select class="pcg-input" id="{{ $prefix }}type" name="type" required>
            @foreach (\App\Models\OrgUnit::TYPES as $key => $label)
                <option value="{{ $key }}" @selected($value('type', 'unit') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}category">Command group <span class="pcg-table__muted" style="display:inline">(commands only)</span></label>
        <select class="pcg-input" id="{{ $prefix }}category" name="category">
            <option value="">—</option>
            @foreach (\App\Models\OrgUnit::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected($value('category') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}effective_from">Effective from</label>
        <input class="pcg-input" type="date" id="{{ $prefix }}effective_from" name="effective_from" value="{{ $value('effective_from') }}">
    </div>
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}effective_to">Effective until <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
        <input class="pcg-input" type="date" id="{{ $prefix }}effective_to" name="effective_to" value="{{ $value('effective_to') }}">
    </div>
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}sort_order">Display order</label>
        <input class="pcg-input" type="number" min="0" id="{{ $prefix }}sort_order" name="sort_order" value="{{ $value('sort_order', 0) }}">
    </div>
    <div class="pcg-field">
        <label class="pcg-label" for="{{ $prefix }}description">Description / reference <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
        <textarea class="pcg-input" id="{{ $prefix }}description" name="description" rows="3" maxlength="2000">{{ $value('description') }}</textarea>
        <p class="pcg-help">E.g. the general order or memorandum that created the unit.</p>
    </div>
    <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Saving…">{{ $unit ? 'Save changes' : 'Add unit' }}</button>
</form>
