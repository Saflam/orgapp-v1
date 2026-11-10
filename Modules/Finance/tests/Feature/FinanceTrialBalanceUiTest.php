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
use Modules\Core\Models\Unit;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Finance\Livewire\FinanceTrialBalanceReport;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceTrialBalanceUiTest extends TestCase
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

        $moduleService = app(OrganizationModuleService::class);
        $moduleService->enable($this->organization, 'Core');
        $moduleService->enable($this->organization, 'Member');
        $moduleService->enable($this->organization, 'Finance');

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

    public function test_authorized_user_can_view_trial_balance_report_and_sidebar_link(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-04-10', '100.0000', '100.0000');

        $this->reportScreen($user)
            ->assertSee('Trial Balance')
            ->assertSee('As of date')
            ->assertSee('All Units')
            ->assertSee('1000')
            ->assertSee('Cash')
            ->assertSee('100.0000')
            ->assertSee('Debit total')
            ->assertSee('Credit total');

        $this->actingAs($user)
            ->get($this->organizationUrl())
            ->assertOk()
            ->assertSee('Trial Balance');
    }

    public function test_user_without_report_permission_is_forbidden(): void
    {
        $user = $this->authorizeUser(['finance.journal.view']);

        $this->reportScreen($user)->assertForbidden();

        $this->actingAs($user)
            ->get($this->organizationUrl())
            ->assertForbidden();
    }

    public function test_report_uses_selected_financial_period_end_date(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-04-10', '100.0000', '100.0000');
        $this->postJournal('2026-11-10', '50.0000', '50.0000');

        $this->reportScreen($user)
            ->set('periodId', $this->period->id)
            ->assertSet('asOfDate', '2026-12-31')
            ->assertSee('150.0000');
    }

    public function test_report_can_use_a_direct_as_of_date(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-04-10', '100.0000', '100.0000');
        $this->postJournal('2026-11-10', '50.0000', '50.0000');

        $this->reportScreen($user)
            ->set('asOfDate', '2026-06-30')
            ->assertSee('100.0000')
            ->assertDontSee('150.0000');
    }

    public function test_selected_unit_excludes_organization_wide_and_other_unit_journals(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $unitA = Unit::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Unit A']);
        $unitB = Unit::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Unit B']);

        $this->postJournal('2026-04-10', '10.0000', '10.0000');
        $this->postJournal('2026-04-11', '20.0000', '20.0000', $unitA->id);
        $this->postJournal('2026-04-12', '30.0000', '30.0000', $unitB->id);

        $this->reportScreen($user)
            ->set('unitId', $unitA->id)
            ->assertSee('20.0000')
            ->assertDontSee('60.0000');
    }

    public function test_all_units_includes_organization_wide_and_unit_journals(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);

        $this->postJournal('2026-04-10', '10.0000', '10.0000');
        $this->postJournal('2026-04-11', '20.0000', '20.0000', $unit->id);

        $this->reportScreen($user)
            ->assertSee('30.0000');
    }

    public function test_unbalanced_report_difference_is_visible(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-04-10', '100.0000', '100.0000');

        $this->reportScreen($user)
            ->assertSee('Difference')
            ->assertSee('0.0000');
    }

    private function account(string $code): FinanceAccount
    {
        return FinanceAccount::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function postJournal(string $date, string $amount, string $credit, ?int $unitId = null): void
    {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => $date,
            'unit_id' => $unitId,
            'lines' => [
                ['finance_account_id' => $this->cash->id, 'debit' => $amount],
                ['finance_account_id' => $this->income->id, 'credit' => $credit],
            ],
        ]);
    }

    private function reportScreen(User $user)
    {
        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($this->organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);

        return Livewire::actingAs($user)->test(FinanceTrialBalanceReport::class);
    }

    private function organizationUrl(): string
    {
        return 'http://' . $this->organization->slug . '.lvh.me:8000'
            . route('finance.reports.trial-balance', [], false);
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
            'name' => 'Finance Trial Balance Test Role',
            'code' => 'finance_tb_ui_' . Str::lower(Str::random(8)),
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
