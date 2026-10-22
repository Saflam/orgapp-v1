<?php

namespace Modules\Finance\Providers;

use Modules\Core\Contracts\RoleProvider;

class FinanceRoleProvider implements RoleProvider
{
    public function module(): ?string { return 'Finance'; }

    public function roles(): array
    {
        return [
            [
                'name' => 'Finance Administrator',
                'code' => 'finance_admin',
                'description' => 'Manage organization finance configuration and finance master data.',
                'permissions' => [
                    'finance.period.view',
                    'finance.period.manage',
                    'finance.account.view',
                    'finance.account.manage',
                    'finance.item.view',
                    'finance.item.manage',
                    'finance.customer.view',
                    'finance.customer.manage',
                ],
            ],
        ];
    }
}
