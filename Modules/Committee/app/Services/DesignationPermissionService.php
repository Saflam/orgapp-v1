<?php

namespace Modules\Committee\Services;

use Illuminate\Support\Collection;
use Modules\Committee\Models\Designation;
use Modules\Core\Models\Permission;

class DesignationPermissionService
{
    public function assign(
        Designation $designation,
        Permission $permission,
    ): void {
        $designation->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);
    }

    public function remove(
        Designation $designation,
        Permission $permission,
    ): void {
        $designation->permissions()->detach(
            $permission->id
        );
    }

    /**
     * @param  iterable<int>  $permissionIds
     */
    public function sync(
        Designation $designation,
        iterable $permissionIds,
    ): void {
        $designation->permissions()->sync(
            collect($permissionIds)->values()->all()
        );
    }

    public function has(
        Designation $designation,
        Permission $permission,
    ): bool {
        return $designation
            ->permissions()
            ->whereKey($permission->id)
            ->exists();
    }

    public function permissions(
        Designation $designation,
    ): Collection {
        return $designation->permissions()->get();
    }
}