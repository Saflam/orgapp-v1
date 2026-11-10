<?php

namespace Modules\Finance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Core\Models\Organization;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceChartOfAccountsService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceCashFlowAccountConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private FinanceChartOfAccountsService $chart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->scope = app(FinanceScopeService::class)->organizationScope($this->organization);
        $this->chart = app(FinanceChartOfAccountsService::class);
    }

    public function test_manual_asset_account_can_be_configured_as_a_cash_account(): void
    {
        $account = $this->chart->createManual($this->organization, $this->scope, [
            'code' => '1010',
            'name' => 'Operating Bank',
            'account_type' => 'asset',
            'is_cash_account' => true,
        ]);

        $this->assertTrue((bool) $account->is_cash_account);
        $this->assertNull($account->cash_flow_category);
        $this->assertDatabaseHas('finance_accounts', [
            'id' => $account->id,
            'is_cash_account' => true,
            'cash_flow_category' => null,
        ]);
    }

    public function test_non_asset_account_cannot_be_configured_as_a_cash_account(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->chart->createManual($this->organization, $this->scope, [
            'code' => '4010',
            'name' => 'Membership Income',
            'account_type' => 'income',
            'is_cash_account' => true,
        ]);
    }

    public function test_cash_account_cannot_have_a_cash_flow_category(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->chart->createManual($this->organization, $this->scope, [
            'code' => '1020',
            'name' => 'Reserve Bank',
            'account_type' => 'asset',
            'is_cash_account' => true,
            'cash_flow_category' => 'operating',
        ]);
    }

    public function test_non_cash_account_can_be_assigned_each_supported_cash_flow_category(): void
    {
        foreach (['operating', 'investing', 'financing'] as $index => $category) {
            $account = $this->chart->createManual($this->organization, $this->scope, [
                'code' => '6' . ($index + 1) . '00',
                'name' => ucfirst($category) . ' classification account',
                'account_type' => 'expense',
                'cash_flow_category' => $category,
            ]);

            $this->assertFalse((bool) $account->is_cash_account);
            $this->assertSame($category, $account->cash_flow_category);
        }
    }

    public function test_invalid_cash_flow_category_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->chart->createManual($this->organization, $this->scope, [
            'code' => '6100',
            'name' => 'Unclassified Expense',
            'account_type' => 'expense',
            'cash_flow_category' => 'other',
        ]);
    }

    public function test_manual_account_cash_flow_configuration_can_be_updated(): void
    {
        $account = $this->chart->createManual($this->organization, $this->scope, [
            'code' => '1500',
            'name' => 'Equipment',
            'account_type' => 'asset',
        ]);

        $updated = $this->chart->updateManual($this->organization, $this->scope, $account, [
            'cash_flow_category' => 'investing',
        ]);

        $this->assertSame('investing', $updated->cash_flow_category);
        $this->assertFalse((bool) $updated->is_cash_account);

        $updated = $this->chart->updateManual($this->organization, $this->scope, $updated, [
            'is_cash_account' => true,
            'cash_flow_category' => null,
        ]);

        $this->assertTrue((bool) $updated->is_cash_account);
        $this->assertNull($updated->cash_flow_category);
    }

    public function test_partial_update_preserves_existing_cash_flow_configuration(): void
    {
        $account = $this->chart->createManual($this->organization, $this->scope, [
            'code' => '1510',
            'name' => 'Vehicles',
            'account_type' => 'asset',
            'cash_flow_category' => 'investing',
        ]);

        $updated = $this->chart->updateManual($this->organization, $this->scope, $account, [
            'name' => 'Motor Vehicles',
        ]);

        $this->assertSame('investing', $updated->cash_flow_category);
        $this->assertFalse((bool) $updated->is_cash_account);
    }

    public function test_cash_flow_configuration_update_rejects_cash_account_with_non_asset_type(): void
    {
        $account = $this->chart->createManual($this->organization, $this->scope, [
            'code' => '1520',
            'name' => 'Equipment',
            'account_type' => 'asset',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->chart->updateManual($this->organization, $this->scope, $account, [
            'is_cash_account' => true,
            'cash_flow_category' => 'operating',
        ]);
    }
}
