<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceChartOfAccountsService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceChartOfAccountsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_update_preserves_existing_parent_account(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(FinanceChartOfAccountsService::class);

        $parent = $chart->createManual($organization, $scope, [
            'code' => '5100',
            'name' => 'Operating Expenses',
            'account_type' => 'expense',
        ]);

        $child = $chart->createManual($organization, $scope, [
            'code' => '5110',
            'name' => 'Office Supplies',
            'account_type' => 'expense',
            'parent_id' => $parent->id,
        ]);

        $updated = $chart->updateManual($organization, $scope, $child, [
            'name' => 'Office and Stationery Supplies',
        ]);

        $this->assertSame($parent->id, $updated->parent_id);
        $this->assertDatabaseHas('finance_accounts', [
            'id' => $child->id,
            'parent_id' => $parent->id,
            'name' => 'Office and Stationery Supplies',
        ]);
    }

    public function test_partial_update_can_explicitly_remove_existing_parent_account(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(FinanceChartOfAccountsService::class);

        $parent = $chart->createManual($organization, $scope, [
            'code' => '5200',
            'name' => 'General Expenses',
            'account_type' => 'expense',
        ]);

        $child = $chart->createManual($organization, $scope, [
            'code' => '5210',
            'name' => 'Travel',
            'account_type' => 'expense',
            'parent_id' => $parent->id,
        ]);

        $updated = $chart->updateManual($organization, $scope, $child, [
            'parent_id' => null,
        ]);

        $this->assertNull($updated->parent_id);
        $this->assertDatabaseHas('finance_accounts', [
            'id' => $child->id,
            'parent_id' => null,
        ]);
    }
}
