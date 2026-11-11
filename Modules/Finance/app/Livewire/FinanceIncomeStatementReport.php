<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Committee\Models\Committee;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Services\FinanceIncomeStatementService;
use Modules\Finance\Services\FinanceScopeService;
use Illuminate\Support\Facades\Schema;

class FinanceIncomeStatementReport extends Component
{
    public Organization $organization;

    public ?int $periodId = null;
    public string $startDate = '';
    public string $endDate = '';

    public string $costCenterType = 'all';
    public ?int $costCenterOwnerId = null;

    // Retained for compatibility with existing callers and tests.
    public ?int $unitId = null;

    public function mount(
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): void {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);

        $this->ensureCanView($authorization);

        $this->startDate = now()->startOfYear()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function updatedPeriodId(FinanceScopeService $scopes): void
    {
        if ($this->periodId === null || $this->periodId === 0) {
            return;
        }

        $scope = $scopes->organizationScope($this->organization);
        $period = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->findOrFail($this->periodId);

        $this->startDate = $period->starts_on->toDateString();
        $this->endDate = $period->ends_on->toDateString();
    }

    public function updatedStartDate(): void
    {
        $this->periodId = null;
    }

    public function updatedEndDate(): void
    {
        $this->periodId = null;
    }

    public function updatedCostCenterType(): void
    {
        if (! in_array($this->costCenterType, ['all', 'organization', 'unit', 'committee'], true)) {
            $this->costCenterType = 'all';
        }

        $this->costCenterOwnerId = $this->costCenterType === 'organization'
            ? (int) $this->organization->id
            : null;

        $this->unitId = null;
    }

    public function updatedCostCenterOwnerId(): void
    {
        $this->unitId = $this->costCenterType === 'unit'
            ? $this->costCenterOwnerId
            : null;
    }

    public function updatedUnitId(): void
    {
        if ($this->unitId === 0) {
            $this->unitId = null;
        }

        if ($this->unitId === null) {
            $this->costCenterType = 'all';
            $this->costCenterOwnerId = null;

            return;
        }

        $this->costCenterType = 'unit';
        $this->costCenterOwnerId = $this->unitId;
    }

    public function render(
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
        FinanceIncomeStatementService $incomeStatement,
    ): View {
        $this->ensureCanView($authorization);

        Validator::make([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ], [
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ])->validate();

        $scope = $scopes->organizationScope($this->organization);

        $periods = FinancePeriod::query()
            ->where('organization_id', $this->organization->id)
            ->where('finance_scope_id', $scope->id)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        $units = Unit::query()
            ->where('organization_id', $this->organization->id)
            ->orderBy('name')
            ->get();

        $hasCommitteeTable = Schema::hasTable('committees');
        $committees = collect();

        if ($this->costCenterType === 'committee' && $hasCommitteeTable) {
            $committees = Committee::query()
                ->where('organization_id', $this->organization->id)
                ->orderBy('name')
                ->get();
        }

        if ($this->costCenterType === 'organization') {
            $this->costCenterOwnerId = (int) $this->organization->id;
        }

        if ($this->costCenterType === 'unit' && $this->costCenterOwnerId !== null) {
            abort_unless($units->contains('id', $this->costCenterOwnerId), 404);
            $this->unitId = $this->costCenterOwnerId;
        }

        if ($this->costCenterType === 'committee' && $this->costCenterOwnerId !== null) {
            abort_unless($committees->contains('id', $this->costCenterOwnerId), 404);
        }

        $requiresOwner = in_array($this->costCenterType, ['unit', 'committee'], true)
            && $this->costCenterOwnerId === null;

        if ($requiresOwner) {
            $report = [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'unit_id' => null,
                'cost_center_type' => $this->costCenterType,
                'cost_center_owner_id' => null,
                'income_accounts' => [],
                'expense_accounts' => [],
                'total_income' => '0.0000',
                'total_expenses' => '0.0000',
                'net_surplus' => '0.0000',
                'filter_required' => true,
            ];
        } else {
            $report = $incomeStatement->generate(
                $this->organization,
                $scope,
                $this->startDate,
                $this->endDate,
                null,
                $this->costCenterType,
                $this->costCenterOwnerId,
            );
        }

        return view('finance::livewire.reports.income-statement', [
            'periods' => $periods,
            'units' => $units,
            'committees' => $committees,
            'hasCommitteeTable' => $hasCommitteeTable,
            'report' => $report,
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Income Statement',
            'layoutOrganization' => $this->organization,
        ]);
    }

    private function ensureCanView(AuthorizationService $authorization): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null && $this->organization->exists && $authorization->can(
                $user,
                'finance.report.view',
                new AuthorizationContext((int) $this->organization->id),
            ),
            403,
        );
    }
}
