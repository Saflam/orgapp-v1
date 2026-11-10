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
use Modules\Core\Services\RoleAssignmentService;
use Modules\Finance\Livewire\FinanceJournalManagement;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceJournalManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private $period;
    private FinanceAccount $cash;
    private FinanceAccount $income;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->scope = app(FinanceScopeService::class)->organizationScope($this->organization);

        app(FinanceAccountProvisioningService::class)->provisionDefaults(
            $this->organization,
            $this->scope,
        );

        $this->cash = FinanceAccount::query()
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', '1000')
            ->firstOrFail();

        $this->income = FinanceAccount::query()
            ->where('finance_scope_id', $this->scope->id)
            ->where('code', '4000')
            ->firstOrFail();

        $this->period = app(FinancePeriodService::class)->create(
            $this->organization,
            $this->scope,
            [
                'name' => 'Financial Year 2026',
                'code' => 'FY2026',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ],
        );
    }

    public function test_authorized_user_can_post_a_balanced_manual_journal(): void
    {
        $user = $this->authorizeUser(['finance.journal.view', 'finance.journal.post']);

        $component = $this->journalScreen($user)
            ->set('transactionDate', '2026-10-10')
            ->set('periodId', $this->period->id)
            ->set('description', 'Manual income adjustment')
            ->set('lines.0.finance_account_id', $this->cash->id)
            ->set('lines.0.debit', '1250.0000')
            ->set('lines.0.credit', '0.0000')
            ->set('lines.1.finance_account_id', $this->income->id)
            ->set('lines.1.debit', '0.0000')
            ->set('lines.1.credit', '1250.0000')
            ->call('postJournal')
            ->assertHasNoErrors()
            ->assertSee('posted successfully');

        $this->assertDatabaseHas('finance_journals', [
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'finance_period_id' => $this->period->id,
            'description' => 'Manual income adjustment',
            'status' => 'posted',
        ]);

        $journal = FinanceJournal::query()
            ->where('organization_id', $this->organization->id)
            ->where('description', 'Manual income adjustment')
            ->firstOrFail();

        $this->assertSame(2, $journal->lines()->count());
        $this->assertSame('1250.0000', $journal->lines()->where('finance_account_id', $this->cash->id)->value('debit'));
        $this->assertSame('1250.0000', $journal->lines()->where('finance_account_id', $this->income->id)->value('credit'));
    }

    public function test_unbalanced_manual_journal_is_rejected_without_creating_a_journal(): void
    {
        $user = $this->authorizeUser(['finance.journal.view', 'finance.journal.post']);

        $this->journalScreen($user)
            ->set('transactionDate', '2026-10-10')
            ->set('periodId', $this->period->id)
            ->set('lines.0.finance_account_id', $this->cash->id)
            ->set('lines.0.debit', '100.0000')
            ->set('lines.0.credit', '0.0000')
            ->set('lines.1.finance_account_id', $this->income->id)
            ->set('lines.1.debit', '0.0000')
            ->set('lines.1.credit', '90.0000')
            ->call('postJournal')
            ->assertHasErrors('journal');

        $this->assertSame(
            0,
            FinanceJournal::query()
                ->where('organization_id', $this->organization->id)
                ->count(),
        );
    }

    public function test_user_with_view_permission_cannot_post_a_journal(): void
    {
        $user = $this->authorizeUser(['finance.journal.view']);

        $this->journalScreen($user)
            ->assertDontSee('Post Journal')
            ->call('postJournal')
            ->assertForbidden();

        $this->assertFalse(
            app(\Modules\Core\Services\AuthorizationService::class)->can(
                $user,
                'finance.journal.post',
                new \Modules\Core\Data\AuthorizationContext($this->organization->id),
            ),
        );
    }

    public function test_manual_journal_rejects_an_account_from_another_organization(): void
    {
        $user = $this->authorizeUser(['finance.journal.view', 'finance.journal.post']);

        $otherOrganization = Organization::factory()->create();
        $otherScope = app(FinanceScopeService::class)->organizationScope($otherOrganization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($otherOrganization, $otherScope);
        $foreignCash = FinanceAccount::query()
            ->where('finance_scope_id', $otherScope->id)
            ->where('code', '1000')
            ->firstOrFail();

        $this->journalScreen($user)
            ->set('transactionDate', '2026-10-10')
            ->set('periodId', $this->period->id)
            ->set('lines.0.finance_account_id', $foreignCash->id)
            ->set('lines.0.debit', '100.0000')
            ->set('lines.0.credit', '0.0000')
            ->set('lines.1.finance_account_id', $this->income->id)
            ->set('lines.1.debit', '0.0000')
            ->set('lines.1.credit', '100.0000')
            ->call('postJournal')
            ->assertHasErrors('journal');

        $this->assertSame(
            0,
            FinanceJournal::query()
                ->where('organization_id', $this->organization->id)
                ->count(),
        );
    }

    public function test_journal_register_only_shows_the_current_organizations_journals(): void
    {
        $user = $this->authorizeUser(['finance.journal.view', 'finance.journal.post']);

        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => '2026-10-10',
            'description' => 'Current organization journal',
            'lines' => [
                ['finance_account_id' => $this->cash->id, 'debit' => '50.0000'],
                ['finance_account_id' => $this->income->id, 'credit' => '50.0000'],
            ],
        ]);

        $otherOrganization = Organization::factory()->create();
        $otherScope = app(FinanceScopeService::class)->organizationScope($otherOrganization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($otherOrganization, $otherScope);
        $otherPeriod = app(FinancePeriodService::class)->create($otherOrganization, $otherScope, [
            'name' => 'Financial Year 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $otherCash = FinanceAccount::query()->where('finance_scope_id', $otherScope->id)->where('code', '1000')->firstOrFail();
        $otherIncome = FinanceAccount::query()->where('finance_scope_id', $otherScope->id)->where('code', '4000')->firstOrFail();

        app(FinanceJournalService::class)->post($otherOrganization, $otherScope, $otherPeriod, [
            'transaction_date' => '2026-10-10',
            'description' => 'Foreign organization journal',
            'lines' => [
                ['finance_account_id' => $otherCash->id, 'debit' => '75.0000'],
                ['finance_account_id' => $otherIncome->id, 'credit' => '75.0000'],
            ],
        ]);

        $this->journalScreen($user)
            ->assertSee('Current organization journal')
            ->assertDontSee('Foreign organization journal');
    }


    public function test_manual_journal_can_be_attributed_to_a_unit(): void
    {
        $user = $this->authorizeUser(['finance.journal.view', 'finance.journal.post']);
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);

        $this->journalScreen($user)
            ->set('transactionDate', '2026-10-10')
            ->set('periodId', $this->period->id)
            ->set('unitId', $unit->id)
            ->set('lines.0.finance_account_id', $this->cash->id)
            ->set('lines.0.debit', '80.0000')
            ->set('lines.0.credit', '0.0000')
            ->set('lines.1.finance_account_id', $this->income->id)
            ->set('lines.1.debit', '0.0000')
            ->set('lines.1.credit', '80.0000')
            ->call('postJournal')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('finance_journals', [
            'organization_id' => $this->organization->id,
            'unit_id' => $unit->id,
            'description' => null,
        ]);
    }

    public function test_manual_journal_rejects_a_closed_financial_period(): void
    {
        $user = $this->authorizeUser(['finance.journal.view', 'finance.journal.post']);
        app(FinancePeriodService::class)->close($this->period);

        $this->journalScreen($user)
            ->set('transactionDate', '2026-10-10')
            ->set('periodId', $this->period->id)
            ->set('lines.0.finance_account_id', $this->cash->id)
            ->set('lines.0.debit', '80.0000')
            ->set('lines.0.credit', '0.0000')
            ->set('lines.1.finance_account_id', $this->income->id)
            ->set('lines.1.debit', '0.0000')
            ->set('lines.1.credit', '80.0000')
            ->call('postJournal')
            ->assertHasErrors('journal');

        $this->assertSame(0, FinanceJournal::query()->where('organization_id', $this->organization->id)->count());
    }

    private function journalScreen(User $user)
    {
        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($this->organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);

        return Livewire::actingAs($user)->test(FinanceJournalManagement::class);
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
            'name' => 'Finance Journal Test Role',
            'code' => 'finance_journal_test_' . Str::lower(Str::random(8)),
        ]);

        $permissions = collect($permissionCodes)->map(fn (string $code) => Permission::create([
            'module' => 'finance',
            'name' => str($code)->headline(),
            'code' => $code,
        ]));

        $role->permissions()->attach($permissions->pluck('id')->all());

        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $this->organization,
        );

        return $user;
    }
}
