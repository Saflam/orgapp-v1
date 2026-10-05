<?php

namespace Modules\Member\Providers;

use Modules\Core\Contracts\RoleProvider;

class MemberRoleProvider implements RoleProvider
{
    public function module(): ?string
    {
        return 'Member';
    }

    public function roles(): array
    {
        return [
            [
                'name' => 'Organization Administrator',
                'code' => 'organization_admin',
                'description' => 'Manage organization-level member operations and membership applications.',
                'permissions' => [
                    'members.view',
                    'members.update',
                    'members.profile.view',
                    'members.profile.update',
                    'membership.application.view',
                    'membership.application.submit',
                    'membership.application.verify',
                    'membership.application.review',
                    'membership.application.approve',
                    'membership.application.receive-payment',
                    'membership.application.confirm',
                ],
            ],
        ];
    }
}
