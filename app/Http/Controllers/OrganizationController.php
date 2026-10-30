<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin -> Organization: the configurable PCG hierarchy (org_units).
 * Super/HR admins view it; only super admins change it. Units are never
 * deleted, only deactivated. Every change is written to the admin audit log
 * by the admin.audit middleware.
 */
class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $selected = $request->filled('unit') ? OrgUnit::with('parent')->find($request->integer('unit')) : null;

        // keep the branches above the selected unit expanded
        $openIds = [];
        for ($node = $selected?->parent; $node && !in_array($node->id, $openIds, true); $node = $node->parent) {
            $openIds[] = $node->id;
        }

        return view('admin.organization.index', [
            'tree' => OrgUnit::tree(),
            'openIds' => $openIds,
            'options' => OrgUnit::flatTree(),
            'selected' => $selected,
            'selectedChildren' => $selected ? $selected->children()->get() : collect(),
            'canManage' => session('admin_role') === 'super_admin',
            'total' => OrgUnit::count(),
            'active' => OrgUnit::where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $data['updated_by'] = session('admin_user_id');

        if ($data['parent_id'] && !OrgUnit::whereKey($data['parent_id'])->value('is_active')) {
            return back()->withInput()->withErrors(['parent_id' => 'The parent unit is inactive. Activate it first or choose another parent.']);
        }

        $unit = OrgUnit::create($data);

        return redirect()->route('admin.organization.index', ['unit' => $unit->id])
            ->with('success', "{$unit->code} was added.");
    }

    public function update(Request $request, OrgUnit $unit)
    {
        $data = $this->validated($request, $unit);

        if ($data['parent_id'] && in_array((int) $data['parent_id'], $unit->subtreeIds(), true)) {
            return back()->withInput()->withErrors(['parent_id' => 'A unit cannot be placed under itself or one of its own subordinate units.']);
        }

        $data['updated_by'] = session('admin_user_id');
        $unit->update($data);

        return redirect()->route('admin.organization.index', ['unit' => $unit->id])
            ->with('success', "{$unit->code} was updated.");
    }

    public function status(Request $request, OrgUnit $unit)
    {
        $request->validate(['is_active' => 'required|boolean']);
        $activate = $request->boolean('is_active');

        if (!$activate && $unit->children()->where('is_active', true)->exists()) {
            return back()->with('error', "{$unit->code} still has active subordinate units. Deactivate or move them first.");
        }
        if ($activate && $unit->parent && !$unit->parent->is_active) {
            return back()->with('error', "The parent unit {$unit->parent->code} is inactive. Activate it first.");
        }

        $unit->update(['is_active' => $activate, 'updated_by' => session('admin_user_id')]);

        return back()->with('success', "{$unit->code} was " . ($activate ? 'activated' : 'deactivated') . '. Its records and history are kept.');
    }

    private function validated(Request $request, ?OrgUnit $unit = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._\/&()-]*$/',
                function ($attribute, $value, $fail) use ($unit) {
                    // case-insensitive, like the MySQL collation of the unique index
                    $taken = OrgUnit::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($value))])
                        ->when($unit, fn ($q) => $q->whereKeyNot($unit->id))->exists();
                    if ($taken) {
                        $fail('Another unit already uses this code.');
                    }
                }],
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(array_keys(OrgUnit::TYPES))],
            'category' => ['nullable', Rule::in(array_keys(OrgUnit::CATEGORIES))],
            'description' => 'nullable|string|max:2000',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ], [
            'code.regex' => 'Use letters, numbers, spaces and . _ / & ( ) - only.',
        ]);

        $data['code'] = trim($data['code']);
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
