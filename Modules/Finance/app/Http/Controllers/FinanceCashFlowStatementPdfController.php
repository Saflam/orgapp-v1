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
use Modules\Finance\Services\FinanceCashFlowStatementService;
use Modules\Finance\Services\FinanceScopeService;
use Symfony\Component\HttpFoundation\Response;

class FinanceCashFlowStatementPdfController
{
    public function __invoke(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
        FinanceCashFlowStatementService $cashFlow,
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
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'unit_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        $validated = $validator->validated();
        $scope = $scopes->organizationScope($organization);
        $unitId = isset($validated['unit_id']) ? (int) $validated['unit_id'] : null;

        $unitName = 'All Units';

        if ($unitId !== null) {
            $unit = Unit::query()
                ->where('organization_id', $organization->id)
                ->findOrFail($unitId);

            $unitName = $unit->name;
        }

        $report = $cashFlow->generate(
            $organization,
            $scope,
            $validated['start_date'],
            $validated['end_date'],
            $unitId,
        );

        $pdf = Pdf::loadView('finance::livewire.reports.cash-flow-statement-pdf', [
            'organization' => $organization,
            'report' => $report,
            'unitName' => $unitName,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            'cash-flow-statement-' . $validated['start_date'] . '-to-' . $validated['end_date'] . '.pdf'
        );
    }
}
