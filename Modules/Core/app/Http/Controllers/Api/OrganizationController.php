<?php

namespace Modules\Core\Http\Controllers\Api;

use App\Models\User;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Requests\Api\CreateOrganizationRequest;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\OrganizationService;
use Modules\Core\Services\SuperAdminService;

class OrganizationController
{
    public function show(
        CurrentOrganization $currentOrganization,
    ): JsonResponse {
        $organization = $currentOrganization->get();

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
        ]);
    }

    public function store(
        CreateOrganizationRequest $request,
        OrganizationService $organizationService,
    ): JsonResponse {
        $organization = $organizationService->create(
            $request->validated()
        );

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'code' => $organization->code,
                'slug' => $organization->slug,
            ],
        ], 201);
    }

    public function activate(
        Organization $organization,
        OrganizationService $organizationService,
    ): JsonResponse {
        $organization = $organizationService->activate(
            $organization
        );

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'code' => $organization->code,
                'slug' => $organization->slug,
                'is_active' => $organization->is_active,
            ],
        ]);
    }

    public function deactivate(
        Organization $organization,
        OrganizationService $organizationService,
    ): JsonResponse {
        $organization = $organizationService->deactivate(
            $organization
        );

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'code' => $organization->code,
                'slug' => $organization->slug,
                'is_active' => $organization->is_active,
            ],
        ]);
    }

    public function replaceSuperAdmin(
        Organization $organization,
        Request $request,
        SuperAdminService $superAdminService,
    ): JsonResponse {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $user = User::findOrFail($validated['user_id']);

        $superAdminService->replace(
            $organization,
            $user,
        );

        return response()->json([
            'message' => 'Organization Super Admin replaced successfully.',
        ]);
    }

    public function removeSuperAdmin(
        Organization $organization,
        SuperAdminService $superAdminService,
    ): JsonResponse {
        $superAdminService->remove($organization);

        return response()->json([
            'message' => 'Organization Super Admin removed successfully.',
        ]);
    }

    public function modules(
        Organization $organization,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        return response()->json([
            'modules' => $moduleService->statuses($organization),
        ]);
    }

    public function enableModule(
        Organization $organization,
        string $module,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $moduleService->enable(
            $organization,
            $module,
        );

        return response()->json([
            'message' => 'Organization module enabled successfully.',
            'module' => [
                'module' => $module,
                'is_enabled' => true,
            ],
        ]);
    }

    public function disableModule(
        Organization $organization,
        string $module,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $moduleService->disable(
            $organization,
            $module,
        );

        return response()->json([
            'message' => 'Organization module disabled successfully.',
            'module' => [
                'module' => $module,
                'is_enabled' => false,
            ],
        ]);
    }
}