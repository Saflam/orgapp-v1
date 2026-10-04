<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\RoleProvider;
use Modules\Core\Models\Organization;

class RoleRegistry
{
    /**
     * @var array<int, RoleProvider>
     */
    private array $providers = [];

    public function __construct(
        private OrganizationModuleService $organizationModuleService,
    ) {
    }

    public function registerProvider(
        RoleProvider $provider
    ): void {
        if ($this->hasProvider($provider::class)) {
            return;
        }

        $this->providers[] = $provider;
    }

    /**
     * @return array<int, RoleProvider>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    public function registerRoles(
        RoleRegistrationService $registrationService,
        Organization $organization,
    ): void {
        foreach ($this->providers as $provider) {
            $module = $provider->module();

            if ($module === null) {
                continue;
            }

            if (! $this->organizationModuleService->isEnabled(
                $organization,
                $module
            )) {
                continue;
            }

            $registrationService->register(
                $organization,
                $provider
            );
        }
    }

    public function registerGlobalRoles(
        RoleRegistrationService $registrationService
    ): void {
        foreach ($this->providers as $provider) {
            if ($provider->module() !== null) {
                continue;
            }

            $registrationService->registerGlobal($provider);
        }
    }

    private function hasProvider(string $providerClass): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider::class === $providerClass) {
                return true;
            }
        }

        return false;
    }
}