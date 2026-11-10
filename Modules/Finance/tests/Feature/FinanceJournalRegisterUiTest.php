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
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceJournalRegisterUiTest extends TestCase
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
        app(FinanceAccountProvisioningService::class)->provisionDefaults($this->organization, $this->scope);

        $this->cash = FinanceAccount::query()->where('finance_scope_id', $this->scope->id)->where('code', '1000')->firstOrFail();
        $this->income = FinanceAccount::query()->where('finance_scope_id', $this->scope->id)->where('code', '4000')->firstOrFail();

        $this->period = app(FinancePeriodService::class)->create($this->organization, $this->scope, [
            'name' => 'Financial Year 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    public function test_journal_register_uses_fifteen_item_server_side_pagination(): void
    {
        $user = $this->authorizeUser(['finance.journal.view']);

        for ($index = 1; $index <= 16; $index++) {
            $this->createJournal($index === 1 ? 'Pagination oldest entry' : 'Pagination entry ' . $index);
        }

        $component = $this->journalScreen($user)
            ->assertSee('Pagination entry 16')
            ->assertDontSee('Pagination oldest entry')
            ->assertSee('Next');

        $component->set('paginators.page', 2)
            ->assertSee('Pagination oldest entry');

        $component->set('search', 'Pagination oldest entry')
            ->assertSet('paginators.page', 1)
            ->assertSee('Pagination oldest entry');
    }

    public function test_reversal_form_is_rendered_as_a_modal_and_can_be_cancelled(): void
    {
        $user = $this->authorizeUser(['finance.journal.view', 'finance.journal.reverse']);
        $journal = $this->createJournal('Modal reversal test');

        $component = $this->journalScreen($user)
            ->assertDontSee('role="dialog"', false)
            ->assertSee('View journal')
            ->assertSee('Reverse journal')
            ->call('prepareReversal', $journal->id)
            ->assertSee('role="dialog"', false)
            ->assertSee('Reverse journal');

        $component->call('cancelReversal')
            ->assertDontSee('role="dialog"', false);
    }

    private function createJournal(string $description): FinanceJournal
    {
        return app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => '2026-10-10',
            'description' => $description,
            'lines' => [
                ['finance_account_id' => $this->cash->id, 'debit' => '50.0000'],
                ['finance_account_id' => $this->income->id, 'credit' => '50.0000'],
            ],
        ]);
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
            'name' => 'Finance Journal UI Test Role',
            'code' => 'finance_journal_ui_' . Str::lower(Str::random(8)),
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
