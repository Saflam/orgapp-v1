<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeType;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Unit;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Finance\Livewire\FinanceIncomeStatementReport;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceIncomeStatementCostCenterUiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private $period;
    private FinanceAccount $cash;
    private FinanceAccount $income;
    private FinanceAccount $expense;

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

        $this->cash = $this->account('1000');
        $this->income = $this->account('4000');
        $this->expense = $this->account('5000');

        $this->period = app(FinancePeriodService::class)->create($this->organization, $this->scope, [
            'name' => 'Financial Year 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    public function test_report_exposes_all_cost_center_filter_options_and_filters_unit_and_committee_activity(): void
    {
        $user = $this->authorizeUser();
        $unit = Unit::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Income Statement Unit',
        ]);
        $committee = $this->createCommittee('Income Statement Committee');

        $this->postAttributedJournal('2026-04-10', '40.0000', 'unit', $unit->id);
        $this->postAttributedJournal('2026-04-11', '60.0000', 'committee', $committee->id);

        $component = $this->reportScreen($user)
            ->assertSee('All Scopes')
            ->assertSee('Central / Organization')
            ->assertSee('Individual Units')
            ->assertSee('Individual Committees')
            ->set('costCenterType', 'unit')
            ->assertSee('Income Statement Unit')
            ->set('costCenterOwnerId', $unit->id)
            ->assertSee('40.0000')
            ->assertDontSee('60.0000');

        $component
            ->set('costCenterType', 'committee')
            ->assertSee('Income Statement Committee')
            ->set('costCenterOwnerId', $committee->id)
            ->assertSee('60.0000')
            ->assertDontSee('40.0000');
    }

    public function test_report_can_filter_central_organization_cost_center(): void
    {
        $user = $this->authorizeUser();
        $this->postAttributedJournal('2026-04-10', '25.0000', 'organization', $this->organization->id);
        $this->postJournal('2026-04-11', '35.0000');

        $this->reportScreen($user)
            ->set('costCenterType', 'organization')
            ->assertSet('costCenterOwnerId', $this->organization->id)
            ->assertSee('60.0000');
    }

    public function test_all_scopes_includes_central_unit_and_committee_activity(): void
    {
        $user = $this->authorizeUser();
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $committee = $this->createCommittee('All Scopes Committee');

        $this->postJournal('2026-04-10', '10.0000');
        $this->postAttributedJournal('2026-04-11', '20.0000', 'unit', $unit->id);
        $this->postAttributedJournal('2026-04-12', '30.0000', 'committee', $committee->id);

        $this->reportScreen($user)
            ->set('costCenterType', 'all')
            ->assertSee('60.0000');
    }

    public function test_foreign_organization_unit_cannot_be_selected_as_cost_center(): void
    {
        $user = $this->authorizeUser();
        $otherOrganization = Organization::factory()->create();
        $foreignUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->reportScreen($user)
            ->set('costCenterType', 'unit')
            ->set('costCenterOwnerId', $foreignUnit->id)
            ->assertStatus(404);
    }

    public function test_foreign_organization_committee_cannot_be_selected_as_cost_center(): void
    {
        $user = $this->authorizeUser();
        $otherOrganization = Organization::factory()->create();
        $foreignCommittee = $this->createCommittee('Foreign Committee', $otherOrganization);

        $this->reportScreen($user)
            ->set('costCenterType', 'committee')
            ->set('costCenterOwnerId', $foreignCommittee->id)
            ->assertStatus(404);
    }

    private function account(string $code): FinanceAccount
    {
        return FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function postJournal(string $date, string $amount): void
    {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => $date,
            'lines' => [
                ['finance_account_id' => $this->cash->id, 'debit' => $amount],
                ['finance_account_id' => $this->income->id, 'credit' => $amount],
            ],
        ]);
    }

    private function postAttributedJournal(string $date, string $amount, string $type, int $ownerId): void
    {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => $date,
            'lines' => [
                ['finance_account_id' => $this->cash->id, 'debit' => $amount, 'cost_center_type' => $type, 'cost_center_id' => $ownerId],
                ['finance_account_id' => $this->income->id, 'credit' => $amount, 'cost_center_type' => $type, 'cost_center_id' => $ownerId],
            ],
        ]);
    }

    private function createCommittee(string $name, ?Organization $organization = null): Committee
    {
        $organization ??= $this->organization;
        $type = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Standing Committee',
            'code' => 'standing_' . Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        return Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $type->id,
            'name' => $name,
            'kind' => CommitteeKind::RECURRING,
            'status' => 'active',
        ]);
    }

    private function reportScreen(User $user)
    {
        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($this->organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);

        return Livewire::actingAs($user)->test(FinanceIncomeStatementReport::class);
    }

    private function authorizeUser(): User
    {
        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $role = Role::create([
            'organization_id' => $this->organization->id,
            'name' => 'Finance Income Statement Cost Center UI Role',
            'code' => 'finance_is_cc_ui_' . Str::lower(Str::random(8)),
        ]);
        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Finance Reports',
            'code' => 'finance.report.view',
        ]);
        $role->permissions()->attach($permission->id);
        app(RoleAssignmentService::class)->assign($membership, $role, $this->organization);

        return $user;
    }
}
