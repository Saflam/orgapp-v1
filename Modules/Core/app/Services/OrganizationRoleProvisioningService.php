<?php

namespace Modules\Core\Services;

use Modules\Core\Models\Organization;

class OrganizationRoleProvisioningService
{
    public function provision(Organization $organization): void
    {
        app(RoleRegistry::class)->registerRoles(
            app(RoleRegistrationService::class),
            $organization
        );
    }
}