<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\OrganizationRoleProvisioningService;
use Modules\Core\Services\PermissionRegistrationService;
use Modules\Core\Services\PermissionRegistry;
use Tests\TestCase;

class OrganizationRoleProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_registered_roles_for_an_organization(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member'
        );

        app(PermissionRegistry::class)->registerPermissions(
            app(PermissionRegistrationService::class)
        );

        $service = app(
            OrganizationRoleProvisioningService::class
        );

        $service->provision($organization);

        $this->assertDatabaseHas('roles', [
            'organization_id' => $organization->id,
            'code' => 'organization_admin',
            'name' => 'Organization Administrator',
        ]);
    }

    public function test_it_does_not_create_duplicate_roles_when_provisioned_twice(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member'
        );

        app(PermissionRegistry::class)->registerPermissions(
            app(PermissionRegistrationService::class)
        );

        $service = app(
            OrganizationRoleProvisioningService::class
        );

        $service->provision($organization);
        $service->provision($organization);

        $this->assertSame(
            1,
            Role::where('organization_id', $organization->id)
                ->where('code', 'organization_admin')
                ->count()
        );
    }
}