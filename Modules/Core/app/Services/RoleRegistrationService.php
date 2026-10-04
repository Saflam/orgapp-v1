<?php

namespace Modules\Core\Services;

use DomainException;
use Modules\Core\Contracts\RoleProvider;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;

class RoleRegistrationService
{
    public function register(
        Organization $organization,
        RoleProvider $provider
    ): void {
        if ($provider->module() === null) {
            throw new \InvalidArgumentException(
                'A global role provider cannot be registered as an organization role.'
            );
        }

        foreach ($provider->roles() as $definition) {
            $role = Role::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => $definition['code'],
                ],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'is_system' => false,
                ]
            );

            $this->syncPermissions($role, $definition);
        }
    }

    public function registerGlobal(
        RoleProvider $provider
    ): void {
        if ($provider->module() !== null) {
            throw new \InvalidArgumentException(
                'An organization role provider cannot be registered as a global role.'
            );
        }

        foreach ($provider->roles() as $definition) {
            $role = Role::updateOrCreate(
                [
                    'organization_id' => null,
                    'code' => $definition['code'],
                ],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'is_system' => true,
                ]
            );

            $this->syncPermissions($role, $definition);
        }
    }

    private function syncPermissions(
        Role $role,
        array $definition
    ): void {
        $permissionCodes = $definition['permissions'] ?? [];

        if ($permissionCodes === []) {
            $role->permissions()->sync([]);

            return;
        }

        $permissions = Permission::query()
            ->whereIn('code', $permissionCodes)
            ->get();

        $foundCodes = $permissions
            ->pluck('code')
            ->all();

        $missingCodes = array_values(
            array_diff($permissionCodes, $foundCodes)
        );

        if ($missingCodes !== []) {
            throw new DomainException(
                'The following permissions are not registered: '
                . implode(', ', $missingCodes)
            );
        }

        $role->permissions()->sync(
            $permissions->pluck('id')->all()
        );
    }
}