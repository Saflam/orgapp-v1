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
                    'membership.application.edit',
                    'membership.application.verify',
                    'membership.application.review',
                    'membership.application.approve',
                    'membership.payment-item.view',
                    'membership.payment-item.manage',
                    'membership.payment.view',
                    'membership.payment.receive',
                    'membership.payment.discount',
                    'membership.application.confirm',
                    'member.unit-transfer.view',
                    'member.unit-transfer.submit',
                    'member.unit-transfer.approve',
                    'member.unit-transfer.reject',
                    'member.unit-transfer.complete',
                ],
            ],
            [
                'name' => 'Unit Transfer Approver',
                'code' => 'unit_transfer_approver',
                'description' => 'Approve or reject unit transfer requests for the unit context where this role is assigned.',
                'permissions' => [
                    'member.unit-transfer.view',
                    'member.unit-transfer.approve',
                    'member.unit-transfer.reject',
                    'member.unit-transfer.complete',
                ],
            ],
            [
                'name' => 'Unit Transfer Coordinator',
                'code' => 'unit_transfer_coordinator',
                'description' => 'Submit and view unit transfer requests within the assigned authorization context.',
                'permissions' => [
                    'member.unit-transfer.view',
                    'member.unit-transfer.submit',
                ],
            ],
        ];
    }
}
