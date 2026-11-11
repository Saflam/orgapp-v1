<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceCustomer;
use Modules\Finance\Models\FinanceCostCenter;
use Modules\Finance\Models\FinancePayment;
use Modules\Finance\Models\FinancePurchaseBill;
use Modules\Finance\Models\FinanceSalesInvoice;
use Modules\Finance\Models\FinanceSupplier;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceCustomerService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceReceivablesPayablesService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceSupplierService;
use Modules\Finance\Services\FinanceTaxConfigurationService;
use Tests\TestCase;

class FinanceReceivablesPayablesTest extends TestCase
{
    use RefreshDatabase;

    private function setupLedger(): array
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        return [$organization, $scope, $period];
    }

    public function test_sales_invoice_posts_tax_and_tracks_partial_then_full_customer_receipts(): void
    {
        [$organization] = $this->setupLedger();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $taxAccount = FinanceAccount::query()->create([
            'organization_id' => $organization->id, 'finance_scope_id' => $scope->id,
            'code' => '2300', 'name' => 'Output Tax', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_active' => true,
        ]);
        $tax = app(FinanceTaxConfigurationService::class)->createRate($organization, [
            'code' => 'GST10', 'name' => 'GST 10%', 'rate' => 10, 'applies_to' => 'sales', 'sales_account_id' => $taxAccount->id,
        ]);
        $customer = app(FinanceCustomerService::class)->createExternalCustomer($organization, ['name' => 'Example Customer']);

        $invoice = app(FinanceReceivablesPayablesService::class)->postSalesInvoice($organization, [
            'finance_customer_id' => $customer->id, 'invoice_date' => '2026-04-10', 'due_date' => '2026-05-10',
            'lines' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 50, 'finance_tax_rate_id' => $tax->id]],
        ]);

        $this->assertSame('100.0000', $invoice->subtotal);
        $this->assertSame('10.0000', $invoice->tax_total);
        $this->assertSame('110.0000', $invoice->balance_due);
        $this->assertCount(3, $invoice->journal->lines); // AR, income and tax lines.
        $this->assertEquals(110.0, (float) $invoice->journal->lines->sum('debit'));
        $this->assertEquals(110.0, (float) $invoice->journal->lines->sum('credit'));

        $cash = FinanceAccount::query()->where('organization_id', $organization->id)->where('code', '1000')->firstOrFail();
        $service = app(FinanceReceivablesPayablesService::class);
        $first = $service->recordPayment($organization, [
            'direction' => 'customer_receipt', 'payment_date' => '2026-04-15', 'finance_account_id' => $cash->id,
            'amount' => 40, 'allocations' => [['finance_sales_invoice_id' => $invoice->id, 'amount' => 40]],
        ]);
        $this->assertInstanceOf(FinancePayment::class, $first);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame('70.0000', $invoice->fresh()->balance_due);

        $service->recordPayment($organization, [
            'direction' => 'customer_receipt', 'payment_date' => '2026-04-20', 'finance_account_id' => $cash->id,
            'amount' => 70, 'allocations' => [['finance_sales_invoice_id' => $invoice->id, 'amount' => 70]],
        ]);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('0.0000', $invoice->fresh()->balance_due);
    }

    public function test_supplier_bill_posts_purchase_tax_and_tracks_partial_supplier_payments(): void
    {
        [$organization] = $this->setupLedger();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $taxAccount = FinanceAccount::query()->create([
            'organization_id' => $organization->id, 'finance_scope_id' => $scope->id,
            'code' => '1400', 'name' => 'Input Tax', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true,
        ]);
        $tax = app(FinanceTaxConfigurationService::class)->createRate($organization, [
            'code' => 'INPUT18', 'name' => 'Input GST 18%', 'rate' => 18, 'applies_to' => 'purchases', 'purchase_account_id' => $taxAccount->id,
        ]);
        $supplier = app(FinanceSupplierService::class)->create($organization, ['code' => 'SUP-01', 'name' => 'Example Supplier']);

        $bill = app(FinanceReceivablesPayablesService::class)->postPurchaseBill($organization, [
            'finance_supplier_id' => $supplier->id, 'bill_date' => '2026-04-10', 'due_date' => '2026-05-10',
            'supplier_invoice_number' => 'V-1001',
            'lines' => [['description' => 'Office supplies', 'quantity' => 1, 'unit_price' => 200, 'finance_tax_rate_id' => $tax->id]],
        ]);

        $this->assertSame('200.0000', $bill->subtotal);
        $this->assertSame('36.0000', $bill->tax_total);
        $this->assertSame('236.0000', $bill->balance_due);
        $cash = FinanceAccount::query()->where('organization_id', $organization->id)->where('code', '1000')->firstOrFail();
        app(FinanceReceivablesPayablesService::class)->recordPayment($organization, [
            'direction' => 'supplier_payment', 'payment_date' => '2026-04-20', 'finance_account_id' => $cash->id,
            'amount' => 100, 'allocations' => [['finance_purchase_bill_id' => $bill->id, 'amount' => 100]],
        ]);
        $this->assertSame('partially_paid', $bill->fresh()->status);
        $this->assertSame('136.0000', $bill->fresh()->balance_due);
        $aging = app(FinanceReceivablesPayablesService::class)->payablesAging($organization, '2026-06-01');
        $this->assertSame('136.0000', $aging['buckets']['days_1_30']);
    }

    public function test_payment_cannot_be_allocated_above_document_outstanding_balance(): void
    {
        [$organization] = $this->setupLedger();
        $customer = app(FinanceCustomerService::class)->createExternalCustomer($organization, ['name' => 'Customer']);
        $invoice = app(FinanceReceivablesPayablesService::class)->postSalesInvoice($organization, [
            'finance_customer_id' => $customer->id, 'invoice_date' => '2026-04-10', 'due_date' => '2026-05-10',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $cash = FinanceAccount::query()->where('organization_id', $organization->id)->where('code', '1000')->firstOrFail();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The invoice allocation exceeds its outstanding balance or the invoice is not payable.');
        app(FinanceReceivablesPayablesService::class)->recordPayment($organization, [
            'direction' => 'customer_receipt', 'payment_date' => '2026-04-20', 'finance_account_id' => $cash->id,
            'amount' => 101, 'allocations' => [['finance_sales_invoice_id' => $invoice->id, 'amount' => 101]],
        ]);
    }

    public function test_one_customer_receipt_can_be_allocated_across_multiple_invoices(): void
    {
        [$organization] = $this->setupLedger();
        $customer = app(FinanceCustomerService::class)->createExternalCustomer($organization, ['name' => 'Multi Invoice Customer']);
        $service = app(FinanceReceivablesPayablesService::class);
        $first = $service->postSalesInvoice($organization, [
            'finance_customer_id' => $customer->id, 'invoice_date' => '2026-04-10', 'due_date' => '2026-05-10',
            'lines' => [['description' => 'Service A', 'quantity' => 1, 'unit_price' => 60]],
        ]);
        $second = $service->postSalesInvoice($organization, [
            'finance_customer_id' => $customer->id, 'invoice_date' => '2026-04-11', 'due_date' => '2026-05-11',
            'lines' => [['description' => 'Service B', 'quantity' => 1, 'unit_price' => 40]],
        ]);
        $cash = FinanceAccount::query()->where('organization_id', $organization->id)->where('code', '1000')->firstOrFail();
        $payment = $service->recordPayment($organization, [
            'direction' => 'customer_receipt', 'payment_date' => '2026-04-20', 'finance_account_id' => $cash->id,
            'amount' => 100,
            'allocations' => [
                ['finance_sales_invoice_id' => $first->id, 'amount' => 60],
                ['finance_sales_invoice_id' => $second->id, 'amount' => 40],
            ],
        ]);

        $this->assertCount(2, $payment->allocations);
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertDatabaseHas('finance_receipts', ['finance_journal_id' => $payment->finance_journal_id]);
    }

    public function test_invoice_and_payment_journals_preserve_cost_centre_attribution(): void
    {
        [$organization] = $this->setupLedger();
        $center = FinanceCostCenter::query()->create([
            'organization_id' => $organization->id, 'name' => 'Unit North', 'code' => 'UNIT-NORTH',
            'owner_type' => 'unit', 'owner_id' => 999, 'is_active' => true,
        ]);
        $customer = app(FinanceCustomerService::class)->createExternalCustomer($organization, ['name' => 'Cost Centre Customer']);
        $invoice = app(FinanceReceivablesPayablesService::class)->postSalesInvoice($organization, [
            'finance_customer_id' => $customer->id, 'invoice_date' => '2026-04-10', 'due_date' => '2026-05-10',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'finance_cost_center_id' => $center->id]],
        ]);
        $this->assertTrue($invoice->journal->lines->every(fn ($line) => (int) $line->finance_cost_center_id === (int) $center->id));

        $cash = FinanceAccount::query()->where('organization_id', $organization->id)->where('code', '1000')->firstOrFail();
        $payment = app(FinanceReceivablesPayablesService::class)->recordPayment($organization, [
            'direction' => 'customer_receipt', 'payment_date' => '2026-04-20', 'finance_account_id' => $cash->id,
            'amount' => 25, 'allocations' => [['finance_sales_invoice_id' => $invoice->id, 'amount' => 25]],
        ]);
        $this->assertSame($center->id, $payment->allocations->first()->finance_cost_center_id);
        $this->assertTrue($payment->journal->lines->every(fn ($line) => (int) $line->finance_cost_center_id === (int) $center->id));
        $this->assertSame((string) $payment->id, (string) $payment->journal->source_id);
    }

    public function test_generated_invoice_journal_cannot_be_reversed_directly(): void
    {
        [$organization] = $this->setupLedger();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $customer = app(FinanceCustomerService::class)->createExternalCustomer($organization, ['name' => 'Journal Guard Customer']);
        $invoice = app(FinanceReceivablesPayablesService::class)->postSalesInvoice($organization, [
            'finance_customer_id' => $customer->id, 'invoice_date' => '2026-04-10', 'due_date' => '2026-05-10',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);
        $period = \Modules\Finance\Models\FinancePeriod::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A journal generated by a receivable or payable document cannot be reversed directly. Document reversal is not enabled for this document type.');
        app(FinanceJournalService::class)->reverse($organization, $scope, $invoice->journal, $period, '2026-04-20', 'Correction requested');
    }

    public function test_posted_sales_invoice_cannot_be_directly_edited(): void
    {
        [$organization] = $this->setupLedger();
        $customer = app(FinanceCustomerService::class)->createExternalCustomer($organization, ['name' => 'Immutable Customer']);
        $invoice = app(FinanceReceivablesPayablesService::class)->postSalesInvoice($organization, [
            'finance_customer_id' => $customer->id, 'invoice_date' => '2026-04-10', 'due_date' => '2026-05-10',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Posted sales invoices are immutable.');
        $invoice->update(['total' => 1]);
    }

    public function test_sales_invoice_request_key_prevents_duplicate_posting(): void
    {
        [$organization] = $this->setupLedger();
        $customer = app(FinanceCustomerService::class)->createExternalCustomer($organization, ['name' => 'Idempotent Customer']);
        $service = app(FinanceReceivablesPayablesService::class);
        $data = [
            'request_key' => 'invoice-request-001', 'finance_customer_id' => $customer->id,
            'invoice_date' => '2026-04-10', 'due_date' => '2026-05-10',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ];
        $first = $service->postSalesInvoice($organization, $data);
        $second = $service->postSalesInvoice($organization, $data);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('finance_sales_invoices', 1);
        $this->assertDatabaseCount('finance_journals', 1);
    }

    public function test_receivables_ageing_groups_open_invoices_by_due_date(): void
    {
        [$organization] = $this->setupLedger();
        $customer = app(FinanceCustomerService::class)->createExternalCustomer($organization, ['name' => 'Customer']);
        app(FinanceReceivablesPayablesService::class)->postSalesInvoice($organization, [
            'finance_customer_id' => $customer->id, 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-15',
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 125]],
        ]);
        $aging = app(FinanceReceivablesPayablesService::class)->receivablesAging($organization, '2026-11-01');
        $this->assertSame('125.0000', $aging['buckets']['days_1_30']);
        $this->assertSame('125.0000', $aging['total_outstanding']);
    }
}
