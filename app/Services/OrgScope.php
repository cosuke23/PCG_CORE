<?php

namespace App\Services;

use App\Models\OrgUnit;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Organisational data scope of the signed-in admin.
 *
 * admin_users.org_unit_id (session admin_org_unit_id) limits an HR admin to
 * that unit and everything below it; NULL means organisation-wide. Every
 * personnel query and action in the scoped modules goes through here.
 * Personnel without a current assignment are visible organisation-wide only.
 */
class OrgScope
{
    /** @var array<int, array> permitted unit ids, keyed by scope unit id */
    private array $unitIds = [];

    public function scopeUnitId(): ?int
    {
        $id = session('admin_org_unit_id');

        return $id ? (int) $id : null;
    }

    public function isOrgWide(): bool
    {
        return (bool) session('admin_logged_in') && $this->scopeUnitId() === null;
    }

    /** Unit ids the admin may see; null = all. */
    public function unitIds(): ?array
    {
        if ($this->isOrgWide()) {
            return null;
        }

        // keyed by scope: the service may outlive one request (controller instances are reused)
        $scopeId = $this->scopeUnitId();
        if (!isset($this->unitIds[$scopeId])) {
            $unit = OrgUnit::find($scopeId);
            $this->unitIds[$scopeId] = $unit ? $unit->subtreeIds() : [];  // missing unit: no access
        }

        return $this->unitIds[$scopeId];
    }

    public function scopeUnit(): ?OrgUnit
    {
        return $this->scopeUnitId() ? OrgUnit::find($this->scopeUnitId()) : null;
    }

    public function personnelQuery(): Builder
    {
        $query = Personnel::query();
        $ids = $this->unitIds();

        if ($ids !== null) {
            $query->whereHas('currentAssignment', fn ($q) => $q->whereIn('org_unit_id', $ids));
        }

        return $query;
    }

    public function canSee(Personnel $personnel): bool
    {
        return $this->personnelQuery()->whereKey($personnel->id)->exists();
    }

    public function canUseUnit(int $unitId): bool
    {
        $ids = $this->unitIds();

        return $ids === null || in_array($unitId, $ids, true);
    }
}
