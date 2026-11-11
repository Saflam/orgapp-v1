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

class FinanceCashFlowStatementPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private $scope;
    private $period;
    private FinanceAccount $cash;
    private FinanceAccount $income;
    private FinanceAccount $equipment;
    private FinanceAccount $loan;

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
        $this->cash->update(['is_cash_account' => true]);

        $this->income = $this->account('4000');
        $this->income->update(['cash_flow_category' => 'operating']);

        $this->equipment = $this->createAccount(
            '1500',
            'Equipment',
            'asset',
            'debit',
            ['cash_flow_category' => 'investing'],
        );

        $this->loan = $this->createAccount(
            '2500',
            'Long-term Loan',
            'liability',
            'credit',
            ['cash_flow_category' => 'financing'],
        );

        $this->period = app(FinancePeriodService::class)->create($this->organization, $this->scope, [
            'name' => 'Financial Year 2026',
            'code' => 'FY2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    public function test_authorized_user_can_download_cash_flow_statement_pdf(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $this->postJournal('2026-04-10', [
            $this->debit($this->cash->id, '500.0000'),
            $this->credit($this->income->id, '500.0000'),
        ]);

        $response = $this->actingAs($user)->get($this->pdfUrl([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString(
            'cash-flow-statement',
            strtolower($response->headers->get('content-disposition', '')),
        );
        $this->assertStringContainsString('2026-04-01', $response->headers->get('content-disposition', ''));
        $this->assertStringContainsString('2026-04-30', $response->headers->get('content-disposition', ''));
    }


    public function test_pdf_export_accepts_new_cost_center_parameters(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);
        $unit = Unit::factory()->create(['organization_id' => $this->organization->id]);
        $this->postJournal('2026-04-10', [
            $this->debit($this->cash->id, '75.0000'),
            $this->credit($this->income->id, '75.0000'),
        ], $unit->id);

        $this->actingAs($user)->get($this->pdfUrl([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cost_center_type' => 'unit',
            'cost_center_owner_id' => $unit->id,
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_without_report_permission_cannot_download_cash_flow_statement_pdf(): void
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

    public function test_pdf_export_rejects_invalid_dates(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);

        $this->actingAs($user)
            ->get($this->pdfUrl([
                'start_date' => 'not-a-date',
                'end_date' => '2026-04-30',
            ]))
            ->assertUnprocessable();
    }

    public function test_pdf_export_rejects_an_end_date_before_the_start_date(): void
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

        $this->postJournal('2026-04-10', [
            $this->debit($this->cash->id, '80.0000'),
            $this->credit($this->income->id, '80.0000'),
        ], $unit->id);

        $response = $this->actingAs($user)->get($this->pdfUrl([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'unit_id' => $unit->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('cash-flow-statement', strtolower(
            $response->headers->get('content-disposition', ''),
        ));
    }

    public function test_pdf_export_requires_both_date_parameters(): void
    {
        $user = $this->authorizeUser(['finance.report.view']);

        $this->actingAs($user)
            ->get($this->pdfUrl(['start_date' => '2026-04-01']))
            ->assertUnprocessable();

        $this->actingAs($user)
            ->get($this->pdfUrl(['end_date' => '2026-04-30']))
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

    private function createAccount(
        string $code,
        string $name,
        string $type,
        string $normalBalance,
        array $attributes = [],
    ): FinanceAccount {
        return FinanceAccount::create(array_merge([
            'organization_id' => $this->organization->id,
            'finance_scope_id' => $this->scope->id,
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'normal_balance' => $normalBalance,
            'is_system_managed' => false,
            'is_active' => true,
            'metadata' => [],
        ], $attributes));
    }

    private function postJournal(string $date, array $lines, ?int $unitId = null): void
    {
        app(FinanceJournalService::class)->post($this->organization, $this->scope, $this->period, [
            'transaction_date' => $date,
            'unit_id' => $unitId,
            'lines' => $lines,
        ]);
    }

    private function debit(int $accountId, string $amount): array
    {
        return ['finance_account_id' => $accountId, 'debit' => $amount];
    }

    private function credit(int $accountId, string $amount): array
    {
        return ['finance_account_id' => $accountId, 'credit' => $amount];
    }

    private function pdfUrl(array $query = []): string
    {
        $path = route('finance.reports.cash-flow-statement.pdf', $query, false);

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
            'name' => 'Finance Cash Flow PDF Test Role',
            'code' => 'finance_cf_pdf_' . Str::lower(Str::random(8)),
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
