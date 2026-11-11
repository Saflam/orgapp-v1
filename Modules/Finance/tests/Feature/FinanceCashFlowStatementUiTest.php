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
use Modules\Finance\Livewire\FinanceCashFlowStatementReport;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceCashFlowStatementUiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private $period;
    private FinanceAccount $cash;
    private FinanceAccount $income;
    private FinanceAccount $expense;
    private FinanceAccount $equipment;
    private FinanceAccount $loan;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.deployment_mode' => 'multi', 'app.organization_base_domain' => 'lvh.me']);

        $this->organization = Organization::factory()->create();
        $modules = app(OrganizationModuleService::class);
        $modules->enable($this->organization, 'Core');
        $modules->enable($this->organization, 'Member');
        $modules->enable($this->organization, 'Finance');

        $this->scope = app(FinanceScopeService::class)->organizationScope($this->organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($this->organization, $this->scope);

        $this->cash = $this->account('1000');
        $this->cash->update(['is_cash_account' => true]);
        $this->income = $this->account('4000');
        $this->income->update(['cash_flow_category' => 'operating']);
        $this->expense = $this->account('5000');
        $this->expense->update(['cash_flow_category' => 'operating']);
        $this->equipment = $this->createAccount('1500', 'Equipment', 'asset', 'debit', ['cash_flow_category' => 'investing']);
        $this->loan = $this->createAccount('2500', 'Long-term Loan', 'liability', 'credit', ['cash_flow_category' => 'financing']);

        $this->period = app(FinancePeriodService::class)->create($this->organization, $this->scope, [
            'name' => 'Financial Year 2026', 'code' => 'FY2026',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);
    }

    public function test_authorized_user_can_view_cash_flow_statement_and_finance_reports_sidebar_link(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-01-05', 'Membership collections', [$this->debit($this->cash->id, '500.0000'), $this->credit($this->income->id, '500.0000')]);
        $this->postJournal('2026-02-05', 'Operating expense paid', [$this->debit($this->expense->id, '125.1250'), $this->credit($this->cash->id, '125.1250')]);
        $this->postJournal('2026-03-05', 'Equipment purchase', [$this->debit($this->equipment->id, '200.0000'), $this->credit($this->cash->id, '200.0000')]);
        $this->postJournal('2026-04-05', 'Loan proceeds', [$this->debit($this->cash->id, '300.0000'), $this->credit($this->loan->id, '300.0000')]);

        $this->reportScreen($user)
            ->assertSee('Cash Flow Statement')->assertSee('Start date')->assertSee('End date')->assertSee('All Units')
            ->assertSee('Opening cash')->assertSee('Operating activities')->assertSee('374.8750')
            ->assertSee('Investing activities')->assertSee('-200.0000')
            ->assertSee('Financing activities')->assertSee('300.0000')
            ->assertSee('Net change in cash')->assertSee('474.8750')
            ->assertSee('Closing cash');
        $this->actingAs($user)->get($this->cashFlowUrl())->assertOk()
            ->assertSee('Finance')->assertSee('Reports')->assertSee('Cash Flow Statement');
    }

    public function test_user_without_report_permission_is_forbidden(): void
    {
        $user = $this->authorizeUser(['finance.journal.view']);
        $this->reportScreen($user)->assertForbidden();
        $this->actingAs($user)->get($this->cashFlowUrl())->assertForbidden();
    }

    public function test_report_uses_inclusive_date_range_and_calculates_opening_and_closing_cash(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-01-01', 'Opening cash', [$this->debit($this->cash->id, '100.0000'), $this->credit($this->income->id, '100.0000')]);
        $this->postJournal('2026-03-01', 'Start date receipt', [$this->debit($this->cash->id, '50.0000'), $this->credit($this->income->id, '50.0000')]);
        $this->postJournal('2026-03-31', 'End date payment', [$this->debit($this->expense->id, '25.0000'), $this->credit($this->cash->id, '25.0000')]);
        $this->postJournal('2026-04-01', 'After end date', [$this->debit($this->cash->id, '90.0000'), $this->credit($this->income->id, '90.0000')]);

        $this->reportScreen($user)->set('startDate', '2026-03-01')->set('endDate', '2026-03-31')
            ->assertSee('100.0000')->assertSee('25.0000')->assertDontSee('90.0000');
    }

    public function test_selected_unit_filters_cash_flows_and_all_units_includes_unattributed_entries(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $unitA = Unit::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Unit A']);
        $unitB = Unit::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Unit B']);
        $this->postJournal('2026-05-01', 'Organization-wide receipt', [$this->debit($this->cash->id, '10.0000'), $this->credit($this->income->id, '10.0000')]);
        $this->postJournal('2026-05-02', 'Unit A receipt', [$this->debit($this->cash->id, '20.0000'), $this->credit($this->income->id, '20.0000')], $unitA->id);
        $this->postJournal('2026-05-03', 'Unit B receipt', [$this->debit($this->cash->id, '30.0000'), $this->credit($this->income->id, '30.0000')], $unitB->id);

        $component = $this->reportScreen($user)->set('startDate', '2026-01-01')->set('endDate', '2026-12-31');
        $component->set('unitId', $unitA->id)->assertSee('20.0000')->assertDontSee('60.0000');
        $component->set('unitId', null)->assertSee('60.0000');
    }

    public function test_foreign_organization_unit_cannot_be_selected(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $otherOrganization = Organization::factory()->create();
        $otherUnit = Unit::factory()->create(['organization_id' => $otherOrganization->id]);
        $this->reportScreen($user)->set('unitId', $otherUnit->id)->assertStatus(404);
    }

    private function account(string $code): FinanceAccount
    {
        return FinanceAccount::query()->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $this->scope->id)->where('code', $code)->firstOrFail();
    }

    private function createAccount(string $code, string $name, string $type, string $normalBalance, array $attributes = []): FinanceAccount
    {
        return FinanceAccount::create(array_merge([
            'organization_id' => $this->organization->id, 'finance_scope_id' => $this->scope->id,
            'code' => $code, 'name' => $name, 'account_type' => $type, 'normal_balance' => $normalBalance,
            'is_system_managed' => false, 'is_active' => true, 'metadata' => [],
        ], $attributes));
    }

    private function postJournal(string $date, string $description, array $lines, ?int $unitId = null): void
    {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => $date, 'description' => $description, 'unit_id' => $unitId, 'lines' => $lines,
        ]);
    }

    private function debit(int $accountId, string $amount): array { return ['finance_account_id' => $accountId, 'debit' => $amount]; }
    private function credit(int $accountId, string $amount): array { return ['finance_account_id' => $accountId, 'credit' => $amount]; }

    private function reportScreen(User $user)
    {
        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($this->organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);
        return Livewire::actingAs($user)->test(FinanceCashFlowStatementReport::class);
    }

    private function cashFlowUrl(): string
    {
        return 'http://' . $this->organization->slug . '.lvh.me:8000'
            . route('finance.reports.cash-flow-statement', [], false);
    }

    private function authorizeUser(array $permissionCodes): User
    {
        $user = User::factory()->create();
        $membership = OrganizationMembership::create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'status' => 'active']);
        $role = Role::create(['organization_id' => $this->organization->id, 'name' => 'Finance Cash Flow UI Test Role', 'code' => 'finance_cf_ui_' . Str::lower(Str::random(8))]);
        $permissions = collect($permissionCodes)->map(fn (string $code) => Permission::create([
            'module' => 'finance', 'name' => str($code)->headline(), 'code' => $code,
        ]));
        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $this->organization);
        return $user;
    }
}
