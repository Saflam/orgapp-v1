<?php

namespace Modules\Finance\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Committee\Models\Committee;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceTrialBalanceService;

class FinanceTrialBalanceReport extends Component
{
    public Organization $organization;

    public ?int $periodId = null;
    public string $asOfDate = '';
    public ?int $unitId = null;
    public string $costCenterType = 'all';
    public ?int $costCenterOwnerId = null;

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        abort_unless($this->organization instanceof Organization && $this->organization->exists, 404);

        $this->asOfDate = now()->toDateString();
        $this->ensureCanView($authorization);
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

        $this->asOfDate = $period->ends_on->toDateString();
    }

    public function updatedAsOfDate(): void
    {
        $this->periodId = null;
    }

    public function updatedUnitId(): void
    {
        if ($this->unitId === 0) {
            $this->unitId = null;
        }

        // Preserve compatibility with existing callers that still set unitId.
        $this->costCenterType = $this->unitId === null ? 'all' : 'unit';
        $this->costCenterOwnerId = $this->unitId;
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

    public function render(
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
        FinanceTrialBalanceService $trialBalance,
    ): View {
        $this->ensureCanView($authorization);

        Validator::make(['asOfDate' => $this->asOfDate], [
            'asOfDate' => ['required', 'date_format:Y-m-d'],
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

        $committees = Committee::query()
            ->where('organization_id', $this->organization->id)
            ->orderBy('name')
            ->get();

        if ($this->costCenterType === 'unit' && $this->costCenterOwnerId !== null) {
            abort_unless($units->contains('id', $this->costCenterOwnerId), 404);
        } elseif ($this->costCenterType === 'committee' && $this->costCenterOwnerId !== null) {
            abort_unless($committees->contains('id', $this->costCenterOwnerId), 404);
        } elseif ($this->costCenterType === 'organization') {
            $this->costCenterOwnerId = (int) $this->organization->id;
        }

        // A type may be selected before its owner. Until then, show all scopes.
        $effectiveType = in_array($this->costCenterType, ['unit', 'committee'], true)
            && $this->costCenterOwnerId === null ? 'all' : $this->costCenterType;

        $report = $trialBalance->generate(
            $this->organization,
            $scope,
            $this->asOfDate,
            null,
            $effectiveType,
            $effectiveType === 'all' ? null : $this->costCenterOwnerId,
        );

        return view('finance::livewire.reports.trial-balance', [
            'periods' => $periods,
            'units' => $units,
            'committees' => $committees,
            'report' => $report,
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Trial Balance',
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
