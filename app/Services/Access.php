<?php

namespace App\Services;

use App\Models\OrgUnit;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Answers the spec's access questions on the server for every request:
 * who is asking, which role they hold, at which place in the tree, what
 * action they want, and whether the record sits inside that place.
 */
class Access
{
    /** Active assignments of this user that grant the permission. */
    public function grants(?User $user, string $permission): Collection
    {
        if (! $user || ! $user->is_active) {
            return collect();
        }

        return $user->activeAssignments()->filter(
            fn (RoleAssignment $a) => $a->role->permissions->contains('key', $permission)
        )->values();
    }

    /** Whether the user may perform the action, optionally on a record inside a unit. */
    public function can(?User $user, string $permission, ?OrgUnit $unit = null): bool
    {
        $grants = $this->grants($user, $permission);
        if ($unit === null) {
            return $grants->isNotEmpty();
        }

        return $grants->contains(fn (RoleAssignment $a) => $this->covers($a, $unit));
    }

    /** The branch paths in which the user holds the permission. */
    public function paths(?User $user, string $permission): array
    {
        $paths = $this->grants($user, $permission)
            ->map(fn (RoleAssignment $a) => $a->orgUnit?->path ?? '/')
            ->unique()
            ->values()
            ->all();

        // Drop paths already covered by a broader path.
        return array_values(array_filter($paths, function ($p) use ($paths) {
            foreach ($paths as $other) {
                if ($other !== $p && str_starts_with($p, $other)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** The highest units (closest to National) where the user holds the permission. */
    public function units(?User $user, string $permission): Collection
    {
        $paths = $this->paths($user, $permission);

        return OrgUnit::whereIn('path', $paths)->orderBy('depth')->orderBy('name')->get();
    }

    /** All units the user may act in for a permission, including descendants. */
    public function unitsWithin(?User $user, string $permission, ?string $type = null): Collection
    {
        $paths = $this->paths($user, $permission);
        if (empty($paths)) {
            return collect();
        }

        return OrgUnit::query()
            ->where(function ($q) use ($paths) {
                foreach ($paths as $path) {
                    $q->orWhere('path', 'like', $path.'%');
                }
            })
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('depth')->orderBy('name')
            ->get();
    }

    /** Whether the user holds the permission at exactly this unit (or system-wide). */
    public function holdsAt(?User $user, string $permission, OrgUnit $unit): bool
    {
        return $this->grants($user, $permission)
            ->contains(fn (RoleAssignment $a) => $a->org_unit_id === null || (int) $a->org_unit_id === (int) $unit->id);
    }

    /** The shallowest depth at which the user holds the permission over the unit. */
    public function bestDepthOver(?User $user, string $permission, OrgUnit $unit): ?int
    {
        return $this->grants($user, $permission)
            ->filter(fn (RoleAssignment $a) => $this->covers($a, $unit))
            ->map(fn (RoleAssignment $a) => $a->orgUnit?->depth ?? 0)
            ->min();
    }

    protected function covers(RoleAssignment $assignment, OrgUnit $unit): bool
    {
        return $assignment->orgUnit === null || $unit->isWithin($assignment->orgUnit);
    }
}
