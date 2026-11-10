<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceCashFlowStatementService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceCashFlowStatementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private $period;
    private FinanceAccount $cash;
    private FinanceAccount $income;
    private FinanceAccount $expense;
    private FinanceAccount $equipment;
    private FinanceAccount $loan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->scope = app(FinanceScopeService::class)->organizationScope($this->organization);

        app(FinanceAccountProvisioningService::class)->provisionDefaults(
            $this->organization,
            $this->scope,
        );

        $this->cash = $this->account('1000');
        $this->income = $this->account('4000');
        $this->expense = $this->account('5000');

        // The account configuration fields are introduced with this feature.
        $this->cash->update(['is_cash_account' => true]);
        $this->income->update(['cash_flow_category' => 'operating']);
        $this->expense->update(['cash_flow_category' => 'operating']);

        $this->equipment = $this->createAccount('1500', 'Equipment', 'asset', 'debit', [
            'cash_flow_category' => 'investing',
        ]);
        $this->loan = $this->createAccount('2500', 'Long-term Loan', 'liability', 'credit', [
            'cash_flow_category' => 'financing',
        ]);

        $this->period = app(FinancePeriodService::class)->create(
            $this->organization,
            $this->scope,
            [
                'name' => 'Financial Year 2026',
                'code' => 'FY2026',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ],
        );
    }

    private function account(string $code): FinanceAccount
    {
        return FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function createAccount(
        string $code,
        string $name,
        string $type,
        string $normalBalance,
        array $attributes = [],
    ): FinanceAccount {
        return FinanceAccount::create(array_merge([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'normal_balance' => $normalBalance,
            'is_system_managed' => false,
            'is_active' => true,
            'metadata' => [],
        ], $attributes));
    }

    private function postJournal(string $date, string $description, array $lines, ?int $unitId = null): FinanceJournal
    {
        return app(FinanceJournalService::class)->post(
            $this->organization,
            $this->scope,
            $this->period,
            [
                'transaction_date' => $date,
                'description' => $description,
                'unit_id' => $unitId,
                'lines' => $lines,
            ],
        );
    }

    private function debit(int $accountId, string $amount): array
    {
        return ['finance_account_id' => $accountId, 'debit' => $amount];
    }

    private function credit(int $accountId, string $amount): array
    {
        return ['finance_account_id' => $accountId, 'credit' => $amount];
    }

    private function report(string $startDate, string $endDate, ?int $unitId = null): array
    {
        return app(FinanceCashFlowStatementService::class)->generate(
            $this->organization,
            $this->scope,
            $startDate,
            $endDate,
            $unitId,
        );
    }

    public function test_cash_flow_statement_classifies_operating_investing_and_financing_cash_flows(): void
    {
        $this->postJournal('2026-01-05', 'Membership collections', [
            $this->debit($this->cash->id, '500.0000'),
            $this->credit($this->income->id, '500.0000'),
        ]);
        $this->postJournal('2026-02-05', 'Operating expense paid', [
            $this->debit($this->expense->id, '125.1250'),
            $this->credit($this->cash->id, '125.1250'),
        ]);
        $this->postJournal('2026-03-05', 'Equipment purchase', [
            $this->debit($this->equipment->id, '200.0000'),
            $this->credit($this->cash->id, '200.0000'),
        ]);
        $this->postJournal('2026-04-05', 'Loan proceeds', [
            $this->debit($this->cash->id, '300.0000'),
            $this->credit($this->loan->id, '300.0000'),
        ]);

        $report = $this->report('2026-01-01', '2026-12-31');

        $this->assertSame('374.8750', $report['operating_net_cash_flow']);
        $this->assertSame('-200.0000', $report['investing_net_cash_flow']);
        $this->assertSame('300.0000', $report['financing_net_cash_flow']);
        $this->assertSame('474.8750', $report['net_change_in_cash']);
        $this->assertSame('474.8750', $report['closing_cash_balance']);
    }

    public function test_cash_flow_statement_uses_only_cash_account_movements_and_ignores_non_cash_journals(): void
    {
        $receivable = $this->createAccount('1210', 'Accounts Receivable', 'asset', 'debit', [
            'cash_flow_category' => 'operating',
        ]);

        // A journal without a cash/bank line must not create cash flow.
        $this->postJournal('2026-02-01', 'Credit sale not yet collected', [
            $this->debit($receivable->id, '250.0000'),
            $this->credit($this->income->id, '250.0000'),
        ]);

        $report = $this->report('2026-01-01', '2026-12-31');

        $this->assertSame('0.0000', $report['operating_net_cash_flow']);
        $this->assertSame('0.0000', $report['net_change_in_cash']);
    }

    public function test_transfer_between_cash_accounts_is_excluded_from_cash_flow_totals(): void
    {
        $bank = $this->createAccount('1010', 'Bank Account', 'asset', 'debit', [
            'is_cash_account' => true,
        ]);

        $this->postJournal('2026-02-01', 'Cash deposited into bank', [
            $this->debit($bank->id, '400.0000'),
            $this->credit($this->cash->id, '400.0000'),
        ]);

        $report = $this->report('2026-01-01', '2026-12-31');

        $this->assertSame('0.0000', $report['operating_net_cash_flow']);
        $this->assertSame('0.0000', $report['investing_net_cash_flow']);
        $this->assertSame('0.0000', $report['financing_net_cash_flow']);
        $this->assertSame('0.0000', $report['net_change_in_cash']);
    }

    public function test_cash_flow_statement_uses_inclusive_date_range_and_excludes_draft_and_later_journals(): void
    {
        $this->postJournal('2026-01-01', 'Opening day receipt', [
            $this->debit($this->cash->id, '100.0000'),
            $this->credit($this->income->id, '100.0000'),
        ]);
        $this->postJournal('2026-06-30', 'End date payment', [
            $this->debit($this->expense->id, '25.0000'),
            $this->credit($this->cash->id, '25.0000'),
        ]);
        $this->postJournal('2026-07-01', 'After end date', [
            $this->debit($this->cash->id, '90.0000'),
            $this->credit($this->income->id, '90.0000'),
        ]);

        $draft = FinanceJournal::create([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'finance_period_id' => $this->period->id,
            'journal_number' => 'JRN-DRAFT-CASH-FLOW',
            'transaction_date' => '2026-04-01',
            'description' => 'Draft receipt',
            'status' => 'draft',
            'metadata' => [],
        ]);
        $draft->lines()->createMany([
            ['finance_account_id' => $this->cash->id, 'debit' => '700.0000', 'credit' => '0.0000', 'metadata' => []],
            ['finance_account_id' => $this->income->id, 'debit' => '0.0000', 'credit' => '700.0000', 'metadata' => []],
        ]);

        $report = $this->report('2026-01-01', '2026-06-30');

        $this->assertSame('75.0000', $report['operating_net_cash_flow']);
        $this->assertSame('75.0000', $report['net_change_in_cash']);
    }

    public function test_cash_flow_statement_unit_filter_and_all_units_include_organization_wide_entries(): void
    {
        $unitA = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $this->organization->id]);

        $this->postJournal('2026-05-01', 'Organization-wide receipt', [
            $this->debit($this->cash->id, '10.0000'),
            $this->credit($this->income->id, '10.0000'),
        ]);
        $this->postJournal('2026-05-02', 'Unit A receipt', [
            $this->debit($this->cash->id, '20.0000'),
            $this->credit($this->income->id, '20.0000'),
        ], $unitA->id);
        $this->postJournal('2026-05-03', 'Unit B receipt', [
            $this->debit($this->cash->id, '30.0000'),
            $this->credit($this->income->id, '30.0000'),
        ], $unitB->id);

        $unitReport = $this->report('2026-01-01', '2026-12-31', $unitA->id);
        $allUnitsReport = $this->report('2026-01-01', '2026-12-31');

        $this->assertSame('20.0000', $unitReport['operating_net_cash_flow']);
        $this->assertSame('60.0000', $allUnitsReport['operating_net_cash_flow']);
    }

    public function test_cash_flow_statement_rejects_invalid_date_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cash Flow Statement dates must use the YYYY-MM-DD format.');

        $this->report('2026-02-30', '2026-12-31');
    }

    public function test_cash_flow_statement_rejects_start_date_after_end_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Cash Flow Statement start date must not be after its end date.');

        $this->report('2026-12-31', '2026-01-01');
    }

    public function test_cash_flow_statement_rejects_unit_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected unit does not belong to the organization.');

        $this->report('2026-01-01', '2026-12-31', $otherUnit->id);
    }

    public function test_cash_flow_statement_rejects_scope_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherScope = app(FinanceScopeService::class)->organizationScope($otherOrganization);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The finance scope does not belong to the organization.');

        app(FinanceCashFlowStatementService::class)->generate(
            $this->organization,
            $otherScope,
            '2026-01-01',
            '2026-12-31',
        );
    }

}
