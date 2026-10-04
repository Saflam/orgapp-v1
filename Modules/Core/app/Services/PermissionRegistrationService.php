<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\PermissionProvider;
use Modules\Core\Models\Permission;

class PermissionRegistrationService
{
    public function register(PermissionProvider $provider): void
    {
        foreach ($provider->permissions() as $definition) {
            Permission::updateOrCreate(
                [
                    'code' => $definition['code'],
                ],
                [
                    'module' => $definition['module'],
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                ]
            );
        }
    }
}