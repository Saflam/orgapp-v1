<?php

namespace Modules\Member\Providers;

use Modules\Core\Contracts\PermissionProvider;

class MemberPermissionProvider implements PermissionProvider
{
    public function permissions(): array
    {
        return [
            [
                'module' => 'member',
                'name' => 'View Members',
                'code' => 'members.view',
                'description' => 'View the organization members list.',
            ],
            [
                'module' => 'member',
                'name' => 'Update Members',
                'code' => 'members.update',
                'description' => 'Update organization member information.',
            ],
            [
                'module' => 'member',
                'name' => 'View Own Profile',
                'code' => 'members.profile.view',
                'description' => 'View the authenticated member profile.',
            ],
            [
                'module' => 'member',
                'name' => 'Update Own Profile',
                'code' => 'members.profile.update',
                'description' => 'Update the authenticated member profile.',
            ],
            [
                'module' => 'member',
                'name' => 'View Membership Applications',
                'code' => 'membership.application.view',
                'description' => 'View membership application configuration and applications.',
            ],
            [
                'module' => 'member',
                'name' => 'Submit Membership Applications',
                'code' => 'membership.application.submit',
                'description' => 'Submit membership applications.',
            ],
            [
                'module' => 'member',
                'name' => 'Verify Membership Applications',
                'code' => 'membership.application.verify',
                'description' => 'Verify or reject submitted membership applications.',
            ],
            [
                'module' => 'member',
                'name' => 'Review Membership Applications',
                'code' => 'membership.application.review',
                'description' => 'Review or reject verified membership applications.',
            ],
            [
                'module' => 'member',
                'name' => 'Approve Membership Applications',
                'code' => 'membership.application.approve',
                'description' => 'Approve or reject reviewed membership applications.',
            ],
            [
                'module' => 'member',
                'name' => 'Receive Membership Application Payment',
                'code' => 'membership.application.receive-payment',
                'description' => 'Record receipt of fees for approved membership applications.',
            ],
            [
                'module' => 'member',
                'name' => 'Confirm Membership Applications',
                'code' => 'membership.application.confirm',
                'description' => 'Confirm approved and paid applications and create active membership.',
            ],
        ];
    }
}
