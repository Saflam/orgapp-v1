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
            ['module' => 'finance', 'name' => 'View Trial Balance', 'code' => 'finance.report.view', 'description' => 'View the cumulative organization-wide trial balance report.'],
            ['module' => 'finance', 'name' => 'View Journal Entries', 'code' => 'finance.journal.view', 'description' => 'View the organization-wide journal register and journal entries.'],
            ['module' => 'finance', 'name' => 'Post Journal Entries', 'code' => 'finance.journal.post', 'description' => 'Create and post manual organization-wide journal entries.'],
            ['module' => 'finance', 'name' => 'Reverse Journal Entries', 'code' => 'finance.journal.reverse', 'description' => 'Create a posted reversing journal for an existing posted journal.'],
            ['module' => 'finance', 'name' => 'View Tax Configuration', 'code' => 'finance.tax.view', 'description' => 'View organization-wide tax rates and tax groups.'],
            ['module' => 'finance', 'name' => 'Manage Tax Configuration', 'code' => 'finance.tax.manage', 'description' => 'Create and manage organization-wide tax rates, tax groups and account mappings.'],
            ['module' => 'finance', 'name' => 'View Finance Items', 'code' => 'finance.item.view', 'description' => 'View finance items.'],
            ['module' => 'finance', 'name' => 'Manage Finance Items', 'code' => 'finance.item.manage', 'description' => 'Create and manage manually-owned finance items.'],
            ['module' => 'finance', 'name' => 'View Settlement Ledger', 'code' => 'finance.settlement.view', 'description' => 'View organization and unit settlement obligations and transactions.'],
            ['module' => 'finance', 'name' => 'Manage Settlements', 'code' => 'finance.settlement.manage', 'description' => 'Record inter-scope transfers and manage settlement transactions.'],
            ['module' => 'finance', 'name' => 'Confirm Settlements', 'code' => 'finance.settlement.confirm', 'description' => 'Confirm or reject incoming settlement transfers for the authorized scope.'],
            ['module' => 'finance', 'name' => 'View Finance Customers', 'code' => 'finance.customer.view', 'description' => 'View finance customers.'],
            ['module' => 'finance', 'name' => 'Manage Finance Customers', 'code' => 'finance.customer.manage', 'description' => 'Create and manage external finance customers.'],
            ['module' => 'finance', 'name' => 'View Finance Suppliers', 'code' => 'finance.supplier.view', 'description' => 'View organization suppliers and supplier records.'],
            ['module' => 'finance', 'name' => 'Manage Finance Suppliers', 'code' => 'finance.supplier.manage', 'description' => 'Create and manage organization suppliers.'],
            ['module' => 'finance', 'name' => 'View Sales Invoices', 'code' => 'finance.sales_invoice.view', 'description' => 'View customer sales invoices and receivable balances.'],
            ['module' => 'finance', 'name' => 'Manage Sales Invoices', 'code' => 'finance.sales_invoice.manage', 'description' => 'Create and post customer sales invoices.'],
            ['module' => 'finance', 'name' => 'View Purchase Bills', 'code' => 'finance.purchase_bill.view', 'description' => 'View supplier bills and payable balances.'],
            ['module' => 'finance', 'name' => 'Manage Purchase Bills', 'code' => 'finance.purchase_bill.manage', 'description' => 'Create and post supplier bills.'],
            ['module' => 'finance', 'name' => 'View Finance Payments', 'code' => 'finance.payment.view', 'description' => 'View customer receipts and supplier payments.'],
            ['module' => 'finance', 'name' => 'Manage Finance Payments', 'code' => 'finance.payment.manage', 'description' => 'Record customer receipts and supplier payments and allocate them to documents.'],
            ['module' => 'finance', 'name' => 'View Receivables and Payables Ageing', 'code' => 'finance.aging.view', 'description' => 'View accounts receivable and accounts payable ageing reports.'],
        ];
    }
}
