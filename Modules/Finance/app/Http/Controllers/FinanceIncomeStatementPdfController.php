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
use Modules\Finance\Services\FinanceIncomeStatementService;
use Modules\Finance\Services\FinanceScopeService;
use Symfony\Component\HttpFoundation\Response;

class FinanceIncomeStatementPdfController
{
    public function __invoke(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        FinanceScopeService $scopes,
        FinanceIncomeStatementService $incomeStatement,
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
            'cost_center_type' => ['nullable', 'in:all,organization,unit,committee'],
            'cost_center_owner_id' => ['nullable', 'integer', 'min:1'],
            // Backward compatibility for existing PDF links.
            'unit_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        $validated = $validator->validated();

        $costCenterType = $validated['cost_center_type']
            ?? (! empty($validated['unit_id']) ? 'unit' : 'all');

        $ownerId = isset($validated['cost_center_owner_id'])
            ? (int) $validated['cost_center_owner_id']
            : (isset($validated['unit_id']) ? (int) $validated['unit_id'] : null);

        if ($costCenterType === 'organization') {
            $ownerId = (int) $organization->id;
        } elseif ($costCenterType === 'unit') {
            abort_if($ownerId === null, 404);

            $costCenterName = Unit::query()
                ->where('organization_id', $organization->id)
                ->whereKey($ownerId)
                ->value('name');

            abort_if($costCenterName === null, 404);
        } elseif ($costCenterType === 'committee') {
            abort_if($ownerId === null || ! Schema::hasTable('committees'), 404);

            $costCenterName = Committee::query()
                ->where('organization_id', $organization->id)
                ->whereKey($ownerId)
                ->value('name');

            abort_if($costCenterName === null, 404);
        } else {
            $ownerId = null;
        }

        $costCenterName ??= match ($costCenterType) {
            'organization' => 'Central / Organization',
            'unit', 'committee' => '',
            default => 'All Cost Centres (All Units)',
        };

        $scope = $scopes->organizationScope($organization);

        $report = $incomeStatement->generate(
            $organization,
            $scope,
            $validated['start_date'],
            $validated['end_date'],
            null,
            $costCenterType,
            $ownerId,
        );

        $pdf = Pdf::loadView('finance::livewire.reports.income-statement-pdf', [
            'organization' => $organization,
            'report' => $report,
            'costCenterName' => $costCenterName,
            // Retain the old view variable for compatibility with custom overrides.
            'unitName' => $costCenterName,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            'income-statement-' . $validated['start_date'] . '-to-' . $validated['end_date'] . '.pdf'
        );
    }
}
