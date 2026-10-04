<?php

namespace Modules\Core\Services;

class AuthorizationDefinitionProvisioningService
{
    public function provision(): void
    {
        $permissionRegistry = app(PermissionRegistry::class);

        $permissionRegistry->registerPermissions(
            app(PermissionRegistrationService::class)
        );

        $roleRegistry = app(RoleRegistry::class);

        $roleRegistry->registerGlobalRoles(
            app(RoleRegistrationService::class)
        );
    }
}