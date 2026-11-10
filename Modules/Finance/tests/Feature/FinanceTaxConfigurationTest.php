<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTaxGroup;
use Modules\Finance\Models\FinanceTaxRate;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceTaxConfigurationService;
use Tests\TestCase;

class FinanceTaxConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(Organization $organization): array
    {
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);

        return [
            FinanceAccount::query()->where('organization_id', $organization->id)->where('code', '1000')->firstOrFail(),
            FinanceAccount::query()->where('organization_id', $organization->id)->where('code', '2000')->firstOrFail(),
        ];
    }

    public function test_tax_rate_can_be_created_with_organization_wide_sales_and_purchase_accounts(): void
    {
        $organization = Organization::factory()->create();
        [$asset, $liability] = $this->accounts($organization);

        $rate = app(FinanceTaxConfigurationService::class)->createRate($organization, [
            'code' => 'GST18',
            'name' => 'GST 18%',
            'rate' => 18,
            'applies_to' => 'both',
            'sales_account_id' => $liability->id,
            'purchase_account_id' => $asset->id,
        ]);

        $this->assertSame($organization->id, $rate->organization_id);
        $this->assertSame('18.0000', $rate->rate);
        $this->assertSame($liability->id, $rate->sales_account_id);
        $this->assertSame($asset->id, $rate->purchase_account_id);
    }

    public function test_sales_tax_must_map_to_an_organization_wide_liability_account(): void
    {
        $organization = Organization::factory()->create();
        [$asset] = $this->accounts($organization);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('active liability account');

        app(FinanceTaxConfigurationService::class)->createRate($organization, [
            'code' => 'GST5',
            'name' => 'GST 5%',
            'rate' => 5,
            'applies_to' => 'sales',
            'sales_account_id' => $asset->id,
        ]);
    }

    public function test_tax_group_can_combine_rates_and_preserves_component_order(): void
    {
        $organization = Organization::factory()->create();
        [, $liability] = $this->accounts($organization);
        $service = app(FinanceTaxConfigurationService::class);

        $cgst = $service->createRate($organization, [
            'code' => 'CGST9', 'name' => 'CGST 9%', 'rate' => 9, 'applies_to' => 'sales',
            'sales_account_id' => $liability->id,
        ]);
        $sgst = $service->createRate($organization, [
            'code' => 'SGST9', 'name' => 'SGST 9%', 'rate' => 9, 'applies_to' => 'sales',
            'sales_account_id' => $liability->id,
        ]);

        $group = $service->createGroup($organization, [
            'code' => 'GST18_SPLIT', 'name' => 'GST 18% Split', 'applies_to' => 'sales',
            'rate_ids' => [$cgst->id, $sgst->id],
        ]);

        $this->assertSame([$cgst->id, $sgst->id], $group->rates->pluck('id')->all());
        $this->assertDatabaseHas('finance_tax_group_rates', [
            'finance_tax_group_id' => $group->id,
            'finance_tax_rate_id' => $sgst->id,
            'position' => 1,
        ]);
    }

    public function test_tax_group_cannot_reference_another_organizations_rate(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        [, $liability] = $this->accounts($otherOrganization);
        $rate = app(FinanceTaxConfigurationService::class)->createRate($otherOrganization, [
            'code' => 'GST12', 'name' => 'GST 12%', 'rate' => 12, 'applies_to' => 'sales',
            'sales_account_id' => $liability->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('active and belong to this organization');

        app(FinanceTaxConfigurationService::class)->createGroup($organization, [
            'code' => 'OTHER', 'name' => 'Other', 'applies_to' => 'sales', 'rate_ids' => [$rate->id],
        ]);
    }

    public function test_tax_rate_used_by_active_group_cannot_be_deactivated(): void
    {
        $organization = Organization::factory()->create();
        [, $liability] = $this->accounts($organization);
        $service = app(FinanceTaxConfigurationService::class);
        $rate = $service->createRate($organization, [
            'code' => 'CGST6', 'name' => 'CGST 6%', 'rate' => 6, 'applies_to' => 'sales',
            'sales_account_id' => $liability->id,
        ]);
        $service->createGroup($organization, [
            'code' => 'GST12', 'name' => 'GST 12%', 'applies_to' => 'sales', 'rate_ids' => [$rate->id],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Remove this rate from active tax groups');

        $service->deactivateRate($organization, $rate);
    }
}
