<?php

namespace Modules\Committee\Providers;

use Modules\Core\Contracts\PermissionProvider;

class CommitteePermissionProvider implements PermissionProvider
{
    public function permissions(): array
    {
        return [
            [
                'module' => 'Committee',
                'name' => 'View committees',
                'code' => 'committee.view',
                'description' => 'View committees and their terms.',
            ],
            [
                'module' => 'Committee',
                'name' => 'Manage committees',
                'code' => 'committee.manage',
                'description' => 'Create and manage committees and terms.',
            ],
            [
                'module' => 'Committee',
                'name' => 'View designations',
                'code' => 'committee.designations.view',
                'description' => 'View committee designations.',
            ],
            [
                'module' => 'Committee',
                'name' => 'Manage designations',
                'code' => 'committee.designations.manage',
                'description' => 'Create and manage committee designations.',
            ],
            [
                'module' => 'Committee',
                'name' => 'Manage designation permissions',
                'code' => 'committee.designation_permissions.manage',
                'description' => 'Assign and revoke permissions for designations.',
            ],
            [
                'module' => 'Committee',
                'name' => 'View committee memberships',
                'code' => 'committee.memberships.view',
                'description' => 'View committee member assignments.',
            ],
            [
                'module' => 'Committee',
                'name' => 'Manage committee memberships',
                'code' => 'committee.memberships.manage',
                'description' => 'Manage committee member assignments.',
            ],
        ];
    }
}
