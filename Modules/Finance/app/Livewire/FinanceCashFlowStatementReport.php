<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Committee\Models\Committee;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Services\FinanceCashFlowStatementService;
use Modules\Finance\Services\FinanceScopeService;

class FinanceCashFlowStatementReport extends Component
{
    public Organization $organization;

    public string $startDate = '';
    public string $endDate = '';
    public string $costCenterType = 'all';
    public ?int $costCenterOwnerId = null;

    // Retained for compatibility with existing Livewire callers.
    public ?int $unitId = null;

    public function mount(
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
    ): void {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);

        $this->startDate = now()->startOfYear()->toDateString();
        $this->endDate = now()->toDateString();

        $this->ensureCanView($authorization);
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
        $this->unitId = $this->costCenterType === 'unit' ? $this->costCenterOwnerId : null;
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
        FinanceCashFlowStatementService $cashFlowStatement,
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
        } elseif ($this->costCenterType === 'unit' && $this->costCenterOwnerId !== null) {
            abort_unless($units->contains('id', $this->costCenterOwnerId), 404);
            $this->unitId = $this->costCenterOwnerId;
        } elseif ($this->costCenterType === 'committee' && $this->costCenterOwnerId !== null) {
            abort_unless($committees->contains('id', $this->costCenterOwnerId), 404);
        }

        $filterRequired = in_array($this->costCenterType, ['unit', 'committee'], true)
            && $this->costCenterOwnerId === null;

        $report = $filterRequired
            ? [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'unit_id' => null,
                'cost_center_type' => $this->costCenterType,
                'cost_center_owner_id' => null,
                'operating_net_cash_flow' => '0.0000',
                'investing_net_cash_flow' => '0.0000',
                'financing_net_cash_flow' => '0.0000',
                'net_change_in_cash' => '0.0000',
                'opening_cash_balance' => '0.0000',
                'closing_cash_balance' => '0.0000',
                'filter_required' => true,
            ]
            : $cashFlowStatement->generate(
                $this->organization,
                $scope,
                $this->startDate,
                $this->endDate,
                null,
                $this->costCenterType,
                $this->costCenterOwnerId,
            );

        return view('finance::livewire.reports.cash-flow-statement', [
            'units' => $units,
            'committees' => $committees,
            'hasCommitteeTable' => $hasCommitteeTable,
            'report' => $report,
            'filterRequired' => $filterRequired,
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Cash Flow Statement',
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
