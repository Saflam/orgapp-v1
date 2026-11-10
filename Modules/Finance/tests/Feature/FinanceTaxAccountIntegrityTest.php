<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Services\FinanceChartOfAccountsService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceTaxConfigurationService;
use Tests\TestCase;

class FinanceTaxAccountIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_mapped_to_an_active_sales_tax_rate_cannot_be_deactivated(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(FinanceChartOfAccountsService::class);
        $liability = $chart->createManual($organization, $scope, [
            'code' => '2300',
            'name' => 'Output Tax Payable',
            'account_type' => 'liability',
        ]);

        app(FinanceTaxConfigurationService::class)->createRate($organization, [
            'code' => 'GST18',
            'name' => 'GST 18%',
            'rate' => 18,
            'applies_to' => 'sales',
            'sales_account_id' => $liability->id,
        ]);

        try {
            $chart->deactivate($organization, $scope, $liability);
            $this->fail('An account mapped to an active sales tax rate must not be deactivated.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'An account mapped to an active tax rate cannot be deactivated.',
                $exception->getMessage(),
            );
        }

        $this->assertTrue($liability->fresh()->is_active);
    }

    public function test_account_mapped_to_an_active_purchase_tax_rate_cannot_be_deactivated(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(FinanceChartOfAccountsService::class);
        $asset = $chart->createManual($organization, $scope, [
            'code' => '1400',
            'name' => 'Input Tax Receivable',
            'account_type' => 'asset',
        ]);

        app(FinanceTaxConfigurationService::class)->createRate($organization, [
            'code' => 'GST-PURCHASE-18',
            'name' => 'Purchase GST 18%',
            'rate' => 18,
            'applies_to' => 'purchases',
            'purchase_account_id' => $asset->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An account mapped to an active tax rate cannot be deactivated.');

        $chart->deactivate($organization, $scope, $asset);
    }

    public function test_account_can_be_deactivated_after_its_tax_rate_is_deactivated(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(FinanceChartOfAccountsService::class);
        $liability = $chart->createManual($organization, $scope, [
            'code' => '2310',
            'name' => 'Inactive Tax Payable',
            'account_type' => 'liability',
        ]);

        $taxService = app(FinanceTaxConfigurationService::class);
        $rate = $taxService->createRate($organization, [
            'code' => 'GST5',
            'name' => 'GST 5%',
            'rate' => 5,
            'applies_to' => 'sales',
            'sales_account_id' => $liability->id,
        ]);
        $taxService->deactivateRate($organization, $rate);

        $chart->deactivate($organization, $scope, $liability);

        $this->assertFalse($liability->fresh()->is_active);
    }
}
