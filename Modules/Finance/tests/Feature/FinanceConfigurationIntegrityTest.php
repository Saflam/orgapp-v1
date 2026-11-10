<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Services\FinanceChartOfAccountsService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceTaxConfigurationService;
use Tests\TestCase;

class FinanceConfigurationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_update_cannot_create_an_overlap(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $periods = app(FinancePeriodService::class);

        $periods->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $second = $periods->create($organization, $scope, [
            'name' => 'FY 2027',
            'code' => 'FY2027',
            'starts_on' => '2027-01-01 00:00:00',
            'ends_on' => '2027-12-31 00:00:00',
        ]);

        try {
            $periods->update($organization, $scope, $second, [
                'name' => 'Overlapping FY 2027',
                'code' => 'FY2027',
                'starts_on' => '2026-12-01',
                'ends_on' => '2027-12-31',
            ]);
            $this->fail('An updated financial period must not overlap an existing period.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('The financial period overlaps an existing period.', $exception->getMessage());
        }

        $this->assertDatabaseHas('finance_periods', [
            'id' => $second->id,
            'starts_on' => '2027-01-01 00:00:00',
            'ends_on' => '2027-12-31 00:00:00',
        ]);
    }

    public function test_tax_rate_cannot_be_remapped_to_an_inactive_account(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(FinanceChartOfAccountsService::class);
        $firstAccount = $chart->createManual($organization, $scope, [
            'code' => '2300',
            'name' => 'First Output Tax Payable',
            'account_type' => 'liability',
        ]);
        $inactiveAccount = $chart->createManual($organization, $scope, [
            'code' => '2310',
            'name' => 'Second Output Tax Payable',
            'account_type' => 'liability',
        ]);

        $tax = app(FinanceTaxConfigurationService::class);
        $rate = $tax->createRate($organization, [
            'code' => 'GST18',
            'name' => 'GST 18%',
            'rate' => 18,
            'applies_to' => 'sales',
            'sales_account_id' => $firstAccount->id,
        ]);
        $chart->deactivate($organization, $scope, $inactiveAccount);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The sales tax account must be an active liability account in this organization's chart.");

        try {
            $tax->updateRate($organization, $rate, [
                'code' => 'GST18',
                'name' => 'GST 18%',
                'rate' => 18,
                'applies_to' => 'sales',
                'sales_account_id' => $inactiveAccount->id,
            ]);
        } finally {
            $this->assertSame($firstAccount->id, $rate->fresh()->sales_account_id);
        }
    }

    public function test_tax_group_cannot_be_created_from_a_rate_that_has_been_deactivated(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $liability = app(FinanceChartOfAccountsService::class)->createManual($organization, $scope, [
            'code' => '2320',
            'name' => 'Output Tax Payable',
            'account_type' => 'liability',
        ]);
        $tax = app(FinanceTaxConfigurationService::class);
        $rate = $tax->createRate($organization, [
            'code' => 'GST5',
            'name' => 'GST 5%',
            'rate' => 5,
            'applies_to' => 'sales',
            'sales_account_id' => $liability->id,
        ]);
        $tax->deactivateRate($organization, $rate);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Every selected tax rate must be active and belong to this organization.');

        $tax->createGroup($organization, [
            'code' => 'GST5_GROUP',
            'name' => 'GST 5% Group',
            'applies_to' => 'sales',
            'rate_ids' => [$rate->id],
        ]);
    }

    public function test_inactive_parent_account_cannot_receive_a_new_child(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        $chart = app(FinanceChartOfAccountsService::class);
        $parent = $chart->createManual($organization, $scope, [
            'code' => '5200',
            'name' => 'General Expenses',
            'account_type' => 'expense',
        ]);

        $chart->deactivate($organization, $scope, $parent);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An inactive account cannot be used as a parent.');

        $chart->createManual($organization, $scope, [
            'code' => '5210',
            'name' => 'Travel',
            'account_type' => 'expense',
            'parent_id' => $parent->id,
        ]);
    }
}
