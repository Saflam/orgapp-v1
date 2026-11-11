<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeType;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceTrialBalanceService;
use Tests\TestCase;

class FinanceTrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private $period;
    private FinanceAccount $cash;
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

        $this->cash = FinanceAccount::query()
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', '1000')
            ->firstOrFail();

        $this->income = FinanceAccount::query()
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', '4000')
            ->firstOrFail();

        $this->expense = FinanceAccount::query()
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', '5000')
            ->firstOrFail();

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

    /**
     * The report contract is an array containing an "accounts" collection and
     * debit_total / credit_total decimal strings. Each account row exposes
     * account_id, code, name, account_type, debit, and credit.
     */
    private function report(string $asOfDate, ?int $unitId = null): array
    {
        return app(FinanceTrialBalanceService::class)->generate(
            $this->organization,
            $this->scope,
            $asOfDate,
            $unitId,
        );
    }

    public function test_trial_balance_is_cumulative_through_the_as_of_date_and_splits_net_balances(): void
    {
        $this->postJournal('2026-02-10', 'Opening cash', $this->cash, $this->income, '100.2500');
        $this->postJournal('2026-06-15', 'Operating expense', $this->expense, $this->cash, '25.1250');
        $this->postJournal('2026-11-01', 'Later income', $this->cash, $this->income, '50.0000');

        $report = $this->report('2026-10-31');
        $rows = collect($report['accounts']);

        $cash = $rows->firstWhere('account_id', $this->cash->id);
        $income = $rows->firstWhere('account_id', $this->income->id);
        $expense = $rows->firstWhere('account_id', $this->expense->id);

        $this->assertSame('75.1250', $cash['debit']);
        $this->assertSame('100.2500', $income['credit']);
        $this->assertSame('25.1250', $expense['debit']);
        $this->assertSame('100.2500', $report['debit_total']);
        $this->assertSame('100.2500', $report['credit_total']);
        $this->assertSame('0.0000', $report['difference']);
    }

    public function test_reversal_journal_offsets_the_original_in_the_cumulative_trial_balance(): void
    {
        $original = $this->postJournal(
            '2026-03-10',
            'Original receipt',
            $this->cash,
            $this->income,
            '125.5000',
        );

        app(FinanceJournalService::class)->reverse(
            $this->organization,
            $this->scope,
            $original,
            $this->period,
            '2026-04-01',
            'Reverse the incorrect receipt.',
        );

        $report = $this->report('2026-12-31');
        $rows = collect($report['accounts']);

        $this->assertSame('0.0000', $rows->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('0.0000', $rows->firstWhere('account_id', $this->cash->id)['credit']);
        $this->assertSame('0.0000', $rows->firstWhere('account_id', $this->income->id)['debit']);
        $this->assertSame('0.0000', $rows->firstWhere('account_id', $this->income->id)['credit']);
        $this->assertSame('0.0000', $report['debit_total']);
        $this->assertSame('0.0000', $report['credit_total']);
    }

    public function test_draft_journals_are_excluded_from_the_trial_balance(): void
    {
        $this->postJournal('2026-05-01', 'Posted transaction', $this->cash, $this->income, '80.0000');

        $draft = FinanceJournal::create([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'finance_period_id' => $this->period->id,
            'journal_number' => 'JRN-DRAFT-TRIAL-BALANCE',
            'transaction_date' => '2026-06-01',
            'description' => 'Draft transaction',
            'status' => FinanceJournalStatus::DRAFT,
            'metadata' => [],
        ]);

        $draft->lines()->createMany([
            ['finance_account_id' => $this->cash->id, 'debit' => '900.0000', 'credit' => '0.0000', 'metadata' => []],
            ['finance_account_id' => $this->income->id, 'debit' => '0.0000', 'credit' => '900.0000', 'metadata' => []],
        ]);

        $report = $this->report('2026-12-31');
        $rows = collect($report['accounts']);

        $this->assertSame('80.0000', $rows->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('80.0000', $rows->firstWhere('account_id', $this->income->id)['credit']);
    }

    public function test_inactive_accounts_with_posted_history_remain_visible_but_unused_inactive_accounts_do_not(): void
    {
        $usedInactive = FinanceAccount::create([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => '5900',
            'name' => 'Archived Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'is_system_managed' => false,
            'is_active' => true,
            'metadata' => [],
        ]);

        $unusedInactive = FinanceAccount::create([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => '5910',
            'name' => 'Unused Archived Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'is_system_managed' => false,
            'is_active' => true,
            'metadata' => [],
        ]);

        $this->postJournal('2026-05-01', 'Historical archived expense', $usedInactive, $this->cash, '40.0000');

        $usedInactive->update(['is_active' => false]);
        $unusedInactive->update(['is_active' => false]);

        $rows = collect($this->report('2026-12-31')['accounts']);

        $this->assertNotNull($rows->firstWhere('account_id', $usedInactive->id));
        $this->assertSame('40.0000', $rows->firstWhere('account_id', $usedInactive->id)['debit']);
        $this->assertNull($rows->firstWhere('account_id', $unusedInactive->id));
    }

    public function test_trial_balance_isolated_to_the_requested_organization_and_finance_scope(): void
    {
        $this->postJournal('2026-05-01', 'This organization', $this->cash, $this->income, '60.0000');

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
        $otherIncome = FinanceAccount::query()->where('finance_scope_id', $otherScope->id)->where('code', '4000')->firstOrFail();

        app(FinanceJournalService::class)->post($otherOrganization, $otherScope, $otherPeriod, [
            'transaction_date' => '2026-05-01',
            'description' => 'Other organization',
            'lines' => [
                ['finance_account_id' => $otherCash->id, 'debit' => '700.0000'],
                ['finance_account_id' => $otherIncome->id, 'credit' => '700.0000'],
            ],
        ]);

        $rows = collect($this->report('2026-12-31')['accounts']);

        $this->assertSame('60.0000', $rows->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertNull($rows->firstWhere('account_id', $otherCash->id));
    }

    public function test_unit_filter_includes_only_that_unit_and_all_units_includes_organization_wide_entries(): void
    {
        $unitA = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $this->organization->id]);

        $this->postJournal('2026-05-01', 'Organization-wide entry', $this->cash, $this->income, '10.0000');
        $this->postJournal('2026-05-02', 'Unit A entry', $this->cash, $this->income, '20.0000', $unitA->id);
        $this->postJournal('2026-05-03', 'Unit B entry', $this->cash, $this->income, '30.0000', $unitB->id);

        $unitReport = $this->report('2026-12-31', $unitA->id);
        $unitRows = collect($unitReport['accounts']);
        $allUnitsReport = $this->report('2026-12-31');

        $this->assertSame('20.0000', $unitRows->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('20.0000', $unitRows->firstWhere('account_id', $this->income->id)['credit']);
        $this->assertSame('60.0000', collect($allUnitsReport['accounts'])->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('60.0000', collect($allUnitsReport['accounts'])->firstWhere('account_id', $this->income->id)['credit']);
    }

    public function test_as_of_date_excludes_journals_posted_after_that_date(): void
    {
        $this->postJournal('2026-07-01', 'Before cutoff', $this->cash, $this->income, '35.0000');
        $this->postJournal('2026-07-02', 'After cutoff', $this->cash, $this->income, '45.0000');

        $rows = collect($this->report('2026-07-01')['accounts']);

        $this->assertSame('35.0000', $rows->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('35.0000', $rows->firstWhere('account_id', $this->income->id)['credit']);
    }


    public function test_cost_center_filter_uses_journal_line_attribution_for_units_and_committees(): void
    {
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $committeeType = CommitteeType::create([
            'organization_id' => $this->organization->id,
            'name' => 'Event Committee',
            'code' => 'EVENT',
            'is_active' => true,
        ]);
        $committee = Committee::create([
            'organization_id' => $this->organization->id,
            'committee_type_id' => $committeeType->id,
            'name' => 'Annual Event Committee',
            'kind' => 'recurring',
            'status' => 'active',
        ]);

        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => '2026-05-10',
            'description' => 'One journal attributed to two cost centers',
            'lines' => [
                [
                    'finance_account_id' => $this->cash->id,
                    'cost_center_type' => 'unit',
                    'cost_center_id' => $unit->id,
                    'debit' => '40.0000',
                ],
                [
                    'finance_account_id' => $this->cash->id,
                    'cost_center_type' => 'committee',
                    'cost_center_id' => $committee->id,
                    'debit' => '60.0000',
                ],
                [
                    'finance_account_id' => $this->income->id,
                    'cost_center_type' => 'unit',
                    'cost_center_id' => $unit->id,
                    'credit' => '40.0000',
                ],
                [
                    'finance_account_id' => $this->income->id,
                    'cost_center_type' => 'committee',
                    'cost_center_id' => $committee->id,
                    'credit' => '60.0000',
                ],
            ],
        ]);

        $unitReport = app(FinanceTrialBalanceService::class)->generate(
            $this->organization,
            $this->scope,
            '2026-12-31',
            null,
            'unit',
            $unit->id,
        );
        $committeeReport = app(FinanceTrialBalanceService::class)->generate(
            $this->organization,
            $this->scope,
            '2026-12-31',
            null,
            'committee',
            $committee->id,
        );
        $allScopesReport = $this->report('2026-12-31');

        $this->assertSame('40.0000', collect($unitReport['accounts'])->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('40.0000', collect($unitReport['accounts'])->firstWhere('account_id', $this->income->id)['credit']);
        $this->assertSame('60.0000', collect($committeeReport['accounts'])->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('60.0000', collect($committeeReport['accounts'])->firstWhere('account_id', $this->income->id)['credit']);
        $this->assertSame('100.0000', collect($allScopesReport['accounts'])->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('100.0000', collect($allScopesReport['accounts'])->firstWhere('account_id', $this->income->id)['credit']);
    }

    public function test_central_cost_center_filter_includes_legacy_organization_wide_lines_only(): void
    {
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);

        $this->postJournal('2026-05-01', 'Legacy central entry', $this->cash, $this->income, '15.0000');
        $this->postJournal('2026-05-02', 'Legacy unit entry', $this->cash, $this->income, '25.0000', $unit->id);

        $centralReport = app(FinanceTrialBalanceService::class)->generate(
            $this->organization,
            $this->scope,
            '2026-12-31',
            null,
            'organization',
            $this->organization->id,
        );

        $this->assertSame('15.0000', collect($centralReport['accounts'])->firstWhere('account_id', $this->cash->id)['debit']);
        $this->assertSame('15.0000', collect($centralReport['accounts'])->firstWhere('account_id', $this->income->id)['credit']);
    }

}
