<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Finance\Livewire\FinancePartyManagement;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Finance\Services\FinanceCustomerService;
use Modules\Finance\Services\FinanceSupplierService;
use Tests\TestCase;

class FinancePartyManagementUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_and_suppliers_have_a_separate_searchable_paginated_page(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

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
            'name' => 'Finance Parties UI Role',
            'code' => 'finance_parties_ui_' . Str::lower(Str::random(8)),
        ]);
        $permissions = collect(['finance.customer.view', 'finance.customer.manage', 'finance.supplier.view', 'finance.supplier.manage'])
            ->map(fn (string $code) => Permission::create([
                'module' => 'finance',
                'name' => str($code)->headline(),
                'code' => $code,
            ]));
        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        $customers = app(FinanceCustomerService::class);
        $suppliers = app(FinanceSupplierService::class);
        for ($index = 1; $index <= 12; $index++) {
            $customers->createExternalCustomer($organization, ['name' => sprintf('Customer %02d', $index), 'email' => sprintf('customer%02d@example.test', $index)]);
            $suppliers->create($organization, ['code' => sprintf('SUP-%02d', $index), 'name' => sprintf('Supplier %02d', $index)]);
        }

        $url = 'http://' . $organization->slug . '.lvh.me:8000' . route('finance.parties', [], false);
        $response = $this->actingAs($user)->get($url);

        $response->assertOk()
            ->assertSee('Customers &amp; Suppliers', false)
            ->assertSee('Search customers')
            ->assertSee('Search suppliers')
            ->assertSee('Customer 01')
            ->assertSee('Supplier 01')
            ->assertDontSee('Customer 12')
            ->assertDontSee('Supplier 12')
            ->assertSee('Next');

        app(CurrentOrganization::class)->set($organization);
        Livewire::actingAs($user)
            ->test(FinancePartyManagement::class)
            ->assertSee('Customer 01')
            ->assertDontSee('Customer 12')
            ->set('customerSearch', 'Customer 12')
            ->assertSee('Customer 12')
            ->assertDontSee('Customer 01')
            ->set('supplierSearch', 'SUP-12')
            ->assertSee('Supplier 12')
            ->assertDontSee('Supplier 01');
    }
}
