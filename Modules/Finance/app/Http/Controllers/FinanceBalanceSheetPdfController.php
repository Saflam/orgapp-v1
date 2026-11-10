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
use Modules\Finance\Services\FinanceBalanceSheetService;
use Modules\Finance\Services\FinanceScopeService;
use Symfony\Component\HttpFoundation\Response;

class FinanceBalanceSheetPdfController
{
    public function __invoke(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
        FinanceBalanceSheetService $balanceSheet,
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

        $report = $balanceSheet->generate(
            $organization,
            $scope,
            $validated['as_of_date'],
            $unitId,
        );

        $pdf = Pdf::loadView('finance::livewire.reports.balance-sheet-pdf', [
            'organization' => $organization,
            'report' => $report,
            'unitName' => $unitName,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('balance-sheet-' . $validated['as_of_date'] . '.pdf');
    }
}
