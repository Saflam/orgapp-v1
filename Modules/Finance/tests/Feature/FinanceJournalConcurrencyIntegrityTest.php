<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceAccountType;
use Modules\Finance\Enums\FinanceNormalBalance;
use Modules\Finance\Enums\FinancePeriodStatus;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceChartOfAccountsService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceJournalConcurrencyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function journalFixture(): array
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

        $cash = FinanceAccount::query()
            ->where('finance_scope_id', $scope->id)
            ->where('code', '1000')
            ->firstOrFail();
        $income = FinanceAccount::query()
            ->where('finance_scope_id', $scope->id)
            ->where('code', '4000')
            ->firstOrFail();

        return compact('organization', 'scope', 'period', 'cash', 'income');
    }

    private function journalData(FinanceAccount $cash, FinanceAccount $income): array
    {
        return [
            'transaction_date' => '2026-10-08',
            'description' => 'Concurrency integrity test',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '1000.00'],
                ['finance_account_id' => $income->id, 'credit' => '1000.00'],
            ],
        ];
    }

    public function test_journal_posting_reloads_period_status_instead_of_trusting_stale_model(): void
    {
        $fixture = $this->journalFixture();
        $stalePeriod = clone $fixture['period'];

        FinancePeriod::query()->whereKey($fixture['period']->id)->update([
            'status' => FinancePeriodStatus::CLOSED->value,
            'closed_at' => now(),
        ]);

        try {
            app(FinanceJournalService::class)->post(
                $fixture['organization'],
                $fixture['scope'],
                $stalePeriod,
                $this->journalData($fixture['cash'], $fixture['income']),
            );
            $this->fail('Posting must reject a period that has been closed in the database.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('The financial period is closed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('finance_journals', 0);
    }

    public function test_journal_posting_reloads_account_active_state_instead_of_trusting_stale_model(): void
    {
        $fixture = $this->journalFixture();
        $staleCash = clone $fixture['cash'];

        FinanceAccount::query()->whereKey($fixture['cash']->id)->update(['is_active' => false]);

        $data = $this->journalData($staleCash, $fixture['income']);

        try {
            app(FinanceJournalService::class)->post(
                $fixture['organization'],
                $fixture['scope'],
                $fixture['period'],
                $data,
            );
            $this->fail('Posting must reject an account that has become inactive.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('The journal account is inactive.', $exception->getMessage());
        }

        $this->assertDatabaseCount('finance_journals', 0);
    }

    public function test_account_referenced_by_journal_cannot_be_deactivated(): void
    {
        $fixture = $this->journalFixture();
        $manualAccount = app(FinanceChartOfAccountsService::class)->createManual(
            $fixture['organization'],
            $fixture['scope'],
            [
                'code' => '1099',
                'name' => 'Manual Clearing Account',
                'account_type' => FinanceAccountType::ASSET,
            ],
        );

        app(FinanceJournalService::class)->post(
            $fixture['organization'],
            $fixture['scope'],
            $fixture['period'],
            $this->journalData($manualAccount, $fixture['income']),
        );

        try {
            app(FinanceChartOfAccountsService::class)->deactivate(
                $fixture['organization'],
                $fixture['scope'],
                $manualAccount,
            );
            $this->fail('An account referenced by a journal must not be deactivated.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('An account used by a journal cannot be deactivated.', $exception->getMessage());
        }

        $this->assertTrue($manualAccount->fresh()->is_active);
    }

    public function test_period_close_uses_current_database_state_and_is_idempotent(): void
    {
        $fixture = $this->journalFixture();
        $stalePeriod = clone $fixture['period'];

        $closed = app(FinancePeriodService::class)->close($stalePeriod);
        $closedAgain = app(FinancePeriodService::class)->close($stalePeriod);

        $this->assertSame(FinancePeriodStatus::CLOSED, $closed->status);
        $this->assertSame(FinancePeriodStatus::CLOSED, $closedAgain->status);
        $this->assertNotNull($closedAgain->closed_at);
    }
}
