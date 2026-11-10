<?php

namespace Modules\Finance\Providers;

use Modules\Core\Contracts\PermissionProvider;

class FinancePermissionProvider implements PermissionProvider
{
    public function permissions(): array
    {
        return [
            ['module' => 'finance', 'name' => 'View Financial Periods', 'code' => 'finance.period.view', 'description' => 'View organization-wide financial periods.'],
            ['module' => 'finance', 'name' => 'Manage Financial Periods', 'code' => 'finance.period.manage', 'description' => 'Create, edit, and close organization-wide financial periods.'],
            ['module' => 'finance', 'name' => 'View Chart of Accounts', 'code' => 'finance.account.view', 'description' => 'View the organization-wide chart of accounts.'],
            ['module' => 'finance', 'name' => 'Manage Chart of Accounts', 'code' => 'finance.account.manage', 'description' => 'Create, edit, and deactivate manually managed organization-wide accounts.'],
            ['module' => 'finance', 'name' => 'View Journal Entries', 'code' => 'finance.journal.view', 'description' => 'View the organization-wide journal register and journal entries.'],
            ['module' => 'finance', 'name' => 'Post Journal Entries', 'code' => 'finance.journal.post', 'description' => 'Create and post manual organization-wide journal entries.'],
            ['module' => 'finance', 'name' => 'View Tax Configuration', 'code' => 'finance.tax.view', 'description' => 'View organization-wide tax rates and tax groups.'],
            ['module' => 'finance', 'name' => 'Manage Tax Configuration', 'code' => 'finance.tax.manage', 'description' => 'Create and manage organization-wide tax rates, tax groups and account mappings.'],
            ['module' => 'finance', 'name' => 'View Finance Items', 'code' => 'finance.item.view', 'description' => 'View finance items.'],
            ['module' => 'finance', 'name' => 'Manage Finance Items', 'code' => 'finance.item.manage', 'description' => 'Create and manage manually-owned finance items.'],
            ['module' => 'finance', 'name' => 'View Finance Customers', 'code' => 'finance.customer.view', 'description' => 'View finance customers.'],
            ['module' => 'finance', 'name' => 'Manage Finance Customers', 'code' => 'finance.customer.manage', 'description' => 'Create and manage external finance customers.'],
        ];
    }
}
