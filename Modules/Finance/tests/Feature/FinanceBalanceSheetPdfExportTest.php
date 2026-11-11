<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Unit;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\RoleAssignmentService;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Services\FinanceAccountProvisioningService;
use Modules\Finance\Services\FinanceJournalService;
use Modules\Finance\Services\FinancePeriodService;
use Modules\Finance\Services\FinanceScopeService;
use Tests\TestCase;

class FinanceBalanceSheetPdfExportTest extends TestCase
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

        $modules = app(OrganizationModuleService::class);
        $modules->enable($this->organization, 'Core');
        $modules->enable($this->organization, 'Member');
        $modules->enable($this->organization, 'Finance');

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

    public function test_authorized_user_can_download_balance_sheet_pdf(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-04-10', $this->cash, $this->equity, '150.0000');

        $response = $this->actingAs($user)->get($this->pdfUrl([
            'as_of_date' => '2026-04-30',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString(
            'balance-sheet',
            strtolower($response->headers->get('content-disposition', ''))
        );
        $this->assertStringContainsString('2026-04-30', $response->headers->get('content-disposition', ''));
    }

    public function test_user_without_report_permission_cannot_download_balance_sheet_pdf(): void
    {
        $user = $this->authorizeUser(['finance.journal.view']);

        $this->actingAs($user)
            ->get($this->pdfUrl(['as_of_date' => '2026-06-30']))
            ->assertForbidden();
    }

    public function test_pdf_export_requires_authentication(): void
    {
        $this->get($this->pdfUrl(['as_of_date' => '2026-06-30']))
            ->assertRedirect();
    }

    public function test_pdf_export_rejects_an_invalid_as_of_date(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);

        $this->actingAs($user)
            ->get($this->pdfUrl(['as_of_date' => 'not-a-date']))
            ->assertUnprocessable();
    }


    public function test_pdf_export_accepts_new_cost_center_parameters(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $this->postJournal('2026-04-10', $this->cash, $this->equity, '75.0000', $unit->id);

        $this->actingAs($user)->get($this->pdfUrl([
            'as_of_date' => '2026-06-30',
            'cost_center_type' => 'unit',
            'cost_center_owner_id' => $unit->id,
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_export_rejects_a_unit_from_another_organization(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $otherOrganization = Organization::factory()->create();
        $foreignUnit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $this->actingAs($user)
            ->get($this->pdfUrl([
                'as_of_date' => '2026-06-30',
                'unit_id' => $foreignUnit->id,
            ]))
            ->assertNotFound();
    }

    public function test_pdf_export_accepts_a_unit_belonging_to_the_current_organization(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $unit = Unit::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'North Unit',
        ]);
        $this->postJournal('2026-04-10', $this->cash, $this->equity, '80.0000', $unit->id);

        $response = $this->actingAs($user)->get($this->pdfUrl([
            'as_of_date' => '2026-06-30',
            'unit_id' => $unit->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('balance-sheet', strtolower(
            $response->headers->get('content-disposition', '')
        ));
    }

    public function test_pdf_export_requires_an_as_of_date(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);

        $this->actingAs($user)
            ->get($this->pdfUrl())
            ->assertUnprocessable();
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
        ?int $unitId = null
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

    private function pdfUrl(array $query = []): string
    {
        $path = route('finance.reports.balance-sheet.pdf', $query, false);

        return 'http://' . $this->organization->slug . '.lvh.me:8000' . $path;
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
            'name' => 'Finance Balance Sheet PDF Test Role',
            'code' => 'finance_bs_pdf_' . Str::lower(Str::random(8)),
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
