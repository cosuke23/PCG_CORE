@extends('layouts.admin')

@section('title', 'Organization')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Organizational structure</h2>
                <p>PCG headquarters, commands and their subordinate offices, units and sections.
                    {{ $active }} active of {{ $total }} {{ \Illuminate\Support\Str::plural('unit', $total) }}.
                    Units are never deleted: deactivating one keeps its history.</p>
            </div>
        </div>

        <div class="pcg-grid">
            <section class="pcg-card" aria-labelledby="tree-heading">
                <div class="pcg-card__header">
                    <h3 id="tree-heading">Hierarchy</h3>
                </div>
                <div class="pcg-card__body">
                    @if ($tree->isEmpty())
                        <p class="pcg-empty">No units yet. Run <code>php artisan migrate</code> to create the headquarters and the three Functional Commands.</p>
                    @else
                        <ul class="pcg-tree pcg-tree__list" aria-label="Organizational hierarchy">
                            @foreach ($tree as $unit)
                                @include('admin.organization.partials.node', ['unit' => $unit])
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <div class="pcg-stack">
                @if ($selected)
                    <section class="pcg-card" aria-labelledby="unit-heading">
                        <div class="pcg-card__header">
                            <h3 id="unit-heading">{{ $selected->code }}</h3>
                            @if ($selected->is_active)
                                <span class="pcg-badge pcg-badge--success">Active</span>
                            @else
                                <span class="pcg-badge pcg-badge--warning">Inactive</span>
                            @endif
                        </div>
                        <div class="pcg-card__body">
                            <dl class="pcg-dl">
                                <div><dt>Name</dt><dd>{{ $selected->name }}</dd></div>
                                <div><dt>Level</dt><dd>{{ $selected->typeLabel() }}</dd></div>
                                @if ($selected->category)
                                    <div><dt>Command group</dt><dd>{{ \App\Models\OrgUnit::CATEGORIES[$selected->category] ?? $selected->category }}</dd></div>
                                @endif
                                <div><dt>Parent</dt><dd>{{ $selected->parent ? $selected->parent->code . ' · ' . $selected->parent->name : '—' }}</dd></div>
                                <div><dt>Effective</dt><dd>{{ $selected->effective_from?->format('d M Y') ?? 'not recorded' }} – {{ $selected->effective_to?->format('d M Y') ?? 'present' }}</dd></div>
                                <div><dt>Subordinate units</dt><dd>{{ $selectedChildren->count() }}</dd></div>
                                @if ($selected->description)
                                    <div><dt>Reference</dt><dd>{{ $selected->description }}</dd></div>
                                @endif
                            </dl>

                            @if ($canManage)
                                <form method="POST" action="{{ route('admin.organization.status', $selected->id) }}" class="pcg-mt">
                                    @csrf
                                    <input type="hidden" name="is_active" value="{{ $selected->is_active ? 0 : 1 }}">
                                    <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--block"
                                        @if ($selected->is_active) data-pcg-confirm="Deactivate {{ $selected->code }}? It stays in the records and can be activated again." @endif>
                                        {{ $selected->is_active ? 'Deactivate unit' : 'Activate unit' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </section>

                    @if ($canManage)
                        <section class="pcg-card" aria-labelledby="edit-heading">
                            <div class="pcg-card__header">
                                <h3 id="edit-heading">Edit {{ $selected->code }}</h3>
                            </div>
                            <div class="pcg-card__body">
                                <details class="pcg-disclosure" @if ($errors->any() && old('_form') === 'edit') open @endif>
                                    <summary class="pcg-btn pcg-btn--secondary pcg-btn--block">Edit details</summary>
                                    @include('admin.organization.partials.form', ['unit' => $selected, 'prefix' => 'edit_'])
                                </details>
                            </div>
                        </section>
                    @endif
                @endif

                @if ($canManage)
                    <section class="pcg-card" aria-labelledby="add-heading">
                        <div class="pcg-card__header">
                            <h3 id="add-heading">Add a unit{{ $selected ? ' under ' . $selected->code : '' }}</h3>
                        </div>
                        <div class="pcg-card__body">
                            <details class="pcg-disclosure" @if (!$selected || ($errors->any() && old('_form') === 'add')) open @endif>
                                <summary class="pcg-btn pcg-btn--secondary pcg-btn--block">New unit</summary>
                                @include('admin.organization.partials.form', ['unit' => null, 'prefix' => 'add_', 'defaultParent' => $selected?->id])
                            </details>
                        </div>
                    </section>
                @elseif (!$selected)
                    <section class="pcg-card">
                        <div class="pcg-card__body">
                            <p class="pcg-empty">Select a unit to see its details. Only a super admin can change the structure.</p>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endsection
