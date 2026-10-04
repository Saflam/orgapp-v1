<?php

namespace Modules\Core\Providers;

use Modules\Core\Contracts\RoleProvider;

class CoreRoleProvider implements RoleProvider
{
    public function module(): ?string
    {
        return null;
    }

    public function roles(): array
    {
        return [
            [
                'name' => 'Super Administrator',
                'code' => 'super_admin',
                'description' => 'Full administrative access within the organization.',
            ],
        ];
    }
}