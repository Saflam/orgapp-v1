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
use Modules\Finance\Livewire\FinanceJournalDetail;
use Modules\Finance\Livewire\FinanceJournalManagement;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceJournalDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_journal_header_lines_and_balanced_totals(): void
    {
        [$organization, $scope, $period, $cash, $income, $journal] = $this->createJournal();
        $user = $this->authorizeUser($organization, ['finance.journal.view']);

        $this->detailScreen($user, $organization, $journal->id)
            ->assertSee($journal->journal_number)
            ->assertSee('FY2026')
            ->assertSee('Manual detail test')
            ->assertSee($cash->code)
            ->assertSee($cash->name)
            ->assertSee($income->code)
            ->assertSee($income->name)
            ->assertSee('125.0000')
            ->assertSee('Journal lines')
            ->assertSee('Totals');
    }

    public function test_journal_register_provides_a_view_link_to_each_journal(): void
    {
        [$organization, , , , , $journal] = $this->createJournal();
        $user = $this->authorizeUser($organization, ['finance.journal.view']);
        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);

        Livewire::actingAs($user)
            ->test(FinanceJournalManagement::class)
            ->assertSee(route('finance.journals.show', $journal->id));
    }

    public function test_user_without_journal_view_permission_cannot_view_detail(): void
    {
        [$organization, , , , , $journal] = $this->createJournal();
        $user = $this->authorizeUser($organization, []);

        $this->detailScreen($user, $organization, $journal->id)->assertForbidden();
    }

    public function test_journal_detail_cannot_be_used_to_view_another_organizations_journal(): void
    {
        [$organization, , , , , ] = $this->createJournal();
        [$otherOrganization, , , , , $foreignJournal] = $this->createJournal();
        $user = $this->authorizeUser($organization, ['finance.journal.view']);

        $this->detailScreen($user, $organization, $foreignJournal->id)->assertStatus(404);
    }

    public function test_reversal_detail_shows_original_journal_and_reason(): void
    {
        [$organization, $scope, $period, , , $original] = $this->createJournal();
        $user = $this->authorizeUser($organization, ['finance.journal.view']);
        $reversal = app(FinanceJournalService::class)->reverse(
            $organization,
            $scope,
            $original,
            $period,
            '2026-11-01',
            'Duplicate entry correction.',
        );

        $this->detailScreen($user, $organization, $reversal->id)
            ->assertSee('Reversal relationship')
            ->assertSee($original->journal_number)
            ->assertSee('Duplicate entry correction.');
    }

    private function createJournal(): array
    {
        $organization = Organization::factory()->create();
        $scope = app(FinanceScopeService::class)->organizationScope($organization);
        app(FinanceAccountProvisioningService::class)->provisionDefaults($organization, $scope);
        $period = app(FinancePeriodService::class)->create($organization, $scope, [
            'name' => 'Financial Year 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
        $cash = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '1000')->firstOrFail();
        $income = FinanceAccount::query()->where('finance_scope_id', $scope->id)->where('code', '4000')->firstOrFail();
        $journal = app(FinanceJournalService::class)->post($organization, $scope, $period, [
            'transaction_date' => '2026-10-10',
            'description' => 'Manual detail test',
            'lines' => [
                ['finance_account_id' => $cash->id, 'description' => 'Cash received', 'debit' => '125.0000'],
                ['finance_account_id' => $income->id, 'description' => 'Income earned', 'credit' => '125.0000'],
            ],
        ]);

        return [$organization, $scope, $period, $cash, $income, $journal];
    }

    private function detailScreen(User $user, Organization $organization, int $journalId)
    {
        $currentOrganization = new CurrentOrganization();
        $currentOrganization->set($organization);
        app()->instance(CurrentOrganization::class, $currentOrganization);

        return Livewire::actingAs($user)->test(FinanceJournalDetail::class, ['journalId' => $journalId]);
    }

    private function authorizeUser(Organization $organization, array $permissionCodes): User
    {
        $user = User::factory()->create();
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Finance Detail Test Role',
            'code' => 'finance_detail_' . Str::lower(Str::random(8)),
        ]);
        $permissions = collect($permissionCodes)->map(fn (string $code) => Permission::create([
            'module' => 'finance',
            'name' => str($code)->headline(),
            'code' => $code,
        ]));
        $role->permissions()->attach($permissions->pluck('id')->all());
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

        return $user;
    }
}
