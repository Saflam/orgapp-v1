# Finance Module

General-purpose financial foundation for Saradhi.

The module is intentionally independent of Member and other optional modules. Other modules integrate through Finance contracts/events rather than Finance depending on them.

## Double-entry Journal Foundation

Finance journals are posted as balanced double-entry accounting transactions.

- Every journal belongs to an organization, Finance scope, and financial period.
- Every journal contains at least two lines.
- Each line contains either a debit or a credit, never both.
- Total debits must equal total credits before a journal is posted.
- Accounts must belong to the same organization and Finance scope as the journal.
- Inactive accounts cannot be posted to.
- Journals cannot be posted outside their financial period.
- Closed financial periods reject new journals.
- Posted journals and their lines are immutable; corrections will be implemented through reversal journals.

The `FinanceJournalService` is the domain entry point for posting journals.


## Cost Centre Attribution and Financial Reports

Journal lines can optionally be assigned to one of the organization's cost centres:

- Central / Organization
- Unit
- Committee (when the Committee table is installed)

Cost centre attribution is stored on each journal line. A journal may therefore contain lines attributed to different cost centres. Posted journal lines remain immutable, and reversal journals preserve their original line-level cost centre assignments.

The Trial Balance, Income Statement, Balance Sheet, and Cash Flow Statement support the same report scopes:

- All Cost Centres
- Central / Organization
- A selected Unit
- A selected Committee

For Unit and Committee scopes, the report does not display organization-wide figures until an owner is selected. PDF exports use the same date and cost-centre parameters as the on-screen report.

Older journals that only use the journal-level `unit_id` remain reportable through the legacy Unit filter. Older unassigned journal lines remain part of the Central / Organization or All Cost Centres reports according to the report's legacy attribution rules.

### Finance feature test suite

Run the Finance module feature tests from the Laravel application root:

```bash
php artisan test Modules/Finance/tests/Feature
```

The cost-centre and financial statement coverage includes:

```bash
php artisan test Modules/Finance/tests/Feature/FinanceJournalCostCenterTest.php
php artisan test Modules/Finance/tests/Feature/FinanceCostCenterTest.php
php artisan test Modules/Finance/tests/Feature/FinanceIncomeStatementCostCenterTest.php
php artisan test Modules/Finance/tests/Feature/FinanceBalanceSheetTest.php
php artisan test Modules/Finance/tests/Feature/FinanceCashFlowStatementTest.php
php artisan test Modules/Finance/tests/Feature/FinanceTrialBalanceTest.php
```

## Accounts Receivable and Accounts Payable

The Finance module includes an organization-wide receivables/payables workflow:

- External customers and suppliers (member customers continue to be resolved by the Member payment integration).
- Posted sales invoices and supplier bills with multiple lines.
- Per-line income/expense/asset account selection and optional tax-rate mapping.
- Tax-exclusive line calculation using the configured sales or purchase tax account.
- Automatic double-entry journals through `FinanceJournalService`.
- Customer receipts and supplier payments through configured cash/bank accounts.
- One payment can be allocated across multiple invoices or bills for the same customer/supplier; partial payments are supported.
- Payments are distributed proportionally across the document's cost centres so the cash and receivable/payable journal lines retain cost-centre attribution.
- Outstanding balances and current / 1–30 / 31–60 / 61–90 / over-90-day ageing buckets.
- Incoming customer receipts also issue the existing immutable Finance receipt record.
- Posted documents, journal entries, payments and payment allocations are immutable; only invoice/bill payment balance and status fields change as allocations are applied.
- Livewire document submissions use request keys to prevent accidental duplicate invoice, bill or payment posting.
- Transactions use the organization's base currency only; foreign-currency conversion is not included in this release.

### Required control accounts

Finance account provisioning must have created the standard organization-wide accounts:

- `1000` Cash and `1100` Bank (cash/bank accounts)
- `1200` Accounts Receivable (asset)
- `2000` Accounts Payable (liability)
- `4000` Sales Income (income)
- `5000` General Expense (expense)

Taxable invoice/bill lines require the selected tax rate to have a valid sales or purchase tax account mapped in Finance Tax Configuration. Purchase tax accounts must be assets; sales tax accounts must be liabilities. Tax is calculated on top of line net amounts (tax-exclusive pricing).

### Service entry points

- `FinanceCustomerService::createExternalCustomer()`
- `FinanceSupplierService::create()`
- `FinanceReceivablesPayablesService::postSalesInvoice()`
- `FinanceReceivablesPayablesService::postPurchaseBill()`
- `FinanceReceivablesPayablesService::recordPayment()`
- `FinanceReceivablesPayablesService::receivablesAging()`
- `FinanceReceivablesPayablesService::payablesAging()`

The management screen is available at `finance.receivables-payables` (`/organization/finance/receivables-payables`). Customer and supplier records are managed on the separate `finance.parties` page (`/organization/finance/parties`). Both pages use organization-scoped Livewire pagination; invoice, bill and payment registers support debounced search, with status/type filters where applicable. Customer filters include member/external type and active status; supplier filters include active status.

### Permissions

- `finance.customer.view`, `finance.customer.manage`
- `finance.supplier.view`, `finance.supplier.manage`
- `finance.sales_invoice.view`, `finance.sales_invoice.manage`
- `finance.purchase_bill.view`, `finance.purchase_bill.manage`
- `finance.payment.view`, `finance.payment.manage`
- `finance.aging.view`

The Finance Administrator role receives these permissions.

### Receivables/payables tests

```bash
php artisan test Modules/Finance/tests/Feature/FinanceReceivablesPayablesTest.php
php artisan test Modules/Finance/tests/Feature/FinanceReceivablesPayablesUiTest.php
php artisan test Modules/Finance/tests/Feature/FinancePartyManagementUiTest.php
php artisan test Modules/Finance/tests/Feature/FinanceReportsSidebarNavigationTest.php
```

### MVP boundaries

This release posts invoices and bills directly (there is no draft editing workflow). Payments must be fully allocated to one or more outstanding documents belonging to the same customer or supplier; unapplied advances and on-account credits are not included. Foreign currency, credit/debit notes, invoice/bill cancellation, refunds, recurring invoices and reversal of invoice/bill/payment-generated journals are deferred. Generated document journals are therefore protected from the manual journal reversal action to prevent ledger balances from diverging from document balances.
