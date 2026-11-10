<?php

namespace Modules\Finance\Http\Controllers;

use App\Support\Organization\CurrentOrganization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Finance\Models\FinancePeriod;
use Modules\Finance\Services\FinanceScopeService;
use Modules\Finance\Services\FinanceTrialBalanceService;
use Symfony\Component\HttpFoundation\Response;

class FinanceTrialBalancePdfController
{
    public function __invoke(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
        FinanceTrialBalanceService $trialBalance,
    ): Response {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization && $organization->exists, 404);

        $user = $request->user();
        abort_unless(
            $user !== null && $authorization->can(
                $user,
                'finance.report.view',
                new AuthorizationContext((int) $organization->id),
            ),
            403,
        );

        $validator = Validator::make($request->query(), [
            'as_of_date' => ['required', 'date_format:Y-m-d'],
            'unit_id' => ['nullable', 'integer'],
            'period_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        $validated = $validator->validated();

        $scope = $scopes->organizationScope($organization);

        if (! empty($validated['period_id'])) {
            $period = FinancePeriod::query()
                ->where('organization_id', $organization->id)
                ->where('finance_scope_id', $scope->id)
                ->findOrFail($validated['period_id']);

            abort_unless($period->ends_on->toDateString() === $validated['as_of_date'], 422);
        }

        $unitId = isset($validated['unit_id']) ? (int) $validated['unit_id'] : null;

        if ($unitId !== null) {
            Unit::query()
                ->where('organization_id', $organization->id)
                ->findOrFail($unitId);
        }

        $report = $trialBalance->generate(
            $organization,
            $scope,
            $validated['as_of_date'],
            $unitId,
        );

        $unitName = $unitId === null
            ? 'All Units'
            : Unit::query()->where('organization_id', $organization->id)->findOrFail($unitId)->name;

        $pdf = Pdf::loadView('finance::livewire.reports.trial-balance-pdf', [
            'organization' => $organization,
            'report' => $report,
            'unitName' => $unitName,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('trial-balance-' . $validated['as_of_date'] . '.pdf');
    }
}
