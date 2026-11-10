<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceAccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_managed_child_account_preserves_parent_relationship(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);

        $provisioner = app(FinanceAccountProvisioningService::class);

        $parent = $provisioner->ensure($organization, $scope, [
            'code' => '1500',
            'name' => 'Fixed Assets',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $child = $provisioner->ensure($organization, $scope, [
            'code' => '1510',
            'name' => 'Office Equipment',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => $parent->id,
        ]);

        $this->assertSame($parent->id, $child->parent_id);

        $this->assertDatabaseHas('finance_accounts', [
            'id' => $child->id,
            'parent_id' => $parent->id,
        ]);
    }
}
