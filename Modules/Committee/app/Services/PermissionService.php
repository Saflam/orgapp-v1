<?php

namespace Modules\Committee\Services;

use Illuminate\Support\Facades\DB;
use Modules\Committee\Models\Designation;
use Modules\Core\Models\Permission;

class PermissionService
{
    public function create(
        string $module,
        string $name,
        string $code,
        ?string $description = null,
    ): Permission {
        return DB::transaction(function () use (
            $module,
            $name,
            $code,
            $description,
        ) {
            return Permission::create([
                'module' => $module,
                'name' => $name,
                'code' => $code,
                'description' => $description,
            ]);
        });
    }

    public function assignToDesignation(
        Designation $designation,
        Permission $permission,
    ): void {
        $designation->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);
    }

    public function removeFromDesignation(
        Designation $designation,
        Permission $permission,
    ): void {
        $designation->permissions()->detach($permission->id);
    }
}