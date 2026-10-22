<?php

namespace Modules\Core\Providers;

use Modules\Core\Contracts\PermissionProvider;

class CorePermissionProvider implements PermissionProvider
{
    public function permissions(): array
    {
        return [
            [
                'module' => 'Core',
                'name' => 'Manage organization settings',
                'code' => 'organization.settings.manage',
                'description' => 'Manage general settings for the organization.',
            ],
            [
                'module' => 'Core',
                'name' => 'Manage notification channels',
                'code' => 'notification.channel.manage',
                'description' => 'Manage the organization notification channel configuration.',
            ],
            [
                'module' => 'Core',
                'name' => 'View organization users',
                'code' => 'organization.users.view',
                'description' => 'View and search users registered with the organization.',
            ],
            [
                'module' => 'Core',
                'name' => 'Manage organization roles and permissions',
                'code' => 'organization.roles.manage',
                'description' => 'Create and edit custom roles and manage their permissions.',
            ],
            [
                'module' => 'Core',
                'name' => 'Assign organization roles',
                'code' => 'organization.roles.assign',
                'description' => 'Assign or revoke organization-level roles for users.',
            ],
        ];
    }
}
