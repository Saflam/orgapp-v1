<?php

namespace Modules\Finance\Http\Controllers;

use App\Support\Organization\CurrentOrganization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Modules\Committee\Models\Committee;
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
            // unit_id remains supported for existing PDF links.
            'unit_id' => ['nullable', 'integer'],
            'period_id' => ['nullable', 'integer'],
            'cost_center_type' => ['nullable', 'string', 'in:all,organization,unit,committee'],
            'cost_center_id' => ['nullable', 'integer'],
            'cost_center_owner_id' => ['nullable', 'integer', 'min:1'],
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

        // New cost-center parameters take precedence. Older unit_id URLs are
        // translated to the equivalent unit cost-center selection.
        $costCenterType = $validated['cost_center_type']
            ?? (isset($validated['unit_id']) ? 'unit' : 'all');

        $costCenterOwnerId = isset($validated['cost_center_owner_id'])
            ? (int) $validated['cost_center_owner_id']
            : (isset($validated['cost_center_id'])
                ? (int) $validated['cost_center_id']
                : (isset($validated['unit_id']) ? (int) $validated['unit_id'] : null));

        $costCenterName = match ($costCenterType) {
            'all' => 'All Cost Centres (All Units)',
            'organization' => $this->resolveOrganizationCostCenter($organization, $costCenterOwnerId),
            'unit' => $this->resolveUnitCostCenter($organization, $costCenterOwnerId),
            'committee' => $this->resolveCommitteeCostCenter($organization, $costCenterOwnerId),
        };

        $report = $trialBalance->generate(
            $organization,
            $scope,
            $validated['as_of_date'],
            null,
            $costCenterType,
            $costCenterOwnerId,
        );

        $pdf = Pdf::loadView('finance::livewire.reports.trial-balance-pdf', [
            'organization' => $organization,
            'report' => $report,
            'costCenterName' => $costCenterName,
            // Retain the old variable for any downstream customized PDF view.
            'unitName' => $costCenterName,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('trial-balance-' . $validated['as_of_date'] . '.pdf');
    }

    private function resolveOrganizationCostCenter(Organization $organization, ?int $ownerId): string
    {
        abort_if($ownerId !== null && $ownerId !== (int) $organization->id, 404);

        return 'Central / Organization';
    }

    private function resolveUnitCostCenter(Organization $organization, ?int $ownerId): string
    {
        abort_if($ownerId === null, 422);

        return Unit::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($ownerId)
            ->name;
    }

    private function resolveCommitteeCostCenter(Organization $organization, ?int $ownerId): string
    {
        abort_if($ownerId === null, 422);

        abort_unless(Schema::hasTable('committees'), 404);

        return Committee::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($ownerId)
            ->name;
    }
}
