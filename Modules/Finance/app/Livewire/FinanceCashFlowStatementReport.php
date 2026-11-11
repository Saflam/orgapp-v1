<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Services\FinanceCashFlowStatementService;
use Modules\Finance\Services\FinanceScopeService;

class FinanceCashFlowStatementReport extends Component
{
    public Organization $organization;

    public string $startDate = '';
    public string $endDate = '';
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

    public function updatedUnitId(): void
    {
        if ($this->unitId === 0) {
            $this->unitId = null;
        }
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

        if ($this->unitId !== null) {
            abort_unless($units->contains('id', $this->unitId), 404);
        }

        $report = $cashFlowStatement->generate(
            $this->organization,
            $scope,
            $this->startDate,
            $this->endDate,
            $this->unitId,
        );

        return view('finance::livewire.reports.cash-flow-statement', [
            'units' => $units,
            'report' => $report,
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
