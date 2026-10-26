<?php

namespace Modules\Member\Tests\Concerns;

use App\Models\OrganizationMembership;
use App\Models\User;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\OrganizationRoleProvisioningService;
use Modules\Core\Services\PermissionRegistrationService;
use Modules\Core\Services\PermissionRegistry;
use Modules\Core\Services\RoleAssignmentService;

trait ActsAsMemberOrganizationUser
{
    protected function actAsMemberOrganizationAdmin(
        Organization $organization,
        ?User $user = null,
    ): void {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $user ??= User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member',
        );

        app(PermissionRegistry::class)->registerPermissions(
            app(PermissionRegistrationService::class),
        );

        app(OrganizationRoleProvisioningService::class)->provision(
            $organization,
        );

        $role = Role::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'organization_admin')
            ->firstOrFail();

        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $organization,
        );

        $this->actingAs($user);
    }

    protected function organizationUrl(
        Organization $organization,
        string $path,
    ): string {
        return 'http://'
            . $organization->slug
            . '.example.test'
            . $path;
    }
}