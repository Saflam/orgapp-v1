<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;

class OrganizationService
{
    public function __construct(
        private readonly OrganizationModuleService $moduleService,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly SuperAdminProvisioningService $superAdminProvisioningService,
    ) {
    }

    public function create(array $data): Organization
    {
        return DB::transaction(function () use ($data) {
            $this->ensureSuperAdminEmailIsAvailable(
                $data['super_admin']['email']
            );

            $organization = Organization::create(
                $data['organization']
            );

            $this->initializeModules($organization);

            $this->superAdminProvisioningService->provision(
                $organization,
                $data['super_admin'],
            );

            return $organization->fresh();
        });
    }

    public function activate(Organization $organization): Organization
    {
        $organization->update([
            'is_active' => true,
        ]);

        return $organization->fresh();
    }

    public function deactivate(Organization $organization): Organization
    {
        $organization->update([
            'is_active' => false,
        ]);

        return $organization->fresh();
    }

    private function ensureSuperAdminEmailIsAvailable(string $email): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'super_admin.email' =>
                    'A user with this email address already exists.',
            ]);
        }
    }

    private function initializeModules(Organization $organization): void
    {
        foreach ($this->moduleRegistry->enabledNames() as $module) {
            if ($this->moduleService->isRequired($module)) {
                $this->moduleService->enable(
                    $organization,
                    $module,
                );

                continue;
            }

            $this->moduleService->disable(
                $organization,
                $module,
            );
        }
    }
}