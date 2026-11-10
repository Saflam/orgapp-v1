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

class FinanceIncomeStatementPdfExportTest extends TestCase
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

    public function test_authorized_user_can_download_income_statement_pdf(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-04-10', '150.0000', '45.2500');

        $response = $this->actingAs($user)->get($this->pdfUrl([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString(
            'income-statement',
            strtolower($response->headers->get('content-disposition', ''))
        );
    }

    public function test_user_without_report_permission_cannot_download_income_statement_pdf(): void
    {
        $user = $this->authorizeUser(['finance.journal.view']);

        $this->actingAs($user)
            ->get($this->pdfUrl([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
            ]))
            ->assertForbidden();
    }

    public function test_pdf_export_requires_authentication(): void
    {
        $this->get($this->pdfUrl([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))->assertRedirect();
    }

    public function test_pdf_export_rejects_invalid_date_range(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);

        $this->actingAs($user)
            ->get($this->pdfUrl([
                'start_date' => 'not-a-date',
                'end_date' => '2026-04-30',
            ]))
            ->assertUnprocessable();
    }

    public function test_pdf_export_rejects_end_date_before_start_date(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);

        $this->actingAs($user)
            ->get($this->pdfUrl([
                'start_date' => '2026-05-01',
                'end_date' => '2026-04-30',
            ]))
            ->assertUnprocessable();
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
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
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
        $this->postJournal('2026-04-10', '80.0000', '20.0000', $unit->id);

        $response = $this->actingAs($user)->get($this->pdfUrl([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'unit_id' => $unit->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
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
        string $incomeAmount,
        string $expenseAmount,
        ?int $unitId = null
    ): void {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => $date,
            'unit_id' => $unitId,
            'lines' => [
                ['finance_account_id' => $this->cash->id, 'debit' => $incomeAmount],
                ['finance_account_id' => $this->income->id, 'credit' => $incomeAmount],
                ['finance_account_id' => $this->expense->id, 'debit' => $expenseAmount],
                ['finance_account_id' => $this->cash->id, 'credit' => $expenseAmount],
            ],
        ]);
    }

    private function pdfUrl(array $query = []): string
    {
        $path = route('finance.reports.income-statement.pdf', $query, false);

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
            'name' => 'Finance Income Statement PDF Test Role',
            'code' => 'finance_is_pdf_' . Str::lower(Str::random(8)),
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
