<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceScopeType;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceScope;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceJournalScopeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_rejects_a_period_from_another_organization_level_scope(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);

        // Simulate a duplicate/legacy organization-level scope for the same organization.
        $otherScope = FinanceScope::create([
            'organization_id' => $organization->id,
            'scope_type' => FinanceScopeType::ORGANIZATION,
            'scope_id' => $organization->id + 1000,
            'name' => 'Legacy organization ledger',
            'code' => 'LEGACY-ORG',
            'is_active' => true,
            'metadata' => [],
        ]);
        $period = app(FinancePeriodService::class)->create($organization, $otherScope, [
            'name' => 'Legacy FY 2026',
            'code' => 'LEGACY-FY2026',
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The financial period must be an organization-level period for the same organization and finance scope.'
        );

        app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-08',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '100.00'],
                ['finance_account_id' => $income->id, 'credit' => '100.00'],
            ],
        ]);
    }
}
