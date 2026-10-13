<?php

namespace Modules\Finance\Providers;

use Modules\Core\Contracts\PermissionProvider;

class FinancePermissionProvider implements PermissionProvider
{
    public function permissions(): array
    {
        return [
            ['module' => 'finance', 'name' => 'View Finance Accounts', 'code' => 'finance.account.view', 'description' => 'View chart of accounts within the authorized financial scope.'],
            ['module' => 'finance', 'name' => 'Manage Finance Accounts', 'code' => 'finance.account.manage', 'description' => 'Create and manage finance accounts within the authorized financial scope.'],
            ['module' => 'finance', 'name' => 'View Finance Items', 'code' => 'finance.item.view', 'description' => 'View finance items.'],
            ['module' => 'finance', 'name' => 'Manage Finance Items', 'code' => 'finance.item.manage', 'description' => 'Create and manage manually-owned finance items.'],
            ['module' => 'finance', 'name' => 'View Finance Customers', 'code' => 'finance.customer.view', 'description' => 'View finance customers.'],
            ['module' => 'finance', 'name' => 'Manage Finance Customers', 'code' => 'finance.customer.manage', 'description' => 'Create and manage external finance customers.'],
        ];
    }
}
