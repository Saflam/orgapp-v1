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

        if ($this->unitId !== null) {
            abort_unless($units->contains('id', $this->unitId), 404);
        }

        $report = $trialBalance->generate(
            $this->organization,
            $scope,
            $this->asOfDate,
            $this->unitId,
        );

        return view('finance::livewire.reports.trial-balance', [
            'periods' => $periods,
            'units' => $units,
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
