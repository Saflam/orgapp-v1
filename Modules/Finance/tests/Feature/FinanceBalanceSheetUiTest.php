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
use Modules\Finance\Livewire\FinanceBalanceSheetReport;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceBalanceSheetUiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private $period;
    private FinanceAccount $cash;
    private FinanceAccount $payable;
    private FinanceAccount $equity;
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
        $this->payable = $this->account('2000');
        $this->equity = $this->account('3000');
        $this->income = $this->account('4000');
        $this->expense = $this->account('5000');

        $this->period = app(FinancePeriodService::class)->create($this->organization, $this->scope, [
            'name' => 'Financial Year 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    public function test_authorized_user_can_view_balance_sheet_and_reports_sidebar_link(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);

        $this->postJournal('2026-01-05', $this->cash, $this->equity, '1000.2500');
        $this->postJournal('2026-02-10', $this->cash, $this->income, '500.0000');
        $this->postJournal('2026-03-10', $this->expense, $this->cash, '125.1250');
        $this->postJournal('2026-04-10', $this->cash, $this->payable, '200.0000');

        $this->reportScreen($user)
            ->assertSee('Balance Sheet')
            ->assertSee('As of date')
            ->assertSee('All Units')
            ->assertSee('Assets')
            ->assertSee('1000')
            ->assertSee('Cash')
            ->assertSee('1575.1250')
            ->assertSee('Liabilities')
            ->assertSee('2000')
            ->assertSee('200.0000')
            ->assertSee('Equity')
            ->assertSee('3000')
            ->assertSee('1000.2500')
            ->assertSee('Current earnings')
            ->assertSee('374.8750')
            ->assertSee('Total liabilities and equity')
            ->assertSee('Difference')
            ->assertSee('0.0000');

        $this->actingAs($user)
            ->get($this->balanceSheetUrl())
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Balance Sheet');
    }


    public function test_unit_scope_requires_a_selected_unit_before_showing_balances(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $this->postJournal('2026-04-10', $this->cash, $this->equity, '4321.0000', $unit->id);

        $component = $this->reportScreen($user)
            ->set('costCenterType', 'unit')
            ->assertSee('Select a unit to generate this report.')
            ->assertDontSee('4321.0000');

        $component->set('costCenterOwnerId', $unit->id)
            ->assertSee('4321.0000');
    }

    public function test_user_without_report_permission_is_forbidden(): void
    {
        $user = $this->authorizeUser(['finance.journal.view']);

        $this->reportScreen($user)->assertForbidden();

        $this->actingAs($user)
            ->get($this->balanceSheetUrl())
            ->assertForbidden();
    }

    public function test_report_uses_the_selected_as_of_date_and_cumulative_balances(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);

        $this->postJournal('2026-04-10', $this->cash, $this->equity, '100.0000');
        $this->postJournal('2026-07-10', $this->cash, $this->equity, '50.0000');

        $this->reportScreen($user)
            ->set('asOfDate', '2026-06-30')
            ->assertSee('100.0000')
            ->assertDontSee('150.0000');
    }

    public function test_selected_unit_filters_balances_and_all_units_includes_unattributed_entries(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $unitA = Unit::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Unit A',
        ]);
        $unitB = Unit::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Unit B',
        ]);

        $this->postJournal('2026-05-01', $this->cash, $this->equity, '10.0000');
        $this->postJournal('2026-05-02', $this->cash, $this->equity, '20.0000', $unitA->id);
        $this->postJournal('2026-05-03', $this->cash, $this->equity, '30.0000', $unitB->id);

        $component = $this->reportScreen($user)->set('asOfDate', '2026-12-31');

        $component->set('unitId', $unitA->id)
            ->assertSee('20.0000')
            ->assertDontSee('60.0000');

        $component->set('unitId', null)
            ->assertSee('60.0000');
    }

    public function test_foreign_organization_unit_cannot_be_selected(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $otherOrganization = Organization::factory()->create();
        $otherUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $this->reportScreen($user)
            ->set('unitId', $otherUnit->id)
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

    private function postJournal(
        string $date,
        FinanceAccount $debitAccount,
        FinanceAccount $creditAccount,
        string $amount,
        ?int $unitId = null,
    ): void {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => $date,
            'unit_id' => $unitId,
            'lines' => [
                ['finance_account_id' => $debitAccount->id, 'debit' => $amount],
                ['finance_account_id' => $creditAccount->id, 'credit' => $amount],
            ],
        ]);
    }

    private function reportScreen(User $user)
    {
        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($this->organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);

        return Livewire::actingAs($user)->test(FinanceBalanceSheetReport::class);
    }

    private function balanceSheetUrl(): string
    {
        return 'http://' . $this->organization->slug . '.lvh.me:8000'
            . route('finance.reports.balance-sheet', [], false);
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
            'name' => 'Finance Balance Sheet Test Role',
            'code' => 'finance_bs_ui_' . Str::lower(Str::random(8)),
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
