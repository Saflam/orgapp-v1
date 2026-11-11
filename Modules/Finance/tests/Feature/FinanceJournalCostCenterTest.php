<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceCostCenter;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceCostCenterService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceJournalCostCenterTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026', 'code' => 'FY2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();
        $central = app(FinanceCostCenterService::class)->central($organization);

        return compact('organization', 'scope', 'period', 'cash', 'income', 'central');
    }

    public function test_journal_lines_can_be_attributed_to_a_cost_center(): void
    {
        $f = $this->fixture();
        $journal = app(FinanceJournalService::class)->post($f['organization'], $f['scope'], $f['period'], [
            'transaction_date' => '2026-11-01',
            'lines' => [
                ['finance_account_id' => $f['cash']->id, 'finance_cost_center_id' => $f['central']->id, 'debit' => '100'],
                ['finance_account_id' => $f['income']->id, 'finance_cost_center_id' => $f['central']->id, 'credit' => '100'],
            ],
        ]);

        $this->assertSame($f['central']->id, $journal->lines[0]->finance_cost_center_id);
        $this->assertSame($f['central']->id, $journal->lines[1]->finance_cost_center_id);
    }

    public function test_journal_rejects_cost_center_from_another_organization(): void
    {
        $f = $this->fixture();
        $other = Organization::factory()->create();
        $foreignCenter = app(FinanceCostCenterService::class)->central($other);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The journal cost center is inactive or does not belong to the organization.');

        app(FinanceJournalService::class)->post($f['organization'], $f['scope'], $f['period'], [
            'transaction_date' => '2026-11-01',
            'lines' => [
                ['finance_account_id' => $f['cash']->id, 'finance_cost_center_id' => $foreignCenter->id, 'debit' => '100'],
                ['finance_account_id' => $f['income']->id, 'credit' => '100'],
            ],
        ]);
    }

    public function test_reversal_preserves_cost_center_attribution(): void
    {
        $f = $this->fixture();
        $service = app(FinanceJournalService::class);
        $original = $service->post($f['organization'], $f['scope'], $f['period'], [
            'transaction_date' => '2026-11-01',
            'lines' => [
                ['finance_account_id' => $f['cash']->id, 'finance_cost_center_id' => $f['central']->id, 'debit' => '100'],
                ['finance_account_id' => $f['income']->id, 'finance_cost_center_id' => $f['central']->id, 'credit' => '100'],
            ],
        ]);

        $reversal = $service->reverse($f['organization'], $f['scope'], $original, $f['period'], '2026-11-02', 'Correction');
        $this->assertSame($f['central']->id, $reversal->lines[0]->finance_cost_center_id);
        $this->assertSame($f['central']->id, $reversal->lines[1]->finance_cost_center_id);
    }
}
