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
use Modules\Finance\Livewire\FinanceChartOfAccountsManagement;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceCashFlowAccountConfigurationUiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $this->organization = Organization::factory()->create();

        $modules = app(OrganizationModuleService::class);
        $modules->enable($this->organization, 'Core');
        $modules->enable($this->organization, 'Member');
        $modules->enable($this->organization, 'Finance');

        $this->scope = app(FinanceScopeService::class)->organizationScope($this->organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($this->organization, $this->scope);
    }

    public function test_authorized_user_can_create_cash_account_from_chart_of_accounts_screen(): void
    {
        $user = $this->authorizeUser(['finance.account.view', 'finance.account.manage']);

        $this->accountScreen($user)
            ->assertSee('Cash / bank account')
            ->assertSee('Cash flow category')
            ->set('code', '1010')
            ->set('name', 'Operating Bank')
            ->set('accountType', 'asset')
            ->set('isCashAccount', true)
            ->set('cashFlowCategory', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Operating Bank')
            ->assertSee('Cash / Bank');

        $this->assertDatabaseHas('finance_accounts', [
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => '1010',
            'is_cash_account' => true,
            'cash_flow_category' => null,
        ]);
    }

    public function test_authorized_user_can_create_non_cash_account_with_cash_flow_category(): void
    {
        $user = $this->authorizeUser(['finance.account.view', 'finance.account.manage']);

        $this->accountScreen($user)
            ->set('code', '1510')
            ->set('name', 'Equipment')
            ->set('accountType', 'asset')
            ->set('isCashAccount', false)
            ->set('cashFlowCategory', 'investing')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Equipment')
            ->assertSee('Investing');

        $this->assertDatabaseHas('finance_accounts', [
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => '1510',
            'is_cash_account' => false,
            'cash_flow_category' => 'investing',
        ]);
    }

    public function test_edit_form_loads_existing_cash_flow_configuration(): void
    {
        $user = $this->authorizeUser(['finance.account.view', 'finance.account.manage']);
        $account = $this->createAccount([
            'code' => '1520',
            'name' => 'Vehicles',
            'account_type' => 'asset',
            'cash_flow_category' => 'investing',
        ]);

        $this->accountScreen($user)
            ->call('edit', $account->id)
            ->assertSet('isCashAccount', false)
            ->assertSet('cashFlowCategory', 'investing')
            ->assertSee('Edit Account');
    }

    public function test_user_can_update_cash_flow_configuration_for_existing_account(): void
    {
        $user = $this->authorizeUser(['finance.account.view', 'finance.account.manage']);
        $account = $this->createAccount([
            'code' => '1530',
            'name' => 'Reserve Bank',
            'account_type' => 'asset',
        ]);

        $this->accountScreen($user)
            ->call('edit', $account->id)
            ->set('isCashAccount', true)
            ->set('cashFlowCategory', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Cash / Bank');

        $this->assertDatabaseHas('finance_accounts', [
            'id' => $account->id,
            'is_cash_account' => true,
            'cash_flow_category' => null,
        ]);
    }

    public function test_cash_flow_settings_are_displayed_in_account_list(): void
    {
        $user = $this->authorizeUser(['finance.account.view', 'finance.account.manage']);
        $this->createAccount([
            'code' => '1540',
            'name' => 'Petty Cash',
            'account_type' => 'asset',
            'is_cash_account' => true,
        ]);
        $this->createAccount([
            'code' => '1550',
            'name' => 'Office Equipment',
            'account_type' => 'asset',
            'cash_flow_category' => 'investing',
        ]);
        $this->createAccount([
            'code' => '2510',
            'name' => 'Long-term Loan',
            'account_type' => 'liability',
            'cash_flow_category' => 'financing',
        ]);

        $this->accountScreen($user)
            ->assertSee('Cash / Bank')
            ->assertSee('Investing')
            ->assertSee('Financing');
    }

    public function test_user_without_account_manage_permission_cannot_edit_or_save_settings(): void
    {
        $user = $this->authorizeUser(['finance.account.view']);
        $account = $this->createAccount([
            'code' => '1560',
            'name' => 'Tools',
            'account_type' => 'asset',
        ]);

        $component = $this->accountScreen($user)
            ->assertDontSee('Cash / bank account')
            ->assertDontSee('Cash flow category');

        $component->call('edit', $account->id)->assertForbidden();
    }

    public function test_chart_of_accounts_route_is_available_to_authorized_user(): void
    {
        $user = $this->authorizeUser(['finance.account.view']);

        $this->setCurrentOrganization();

        $this->actingAs($user)
            ->get('http://' . $this->organization->slug . '.lvh.me:8000' . route('finance.accounts', [], false))
            ->assertOk()
            ->assertSee('Chart of Accounts')
            ->assertSee('Cash flow');
    }

    private function createAccount(array $attributes): FinanceAccount
    {
        return FinanceAccount::create(array_merge([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => '9990',
            'name' => 'Test Account',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_system_managed' => false,
            'source_type' => null,
            'source_id' => null,
            'is_active' => true,
            'is_cash_account' => false,
            'cash_flow_category' => null,
            'metadata' => [],
        ], $attributes));
    }

    private function accountScreen(User $user)
    {
        $this->setCurrentOrganization();

        return Livewire::actingAs($user)->test(FinanceChartOfAccountsManagement::class);
    }

    private function setCurrentOrganization(): void
    {
        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($this->organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);
    }

    private function authorizeUser(array $permissionCodes): User
    {
        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $role = Role::create([
            'organization_id' => $this->organization->id,
            'name' => 'Cash Flow Account UI Test Role',
            'code' => 'finance_cf_ui_' . Str::lower(Str::random(8)),
        ]);
        $permissions = collect($permissionCodes)->map(fn (string $code) => Permission::create([
            'module' => 'finance',
            'name' => str($code)->headline(),
            'code' => $code,
        ]));
        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $this->organization);

        return $user;
    }
}
