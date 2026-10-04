<?php

namespace Modules\Committee\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Committee\Http\Requests\CreateDesignationRequest;
use Modules\Committee\Http\Requests\UpdateDesignationRequest;
use Modules\Committee\Models\Designation;
use Modules\Committee\Services\DesignationService;
use Modules\Committee\Transformers\DesignationResource;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;

class DesignationController extends Controller
{
    public function index(
        Organization $organization,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled(
            $organization,
            $moduleService,
        );

        $designations = Designation::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get();

        return DesignationResource::collection($designations)
            ->response();
    }

    public function show(
        Organization $organization,
        Designation $designation,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled(
            $organization,
            $moduleService,
        );

        abort_unless(
            $designation->organization_id === $organization->id,
            404,
            'Designation not found.'
        );

        return (new DesignationResource($designation->load('permissions')))
            ->response();
    }

    public function store(
        CreateDesignationRequest $request,
        Organization $organization,
        DesignationService $designationService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled(
            $organization,
            $moduleService,
        );

        $designation = $designationService->create(
            organizationId: $organization->id,
            name: $request->string('name')->toString(),
            code: $request->string('code')->toString(),
            description: $request->input('description'),
        );

        return (new DesignationResource($designation->load('permissions')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateDesignationRequest $request,
        Organization $organization,
        Designation $designation,
        DesignationService $designationService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled(
            $organization,
            $moduleService,
        );

        abort_unless(
            $designation->organization_id === $organization->id,
            404,
            'Designation not found.'
        );

        $designation = $designationService->update(
            designation: $designation,
            name: $request->string('name')->toString(),
            description: $request->input('description'),
        );

        return (new DesignationResource($designation->load('permissions')))
            ->response()
            ->setStatusCode(200);
    }

    public function activate(
        Organization $organization,
        Designation $designation,
        DesignationService $designationService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled(
            $organization,
            $moduleService,
        );

        abort_unless(
            $designation->organization_id === $organization->id,
            404,
            'Designation not found.'
        );

        $designation = $designationService->activate($designation);

        return (new DesignationResource($designation->load('permissions')))
            ->response()
            ->setStatusCode(200);
    }

    public function deactivate(
        Organization $organization,
        Designation $designation,
        DesignationService $designationService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled(
            $organization,
            $moduleService,
        );

        abort_unless(
            $designation->organization_id === $organization->id,
            404,
            'Designation not found.'
        );

        $designation = $designationService->deactivate($designation);

        return (new DesignationResource($designation->load('permissions')))
            ->response()
            ->setStatusCode(200);
    }

    private function ensureCommitteeModuleEnabled(
        Organization $organization,
        OrganizationModuleService $moduleService,
    ): void {
        if (! $moduleService->isEnabled(
            $organization,
            'Committee',
        )) {
            abort(
                403,
                'The Committee module is not enabled for this organization.'
            );
        }
    }
}