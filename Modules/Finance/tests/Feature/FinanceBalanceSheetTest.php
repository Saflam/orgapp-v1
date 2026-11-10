<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceBalanceSheetService;
use Tests\TestCase;

class FinanceBalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private $period;
    private FinanceAccount $cash;
    private FinanceAccount $payable;
    private FinanceAccount $equity;
    private FinanceAccount $income;
    private FinanceAccount $expense;

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
        $this->payable = $this->account('2000');
        $this->equity = $this->account('3000');
        $this->income = $this->account('4000');
        $this->expense = $this->account('5000');

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
    ): FinanceAccount {
        return FinanceAccount::create([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'normal_balance' => $normalBalance,
            'is_system_managed' => false,
            'is_active' => true,
            'metadata' => [],
        ]);
    }

    private function postJournal(
        string $date,
        string $description,
        FinanceAccount $debitAccount,
        FinanceAccount $creditAccount,
        string $amount,
        ?int $unitId = null,
    ): FinanceJournal {
        return app(FinanceJournalService::class)->post(
            $this->organization,
            $this->scope,
            $this->period,
            [
                'transaction_date' => $date,
                'description' => $description,
                'unit_id' => $unitId,
                'lines' => [
                    ['finance_account_id' => $debitAccount->id, 'debit' => $amount],
                    ['finance_account_id' => $creditAccount->id, 'credit' => $amount],
                ],
            ],
        );
    }

    private function report(string $asOfDate, ?int $unitId = null): array
    {
        return app(FinanceBalanceSheetService::class)->generate(
            $this->organization,
            $this->scope,
            $asOfDate,
            $unitId,
        );
    }

    public function test_balance_sheet_calculates_assets_liabilities_equity_and_current_earnings_as_of_date(): void
    {
        $this->postJournal('2026-01-05', 'Owner contribution', $this->cash, $this->equity, '1000.2500');
        $this->postJournal('2026-02-10', 'Customer receipt', $this->cash, $this->income, '500.0000');
        $this->postJournal('2026-03-10', 'Operating expense', $this->expense, $this->cash, '125.1250');
        $this->postJournal('2026-04-10', 'Supplier credit', $this->cash, $this->payable, '200.0000');
        $this->postJournal('2026-11-01', 'Later receipt', $this->cash, $this->income, '75.0000');

        $report = $this->report('2026-10-31');

        $assetRows = collect($report['asset_accounts']);
        $liabilityRows = collect($report['liability_accounts']);
        $equityRows = collect($report['equity_accounts']);

        $this->assertSame('1575.1250', $assetRows->firstWhere('account_id', $this->cash->id)['amount']);
        $this->assertSame('200.0000', $liabilityRows->firstWhere('account_id', $this->payable->id)['amount']);
        $this->assertSame('1000.2500', $equityRows->firstWhere('account_id', $this->equity->id)['amount']);

        $this->assertSame('374.8750', $report['current_earnings']['amount']);
        $this->assertSame('1575.1250', $report['total_assets']);
        $this->assertSame('200.0000', $report['total_liabilities']);
        $this->assertSame('1000.2500', $report['total_equity']);
        $this->assertSame('1575.1250', $report['total_liabilities_and_equity']);
        $this->assertSame('0.0000', $report['difference']);
        $this->assertSame('2026-10-31', $report['as_of_date']);
    }

    public function test_balance_sheet_includes_cumulative_activity_from_prior_periods(): void
    {
        $this->postJournal('2026-01-10', 'Opening capital', $this->cash, $this->equity, '300.0000');
        $this->postJournal('2026-02-10', 'Current period receipt', $this->cash, $this->income, '90.0000');

        $report = $this->report('2026-12-31');

        $this->assertSame('390.0000', $report['total_assets']);
        $this->assertSame('300.0000', $report['total_equity']);
        $this->assertSame('90.0000', $report['current_earnings']['amount']);
        $this->assertSame('390.0000', $report['total_liabilities_and_equity']);
        $this->assertSame('0.0000', $report['difference']);
    }

    public function test_balance_sheet_excludes_draft_journals(): void
    {
        $this->postJournal('2026-05-01', 'Posted capital', $this->cash, $this->equity, '80.0000');

        $draft = FinanceJournal::create([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'finance_period_id' => $this->period->id,
            'journal_number' => 'JRN-DRAFT-BALANCE-SHEET',
            'transaction_date' => '2026-06-01',
            'description' => 'Draft transaction',
            'status' => FinanceJournalStatus::DRAFT,
            'metadata' => [],
        ]);

        $draft->lines()->createMany([
            ['finance_account_id' => $this->cash->id, 'debit' => '900.0000', 'credit' => '0.0000', 'metadata' => []],
            ['finance_account_id' => $this->equity->id, 'debit' => '0.0000', 'credit' => '900.0000', 'metadata' => []],
        ]);

        $report = $this->report('2026-12-31');

        $this->assertSame('80.0000', $report['total_assets']);
        $this->assertSame('80.0000', $report['total_equity']);
        $this->assertSame('0.0000', $report['difference']);
    }

    public function test_reversal_journal_offsets_the_original_in_the_cumulative_balance_sheet(): void
    {
        $original = $this->postJournal(
            '2026-03-10',
            'Owner contribution',
            $this->cash,
            $this->equity,
            '125.5000',
        );

        app(FinanceJournalService::class)->reverse(
            $this->organization,
            $this->scope,
            $original,
            $this->period,
            '2026-04-01',
            'Reverse the incorrect contribution.',
        );

        $report = $this->report('2026-12-31');

        $this->assertSame('0.0000', $report['total_assets']);
        $this->assertSame('0.0000', $report['total_equity']);
        $this->assertSame('0.0000', $report['current_earnings']['amount']);
        $this->assertSame('0.0000', $report['difference']);
    }

    public function test_balance_sheet_excludes_activity_after_the_as_of_date(): void
    {
        $this->postJournal('2026-07-01', 'Before cutoff', $this->cash, $this->equity, '35.0000');
        $this->postJournal('2026-07-02', 'After cutoff', $this->cash, $this->equity, '45.0000');

        $report = $this->report('2026-07-01');

        $this->assertSame('35.0000', $report['total_assets']);
        $this->assertSame('35.0000', $report['total_equity']);
        $this->assertSame('0.0000', $report['difference']);
    }

    public function test_balance_sheet_unit_filter_isolates_activity_and_all_units_includes_unattributed_entries(): void
    {
        $unitA = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $this->organization->id]);

        $this->postJournal('2026-05-01', 'Organization-wide capital', $this->cash, $this->equity, '10.0000');
        $this->postJournal('2026-05-02', 'Unit A capital', $this->cash, $this->equity, '20.0000', $unitA->id);
        $this->postJournal('2026-05-03', 'Unit B capital', $this->cash, $this->equity, '30.0000', $unitB->id);

        $unitReport = $this->report('2026-12-31', $unitA->id);
        $allUnitsReport = $this->report('2026-12-31');

        $this->assertSame('20.0000', $unitReport['total_assets']);
        $this->assertSame('20.0000', $unitReport['total_equity']);
        $this->assertSame('60.0000', $allUnitsReport['total_assets']);
        $this->assertSame('60.0000', $allUnitsReport['total_equity']);
    }

    public function test_balance_sheet_keeps_inactive_accounts_with_posted_history_and_hides_unused_inactive_accounts(): void
    {
        $usedAsset = $this->createAccount('1500', 'Archived Asset', 'asset', 'debit');
        $unusedAsset = $this->createAccount('1510', 'Unused Archived Asset', 'asset', 'debit');

        $this->postJournal('2026-05-01', 'Archived asset purchase', $usedAsset, $this->equity, '40.0000');

        $usedAsset->update(['is_active' => false]);
        $unusedAsset->update(['is_active' => false]);

        $report = $this->report('2026-12-31');
        $rows = collect($report['asset_accounts']);

        $this->assertSame('40.0000', $rows->firstWhere('account_id', $usedAsset->id)['amount']);
        $this->assertNull($rows->firstWhere('account_id', $unusedAsset->id));
    }

    public function test_balance_sheet_isolated_to_the_requested_organization_and_finance_scope(): void
    {
        $this->postJournal('2026-05-01', 'This organization', $this->cash, $this->equity, '60.0000');

        $otherOrganization = Organization::factory()->create();
        $otherScope = app(FinanceScopeService::class)->organizationScope($otherOrganization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($otherOrganization, $otherScope);
        $otherPeriod = app(FinancePeriodService::class)->create($otherOrganization, $otherScope, [
            'name' => 'Financial Year 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $otherCash = FinanceAccount::query()->where('finance_scope_id', $otherScope->id)->where('code', '1000')->firstOrFail();
        $otherEquity = FinanceAccount::query()->where('finance_scope_id', $otherScope->id)->where('code', '3000')->firstOrFail();

        app(FinanceJournalService::class)->post($otherOrganization, $otherScope, $otherPeriod, [
            'transaction_date' => '2026-05-01',
            'description' => 'Other organization',
            'lines' => [
                ['finance_account_id' => $otherCash->id, 'debit' => '700.0000'],
                ['finance_account_id' => $otherEquity->id, 'credit' => '700.0000'],
            ],
        ]);

        $report = $this->report('2026-12-31');

        $this->assertSame('60.0000', $report['total_assets']);
        $this->assertSame('60.0000', $report['total_equity']);
        $this->assertNull(collect($report['asset_accounts'])->firstWhere('account_id', $otherCash->id));
    }

    public function test_balance_sheet_rejects_a_unit_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected unit does not belong to the organization.');

        $this->report('2026-01-01', $otherUnit->id);
    }

    public function test_balance_sheet_rejects_a_finance_scope_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherScope = app(FinanceScopeService::class)->organizationScope($otherOrganization);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The finance scope does not belong to the organization.');

        app(FinanceBalanceSheetService::class)->generate(
            $this->organization,
            $otherScope,
            '2026-12-31',
        );
    }
}
