<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Enums\FinanceAccountType;
use Modules\Finance\Enums\FinanceNormalBalance;
use Modules\Finance\Enums\FinanceScopeType;
use Modules\Finance\Enums\FinanceItemSource;
use Modules\Finance\Enums\FinanceCustomerType;
use Modules\Finance\Enums\FinancePeriodStatus;
use Modules\Finance\Services\FinanceReceiptService;
use Modules\Finance\Models\FinanceReceipt;
use Modules\Finance\Enums\FinanceReceiptStatus;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceCustomer;
use Modules\Finance\Models\FinanceItem;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceCustomerService;
use Modules\Finance\Services\FinanceItemService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\MemberPaymentFinanceService;
use Modules\Member\Models\MemberPayment;
use Modules\Member\Events\MembershipPaymentReceived;
use Modules\Core\Services\OrganizationModuleService;
use Tests\TestCase;

class FinanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_provisions_only_an_organization_scope(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);

        $service = app(FinanceScopeService::class);
        $service->provisionOrganizationScopes($organization);

        $this->assertDatabaseHas('finance_scopes', [
            'organization_id' => $organization->id,
            'scope_type' => FinanceScopeType::ORGANIZATION->value,
            'scope_id' => $organization->id,
        ]);
        $this->assertDatabaseMissing('finance_scopes', [
            'organization_id' => $organization->id,
            'scope_type' => FinanceScopeType::UNIT->value,
            'scope_id' => $unit->id,
        ]);
    }

    public function test_default_accounts_are_provisioned_for_the_organization_wide_chart(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);

        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);

        $this->assertSame(7, FinanceAccount::query()->where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('finance_accounts', ['code' => '1000', 'account_type' => FinanceAccountType::ASSET->value, 'normal_balance' => FinanceNormalBalance::DEBIT->value]);
    }

    public function test_module_item_is_system_managed_and_idempotently_resolved(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $account = app(FinanceAccountProvisioningService::class)->ensure($organization, $scope, [
            'code' => '4100', 'name' => 'Membership Subscription Income', 'account_type' => 'income', 'normal_balance' => 'credit',
            'source_type' => 'MemberPaymentItem', 'source_id' => 10,
        ]);

        $service = app(FinanceItemService::class);
        $definition = [
            'sku' => 'MEM-SUB-10', 'title' => 'Subscription Fee', 'selling_price' => 500,
            'currency' => 'INR', 'sales_account_id' => $account->id,
            'source_type' => 'MemberPaymentItem', 'source_id' => 10,
        ];
        $first = $service->resolveModuleItem($organization, $definition);
        $second = $service->resolveModuleItem($organization, $definition);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->is_system_managed);
        $this->assertSame(FinanceItemSource::MODULE, $second->source);
    }

    public function test_financial_period_can_be_created_for_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);

        $period = app(FinancePeriodService::class)->create(
            $organization,
            $scope,
            [
                'name' => 'FY 2026',
                'code' => 'FY2026',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ],
        );

        $this->assertSame($organization->id, $period->organization_id);
        $this->assertSame($scope->id, $period->finance_scope_id);
        $this->assertSame(FinancePeriodStatus::OPEN, $period->status);
        $this->assertTrue($period->isOpen());
        $this->assertDatabaseHas('finance_periods', [
            'id' => $period->id,
            'organization_id' => $organization->id,
            'finance_scope_id' => $scope->id,
            'code' => 'FY2026',
            'status' => FinancePeriodStatus::OPEN->value,
        ]);
    }

    public function test_financial_period_rejects_invalid_date_range(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The financial period start date cannot be after the end date.'
        );

        app(FinancePeriodService::class)->create(
            $organization,
            $scope,
            [
                'name' => 'Invalid Period',
                'code' => 'INVALID',
                'starts_on' => '2026-12-31',
                'ends_on' => '2026-01-01',
            ],
        );
    }

    public function test_financial_periods_cannot_overlap_within_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $service = app(FinancePeriodService::class);

        $service->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The financial period overlaps an existing period.'
        );

        $service->create($organization, $scope, [
            'name' => 'Second Period',
            'code' => 'SECOND',
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-05-31',
        ]);
    }

    public function test_unit_scope_cannot_create_a_separate_financial_period(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $unitScope = app(FinanceScopeService::class)->unitScope($organization, $unit);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Financial periods must belong to the organization-wide ledger.');

        app(FinancePeriodService::class)->create($organization, $unitScope, [
            'name' => 'Unit FY 2026',
            'code' => 'UNIT-FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    public function test_unit_scope_cannot_provision_a_separate_chart_of_accounts(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $unitScope = app(FinanceScopeService::class)->unitScope($organization, $unit);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Finance accounts must belong to the organization-wide chart of accounts.');

        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $unitScope);
    }

    public function test_financial_period_can_be_closed(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $closed = app(FinancePeriodService::class)->close($period);

        $this->assertSame(FinancePeriodStatus::CLOSED, $closed->status);
        $this->assertTrue($closed->isClosed());
        $this->assertNotNull($closed->closed_at);
    }

    public function test_closed_financial_period_is_rejected_as_open(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $period = app(FinancePeriodService::class)->close($period);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The financial period is closed.');

        app(FinancePeriodService::class)->assertOpen($period);
    }

    public function test_member_customer_is_keyed_by_organization_and_user(): void
    {
        $organization = Organization::factory()->create();
        $user = \App\Models\User::factory()->create();

        $service = app(FinanceCustomerService::class);
        $first = $service->resolveMemberCustomer($organization, $user);
        $second = $service->resolveMemberCustomer($organization, $user, ['name' => 'Updated Name']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Updated Name', $second->name);
        $this->assertSame(FinanceCustomerType::MEMBER, $second->customer_type);
        $this->assertTrue($second->is_system_managed);
    }

    public function test_balanced_journal_can_be_posted(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);

        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();

        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'description' => 'Membership payment',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);

        $this->assertSame(FinanceJournalStatus::POSTED, $journal->status);
        $this->assertCount(2, $journal->lines);
        $this->assertDatabaseHas('finance_journals', [
            'id' => $journal->id,
            'finance_scope_id' => $scope->id,
            'finance_period_id' => $period->id,
            'status' => FinanceJournalStatus::POSTED->value,
        ]);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The journal is not balanced. Total debits must equal total credits.');

        app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '900.00'],
            ],
        ]);
    }

    public function test_journal_rejects_line_with_both_debit_and_credit(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each journal line must contain either a debit or a credit amount.');

        app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00', 'credit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);
    }

    public function test_journal_rejects_account_from_another_scope(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $scopeService = app(FinanceScopeService::class);
        $scope = $scopeService->organizationScope($organization);
        $otherScope = $scopeService->unitScope($organization, $unit);
        $provisioner = app(FinanceAccountProvisioningService::class);
        $provisioner->provisionDefaults($organization, $scope);
        // Simulate a legacy unit-scope account so the journal service continues
        // to prove it rejects cross-scope account references in historical data.
        $otherCash = FinanceAccount::create([
            'organization_id' => $organization->id,
            'finance_scope_id' => $otherScope->id,
            'code' => '1000',
            'name' => 'Legacy Unit Cash',
            'account_type' => FinanceAccountType::ASSET,
            'normal_balance' => FinanceNormalBalance::DEBIT,
            'is_system_managed' => true,
            'is_active' => true,
            'metadata' => [],
        ]);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The journal account does not belong to the finance scope.');

        app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'lines' => [
                ['finance_account_id' => $otherCash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);
    }

    public function test_journal_rejects_transaction_outside_period(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The journal transaction date is outside the financial period.');

        app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2027-01-01',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);
    }

    public function test_journal_rejects_closed_period(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $periodService = app(FinancePeriodService::class);
        $period = $periodService->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $periodService->close($period);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The financial period is closed.');

        app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);
    }

    public function test_posted_journal_cannot_be_modified_or_deleted(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();
        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Posted journals are immutable.');
        $journal->description = 'Changed';
        $journal->save();
    }

    public function test_receipt_can_be_issued_for_a_posted_journal(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();

        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'source_type' => 'MembershipPayment',
            'source_id' => 'payment-123',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);

        $receipt = app(FinanceReceiptService::class)->issue($organization, $scope, $journal);

        $this->assertSame(FinanceReceiptStatus::ISSUED, $receipt->status);
        $this->assertSame($journal->id, $receipt->finance_journal_id);
        $this->assertSame('MembershipPayment', $receipt->source_type);
        $this->assertSame('payment-123', $receipt->source_id);
        $this->assertStringStartsWith('RCT-', $receipt->receipt_number);
        $this->assertTrue($receipt->isIssued());
        $this->assertDatabaseHas('finance_receipts', [
            'id' => $receipt->id,
            'finance_journal_id' => $journal->id,
            'status' => FinanceReceiptStatus::ISSUED->value,
        ]);
    }

    public function test_receipt_issuance_is_idempotent_for_the_same_journal(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();
        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);

        $service = app(FinanceReceiptService::class);
        $first = $service->issue($organization, $scope, $journal);
        $second = $service->issue($organization, $scope, $journal);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->receipt_number, $second->receipt_number);
        $this->assertSame(1, FinanceReceipt::query()->where('finance_journal_id', $journal->id)->count());
    }

    public function test_receipt_requires_a_posted_journal(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $journal = FinanceJournal::create([
            'organization_id' => $organization->id,
            'finance_scope_id' => $scope->id,
            'finance_period_id' => $period->id,
            'journal_number' => 'JRN-DRAFT',
            'transaction_date' => '2026-10-08',
            'status' => FinanceJournalStatus::DRAFT,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A receipt can only be issued for a posted journal.');

        app(FinanceReceiptService::class)->issue($organization, $scope, $journal);
    }

    public function test_receipt_rejects_journal_from_another_scope(): void
    {
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $scopeService = app(FinanceScopeService::class);
        $scope = $scopeService->organizationScope($organization);
        $otherScope = $scopeService->unitScope($organization, $unit);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);

        // Simulate a legacy posted journal from a unit scope. New journals must
        // use the organization ledger, but receipt issuance must still reject
        // historical journals whose scope does not match the requested scope.
        $journal = FinanceJournal::create([
            'organization_id' => $organization->id,
            'finance_scope_id' => $otherScope->id,
            'finance_period_id' => $period->id,
            'journal_number' => 'JRN-LEGACY-UNIT',
            'transaction_date' => '2026-10-08',
            'status' => FinanceJournalStatus::POSTED,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The journal does not belong to the finance scope.');

        app(FinanceReceiptService::class)->issue($organization, $scope, $journal);
    }

    public function test_finance_receipt_cannot_be_modified_or_deleted(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();
        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ]);
        $receipt = app(FinanceReceiptService::class)->issue($organization, $scope, $journal);

        try {
            $receipt->receipt_number = 'CHANGED';
            $receipt->save();
            $this->fail('Expected receipt update to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Finance receipts are immutable.', $e->getMessage());
        }

        try {
            $receipt->delete();
            $this->fail('Expected receipt deletion to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Finance receipts cannot be deleted.', $e->getMessage());
        }
    }


    public function test_membership_payment_creates_finance_journal_and_receipt(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');

        $scope = app(FinanceScopeService::class)->organizationScope($organization);

        app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'receipt_number' => 'MP-TEST-001',
            'total_amount' => 500,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organization->id,
            'received_at' => '2026-10-08 10:00:00',
            'metadata' => [],
        ]);

        $receipt = app(MemberPaymentFinanceService::class)->handle($payment);

        $this->assertNotNull($receipt);
        $this->assertSame('member_payment', $receipt->source_type);
        $this->assertSame($payment->id, $receipt->source_id);

        $journal = FinanceJournal::query()
            ->where('source_type', 'member_payment')
            ->where('source_id', $payment->id)
            ->with('lines')
            ->firstOrFail();

        $this->assertTrue($journal->isPosted());
        $this->assertCount(2, $journal->lines);

        $this->assertEquals(500.0, (float) $journal->lines->sum('debit'));
        $this->assertEquals(500.0, (float) $journal->lines->sum('credit'));
    }

    public function test_unit_collected_membership_payment_uses_organization_ledger_with_unit_attribution(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');

        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $scopes = app(FinanceScopeService::class);
        $organizationScope = $scopes->organizationScope($organization);
        $period = app(FinancePeriodService::class)->create($organization, $organizationScope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'receipt_number' => 'MP-UNIT-PERIOD-001',
            'total_amount' => 425,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'collector_scope_type' => 'unit',
            'collector_scope_id' => $unit->id,
            'received_at' => '2026-10-08 10:00:00',
            'metadata' => [],
        ]);

        $receipt = app(MemberPaymentFinanceService::class)->handle($payment);

        $this->assertNotNull($receipt);
        $journal = FinanceJournal::query()
            ->where('source_type', 'member_payment')
            ->where('source_id', $payment->id)
            ->firstOrFail();

        $this->assertSame($organizationScope->id, $journal->finance_scope_id);
        $this->assertSame($unit->id, $journal->unit_id);
        $this->assertSame($period->id, $journal->finance_period_id);
        $this->assertSame($organizationScope->id, $journal->period->finance_scope_id);
    }

    public function test_membership_payment_finance_processing_is_idempotent(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');
        $scope = app(FinanceScopeService::class)->organizationScope($organization);

        app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'receipt_number' => 'MP-TEST-002',
            'total_amount' => 750,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organization->id,
            'received_at' => '2026-10-08 10:00:00',
            'metadata' => [],
        ]);

        $service = app(MemberPaymentFinanceService::class);

        $first = $service->handle($payment);
        $second = $service->handle($payment);

        $this->assertSame($first->id, $second->id);

        $this->assertSame(
            1,
            FinanceJournal::query()
                ->where('source_type', 'member_payment')
                ->where('source_id', $payment->id)
                ->count()
        );

        $this->assertSame(
            1,
            FinanceReceipt::query()
                ->where('source_type', 'member_payment')
                ->where('source_id', $payment->id)
                ->count()
        );
    }


    public function test_financial_year_pattern_generates_a_draft_and_requires_explicit_opening(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $service = app(FinancePeriodService::class);

        $period = $service->generateDraft($organization, $scope, 2026, [
            'start_month' => 4,
            'start_day' => 1,
            'end_month' => 3,
            'end_day' => 31,
        ]);

        $this->assertSame('FY26-27', $period->code);
        $this->assertSame('2026-04-01', $period->starts_on->toDateString());
        $this->assertSame('2027-03-31', $period->ends_on->toDateString());
        $this->assertSame(FinancePeriodStatus::DRAFT, $period->status);
        $this->assertNull($service->forDate($scope, '2026-10-22'));

        $opened = $service->open($period);

        $this->assertSame(FinancePeriodStatus::OPEN, $opened->status);
        $this->assertSame($period->id, $service->forDate($scope, '2026-10-22')?->id);
    }

    public function test_generated_financial_year_cannot_overlap_an_existing_period(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $service = app(FinancePeriodService::class);

        $service->create($organization, $scope, [
            'name' => 'Existing period',
            'code' => 'CUSTOM-2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The financial period overlaps an existing period.');

        $service->generateDraft($organization, $scope, 2026, [
            'start_month' => 4,
            'start_day' => 1,
            'end_month' => 3,
            'end_day' => 31,
        ]);
    }

    public function test_membership_payment_finance_processing_requires_a_financial_period(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'receipt_number' => 'MP-TEST-003',
            'total_amount' => 250,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organization->id,
            'received_at' => '2026-10-08 10:00:00',
            'metadata' => [],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'No open financial period exists for the membership payment date.'
        );

        app(MemberPaymentFinanceService::class)->handle($payment);
    }




    public function test_membership_payment_received_event_is_consumed_by_finance(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');
        $scope = app(FinanceScopeService::class)->organizationScope($organization);

        app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $payment = MemberPayment::create([
            'organization_id' => $organization->id,
            'receipt_number' => 'MP-EVENT-001',
            'total_amount' => 300,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'collector_scope_type' => 'organization',
            'collector_scope_id' => $organization->id,
            'received_at' => '2026-10-08 10:00:00',
            'metadata' => [],
        ]);

        MembershipPaymentReceived::dispatch($payment);

        $this->assertDatabaseHas('finance_journals', [
            'organization_id' => $organization->id,
            'source_type' => 'member_payment',
            'source_id' => $payment->id,
        ]);

        $this->assertDatabaseHas('finance_receipts', [
            'organization_id' => $organization->id,
            'source_type' => 'member_payment',
            'source_id' => $payment->id,
        ]);
    }

    public function test_manual_chart_accounts_use_organization_scope_and_derived_normal_balance(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(\Modules\Finance\Services\FinanceChartOfAccountsService::class);

        $parent = $chart->createManual($organization, $scope, [
            'code' => '5100', 'name' => 'Operating Expenses', 'account_type' => 'expense',
        ]);
        $child = $chart->createManual($organization, $scope, [
            'code' => '5110', 'name' => 'Office Supplies', 'account_type' => 'expense', 'parent_id' => $parent->id,
        ]);

        $this->assertSame(FinanceNormalBalance::DEBIT, $parent->normal_balance);
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertFalse($child->is_system_managed);
        $this->assertSame($organization->id, $child->organization_id);
        $this->assertSame($scope->id, $child->finance_scope_id);
    }

    public function test_chart_rejects_parent_from_another_account_type_or_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $otherScope = app(FinanceScopeService::class)->organizationScope($otherOrganization);
        $chart = app(\Modules\Finance\Services\FinanceChartOfAccountsService::class);
        $asset = $chart->createManual($organization, $scope, ['code' => '1300', 'name' => 'Equipment', 'account_type' => 'asset']);
        $foreign = $chart->createManual($otherOrganization, $otherScope, ['code' => '1310', 'name' => 'Foreign Asset', 'account_type' => 'asset']);

        try {
            $chart->createManual($organization, $scope, ['code' => '4100', 'name' => 'Income', 'account_type' => 'income', 'parent_id' => $asset->id]);
            $this->fail('A parent with a different account type must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('same account type', $exception->getMessage());
        }

        try {
            $chart->createManual($organization, $scope, ['code' => '1320', 'name' => 'Invalid Child', 'account_type' => 'asset', 'parent_id' => $foreign->id]);
            $this->fail('A parent from another organization must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('this organization chart', $exception->getMessage());
        }
    }

    public function test_system_managed_accounts_cannot_be_edited_or_deactivated(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $account = app(FinanceAccountProvisioningService::class)->ensure($organization, $scope, [
            'code' => '4000', 'name' => 'Sales Income', 'account_type' => 'income', 'normal_balance' => 'credit',
        ]);
        $chart = app(\Modules\Finance\Services\FinanceChartOfAccountsService::class);

        try {
            $chart->updateManual($organization, $scope, $account, ['code' => '4001', 'name' => 'Changed', 'parent_id' => null]);
            $this->fail('System-managed account editing must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('System-managed', $exception->getMessage());
        }

        try {
            $chart->deactivate($organization, $scope, $account);
            $this->fail('System-managed account deactivation must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('System-managed', $exception->getMessage());
        }
    }

    public function test_manual_account_cannot_be_deactivated_while_it_has_active_children(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(\Modules\Finance\Services\FinanceChartOfAccountsService::class);
        $parent = $chart->createManual($organization, $scope, ['code' => '5200', 'name' => 'General Expenses', 'account_type' => 'expense']);
        $chart->createManual($organization, $scope, ['code' => '5210', 'name' => 'Travel', 'account_type' => 'expense', 'parent_id' => $parent->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('active child accounts');
        $chart->deactivate($organization, $scope, $parent);
    }

}

