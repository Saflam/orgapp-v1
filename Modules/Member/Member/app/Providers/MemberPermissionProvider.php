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
                'name' => 'Edit Membership Applications',
                'code' => 'membership.application.edit',
                'description' => 'Edit permitted membership application data during verification and review.',
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
                'name' => 'View Member Payment Items',
                'code' => 'membership.payment-item.view',
                'description' => 'View organization member payment items, rates, and allocations.',
            ],
            [
                'module' => 'member',
                'name' => 'Manage Member Payment Items',
                'code' => 'membership.payment-item.manage',
                'description' => 'Create and update organization member payment items, rates, allocations, and reminder settings.',
            ],
            [
                'module' => 'member',
                'name' => 'View Membership Payments',
                'code' => 'membership.payment.view',
                'description' => 'View pending membership payment obligations.',
            ],
            [
                'module' => 'member',
                'name' => 'Receive Membership Payments',
                'code' => 'membership.payment.receive',
                'description' => 'Record receipt of membership payment obligations.',
            ],
            [
                'module' => 'member',
                'name' => 'Discount Membership Payments',
                'code' => 'membership.payment.discount',
                'description' => 'Apply an authorized discount or waiver to a pending membership payment obligation.',
            ],
            [
                'module' => 'member',
                'name' => 'Confirm Membership Applications',
                'code' => 'membership.application.confirm',
                'description' => 'Confirm approved and paid applications and create active membership.',
            ],
            [
                'module' => 'member',
                'name' => 'View Unit Transfers',
                'code' => 'member.unit-transfer.view',
                'description' => 'View unit transfer requests within the assigned authorization context.',
            ],
            [
                'module' => 'member',
                'name' => 'Submit Unit Transfers',
                'code' => 'member.unit-transfer.submit',
                'description' => 'Submit unit transfer requests for eligible members.',
            ],
            [
                'module' => 'member',
                'name' => 'Approve Unit Transfers',
                'code' => 'member.unit-transfer.approve',
                'description' => 'Approve a unit transfer request within the assigned unit context.',
            ],
            [
                'module' => 'member',
                'name' => 'Reject Unit Transfers',
                'code' => 'member.unit-transfer.reject',
                'description' => 'Reject a unit transfer request within the assigned unit context.',
            ],
            [
                'module' => 'member',
                'name' => 'Complete Unit Transfers',
                'code' => 'member.unit-transfer.complete',
                'description' => 'Complete an approved unit transfer within an authorized source or target unit context.',
            ],
        ];
    }
}
