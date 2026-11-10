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
use Modules\Core\Services\RoleAssignmentService;
use Modules\Finance\Livewire\FinanceJournalManagement;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceJournalReversalUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_reverse_permission_can_reverse_a_journal_from_the_register(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();
        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-10',
            'description' => 'UI reversal test',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '25.0000'],
                ['finance_account_id' => $income->id, 'credit' => '25.0000'],
            ],
        ]);

        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Finance Reversal Role',
            'code' => 'finance_reversal_' . Str::lower(Str::random(8)),
        ]);
        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'Reverse Journal Entries',
            'code' => 'finance.journal.reverse',
        ]);
        $role->permissions()->attach($permission->id);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);

        Livewire::actingAs($user)
            ->test(FinanceJournalManagement::class)
            ->assertSee('Reverse')
            ->call('prepareReversal', $journal->id)
            ->set('reversalDate', '2026-11-01')
            ->set('reversalPeriodId', $period->id)
            ->set('reversalReason', 'Incorrect posting.')
            ->call('reverseJournal')
            ->assertHasNoErrors()
            ->assertSee('reversed successfully');

        $this->assertDatabaseHas('finance_journals', [
            'reversal_of_journal_id' => $journal->id,
            'reversal_reason' => 'Incorrect posting.',
            'status' => 'posted',
        ]);
    }

    public function test_user_without_reverse_permission_cannot_invoke_reversal(): void
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'FY 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();
        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-10',
            'lines' => [
                ['finance_account_id' => $cash->id, 'debit' => '25.0000'],
                ['finance_account_id' => $income->id, 'credit' => '25.0000'],
            ],
        ]);

        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Finance Viewer Role',
            'code' => 'finance_viewer_' . Str::lower(Str::random(8)),
        ]);
        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Journal Entries',
            'code' => 'finance.journal.view',
        ]);
        $role->permissions()->attach($permission->id);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);

        Livewire::actingAs($user)
            ->test(FinanceJournalManagement::class)
            ->assertDontSee('Reverse')
            ->call('prepareReversal', $journal->id)
            ->assertForbidden();

        $this->assertDatabaseCount('finance_journals', 1);
    }
}
