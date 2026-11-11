<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceCostCenter;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceCostCenterService;
use Modules\Finance\Services\FinanceIncomeStatementService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceIncomeStatementCostCenterTest extends TestCase
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
        app(FinanceAccountProvisioningService::class)->provisionDefaults($this->organization, $this->scope);

        $this->cash = $this->account('1000');
        $this->income = $this->account('4000');
        $this->expense = $this->account('5000');
        $this->period = app(FinancePeriodService::class)->create($this->organization, $this->scope, [
            'name' => 'Financial Year 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    public function test_all_scopes_includes_central_units_and_legacy_unattributed_journals(): void
    {
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $central = app(FinanceCostCenterService::class)->central($this->organization);
        $unitCenter = app(FinanceCostCenterService::class)->resolve($this->organization, 'unit', $unit->id);

        $this->postAttributedJournal($central->id, '10.0000');
        $this->postAttributedJournal($unitCenter->id, '20.0000');
        $this->postLegacyJournal(null, '30.0000');

        $report = $this->report();

        $this->assertSame('60.0000', $report['total_income']);
        $this->assertSame('all', $report['cost_center_type']);
        $this->assertNull($report['cost_center_owner_id']);
    }

    public function test_central_cost_center_includes_explicit_and_legacy_central_lines_only(): void
    {
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $central = app(FinanceCostCenterService::class)->central($this->organization);
        $unitCenter = app(FinanceCostCenterService::class)->resolve($this->organization, 'unit', $unit->id);

        $this->postAttributedJournal($central->id, '10.0000');
        $this->postAttributedJournal($unitCenter->id, '20.0000');
        $this->postLegacyJournal(null, '30.0000');
        $this->postLegacyJournal($unit->id, '40.0000');

        $report = $this->report('organization', $this->organization->id);

        $this->assertSame('40.0000', $report['total_income']);
        $this->assertSame('organization', $report['cost_center_type']);
        $this->assertSame($this->organization->id, $report['cost_center_owner_id']);
    }

    public function test_unit_cost_center_includes_explicit_and_legacy_lines_for_that_unit_only(): void
    {
        $unitA = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $unitB = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $centerA = app(FinanceCostCenterService::class)->resolve($this->organization, 'unit', $unitA->id);
        $centerB = app(FinanceCostCenterService::class)->resolve($this->organization, 'unit', $unitB->id);

        $this->postAttributedJournal($centerA->id, '10.0000');
        $this->postAttributedJournal($centerB->id, '20.0000');
        $this->postLegacyJournal($unitA->id, '30.0000');
        $this->postLegacyJournal($unitB->id, '40.0000');

        $report = $this->report('unit', $unitA->id);

        $this->assertSame('40.0000', $report['total_income']);
        $this->assertSame('unit', $report['cost_center_type']);
        $this->assertSame($unitA->id, $report['cost_center_owner_id']);
    }

    public function test_cost_center_owner_from_another_organization_is_rejected(): void
    {
        $otherOrganization = Organization::factory()->create();
        $foreignUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected cost center owner is invalid or belongs to another organization.');

        $this->report('unit', $foreignUnit->id);
    }

    public function test_invalid_cost_center_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected cost center type is invalid.');

        $this->report('unknown');
    }

    private function account(string $code): FinanceAccount
    {
        return FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function postAttributedJournal(int $costCenterId, string $amount): void
    {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => '2026-05-10',
            'lines' => [
                ['finance_account_id' => $this->cash->id, 'debit' => $amount, 'finance_cost_center_id' => $costCenterId],
                ['finance_account_id' => $this->income->id, 'credit' => $amount, 'finance_cost_center_id' => $costCenterId],
            ],
        ]);
    }

    private function postLegacyJournal(?int $unitId, string $amount): void
    {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => '2026-05-11',
            'unit_id' => $unitId,
            'lines' => [
                ['finance_account_id' => $this->cash->id, 'debit' => $amount],
                ['finance_account_id' => $this->income->id, 'credit' => $amount],
            ],
        ]);
    }

    private function report(string $type = 'all', ?int $ownerId = null): array
    {
        return app(FinanceIncomeStatementService::class)->generate(
            $this->organization,
            $this->scope,
            '2026-01-01',
            '2026-12-31',
            null,
            $type,
            $ownerId,
        );
    }
}
