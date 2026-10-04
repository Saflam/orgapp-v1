<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\PermissionProvider;

class PermissionRegistry
{
    /**
     * @var array<int, PermissionProvider>
     */
    private array $providers = [];

    public function registerProvider(
        PermissionProvider $provider
    ): void {
        $this->providers[] = $provider;
    }

    /**
     * @return array<int, PermissionProvider>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    public function registerPermissions(
        PermissionRegistrationService $registrationService
    ): void {
        foreach ($this->providers as $provider) {
            $registrationService->register($provider);
        }
    }
}