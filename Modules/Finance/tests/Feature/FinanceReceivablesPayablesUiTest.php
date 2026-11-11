<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Finance\Livewire\FinanceReceivablesPayablesManagement;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceCustomerService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceReceivablesPayablesService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceReceivablesPayablesUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivables_register_is_paginated_and_searchable(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Core');
        app(OrganizationModuleService::class)->enable($organization, 'Member');
        app(OrganizationModuleService::class)->enable($organization, 'Finance');

        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Finance Receivables UI Role',
            'code' => 'finance_receivables_ui_' . Str::lower(Str::random(8)),
        ]);
        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Sales Invoices',
            'code' => 'finance.sales_invoice.view',
        ]);
        $role->permissions()->attach($permission->id);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $customerService = app(FinanceCustomerService::class);
        $invoiceService = app(FinanceReceivablesPayablesService::class);
        for ($index = 1; $index <= 11; $index++) {
            $customer = $customerService->createExternalCustomer($organization, [
                'name' => sprintf('Invoice Search Customer %02d', $index),
            ]);
            $invoiceService->postSalesInvoice($organization, [
                'finance_customer_id' => $customer->id,
                'invoice_date' => '2026-04-10',
                'due_date' => '2026-05-10',
                'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
            ]);
        }

        app(CurrentOrganization::class)->set($organization);
        Livewire::actingAs($user)
            ->test(FinanceReceivablesPayablesManagement::class)
            ->assertSee('Customer invoices')
            ->assertSee('Next')
            ->assertDontSee('Invoice Search Customer 11')
            ->set('invoiceSearch', 'Invoice Search Customer 11')
            ->assertSee('Invoice Search Customer 11')
            ->assertDontSee('Invoice Search Customer 01');
    }
}
