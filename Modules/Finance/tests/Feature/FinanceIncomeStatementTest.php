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
use Modules\Finance\Services\FinanceIncomeStatementService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceIncomeStatementTest extends TestCase
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

        $this->cash = $this->account('1000');
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

    private function postJournal(
        string $date,
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
                'description' => 'Income statement test journal',
                'unit_id' => $unitId,
                'lines' => [
                    ['finance_account_id' => $debitAccount->id, 'debit' => $amount],
                    ['finance_account_id' => $creditAccount->id, 'credit' => $amount],
                ],
            ],
        );
    }

    private function report(
        string $startDate,
        string $endDate,
        ?int $unitId = null,
    ): array {
        return app(FinanceIncomeStatementService::class)->generate(
            $this->organization,
            $this->scope,
            $startDate,
            $endDate,
            $unitId,
        );
    }

    public function test_income_statement_summarizes_income_expenses_and_net_surplus_for_selected_date_range(): void
    {
        $this->postJournal('2026-01-10', $this->cash, $this->income, '100.2500');
        $this->postJournal('2026-02-10', $this->expense, $this->cash, '25.1250');
        $this->postJournal('2026-03-10', $this->cash, $this->income, '50.0000');

        $report = $this->report('2026-02-01', '2026-03-31');

        $incomeRows = collect($report['income_accounts']);
        $expenseRows = collect($report['expense_accounts']);

        $this->assertSame('50.0000', $incomeRows->firstWhere('account_id', $this->income->id)['amount']);
        $this->assertSame('25.1250', $expenseRows->firstWhere('account_id', $this->expense->id)['amount']);
        $this->assertSame('50.0000', $report['total_income']);
        $this->assertSame('25.1250', $report['total_expenses']);
        $this->assertSame('24.8750', $report['net_surplus']);
        $this->assertSame('2026-02-01', $report['start_date']);
        $this->assertSame('2026-03-31', $report['end_date']);
    }

    public function test_income_statement_excludes_journals_outside_the_selected_date_range(): void
    {
        $this->postJournal('2026-01-31', $this->cash, $this->income, '100.0000');
        $this->postJournal('2026-02-01', $this->cash, $this->income, '40.0000');
        $this->postJournal('2026-03-31', $this->expense, $this->cash, '10.0000');
        $this->postJournal('2026-04-01', $this->expense, $this->cash, '90.0000');

        $report = $this->report('2026-02-01', '2026-03-31');

        $this->assertSame('40.0000', $report['total_income']);
        $this->assertSame('10.0000', $report['total_expenses']);
        $this->assertSame('30.0000', $report['net_surplus']);
    }

    public function test_draft_journals_are_excluded_from_income_statement(): void
    {
        $this->postJournal('2026-05-01', $this->cash, $this->income, '80.0000');

        $draft = FinanceJournal::create([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'finance_period_id' => $this->period->id,
            'journal_number' => 'JRN-DRAFT-INCOME-STATEMENT',
            'transaction_date' => '2026-06-01',
            'description' => 'Draft transaction',
            'status' => FinanceJournalStatus::DRAFT,
            'metadata' => [],
        ]);

        $draft->lines()->createMany([
            ['finance_account_id' => $this->cash->id, 'debit' => '0.0000', 'credit' => '0.0000', 'metadata' => []],
            ['finance_account_id' => $this->income->id, 'debit' => '0.0000', 'credit' => '900.0000', 'metadata' => []],
        ]);

        $report = $this->report('2026-01-01', '2026-12-31');

        $this->assertSame('80.0000', $report['total_income']);
    }

    public function test_posted_reversal_is_reflected_in_the_period_in_which_the_reversal_is_posted(): void
    {
        $original = $this->postJournal('2026-03-10', $this->cash, $this->income, '125.5000');

        app(FinanceJournalService::class)->reverse(
            $this->organization,
            $this->scope,
            $original,
            $this->period,
            '2026-04-01',
            'Reverse the incorrect receipt.',
        );

        $march = $this->report('2026-03-01', '2026-03-31');
        $april = $this->report('2026-04-01', '2026-04-30');

        $this->assertSame('125.5000', $march['total_income']);
        $this->assertSame('-125.5000', $april['total_income']);
        $this->assertSame('-125.5000', $april['net_surplus']);
    }

    public function test_selected_unit_includes_only_that_units_activity_and_all_units_includes_unattributed_entries(): void
    {
        $unitA = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $this->organization->id]);

        $this->postJournal('2026-05-01', $this->cash, $this->income, '10.0000');
        $this->postJournal('2026-05-02', $this->cash, $this->income, '20.0000', $unitA->id);
        $this->postJournal('2026-05-03', $this->expense, $this->cash, '30.0000', $unitB->id);

        $unitReport = $this->report('2026-01-01', '2026-12-31', $unitA->id);
        $allUnitsReport = $this->report('2026-01-01', '2026-12-31');

        $this->assertSame('20.0000', $unitReport['total_income']);
        $this->assertSame('0.0000', $unitReport['total_expenses']);
        $this->assertSame('30.0000', $allUnitsReport['total_income']);
        $this->assertSame('30.0000', $allUnitsReport['total_expenses']);
    }

    public function test_income_statement_rejects_a_unit_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected unit does not belong to the organization.');

        $this->report('2026-01-01', '2026-12-31', $otherUnit->id);
    }

    public function test_income_statement_rejects_a_non_organization_finance_scope(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherScope = app(FinanceScopeService::class)->organizationScope($otherOrganization);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The finance scope does not belong to the organization.');

        app(FinanceIncomeStatementService::class)->generate(
            $this->organization,
            $otherScope,
            '2026-01-01',
            '2026-12-31',
        );
    }
}
