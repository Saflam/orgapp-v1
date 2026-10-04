<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Providers\CoreRoleProvider;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\PermissionRegistrationService;
use Modules\Core\Services\PermissionRegistry;
use Modules\Core\Services\RoleRegistrationService;
use Modules\Core\Services\RoleRegistry;
use Modules\Member\Providers\MemberRoleProvider;
use Tests\TestCase;

class RoleRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_register_multiple_role_providers(): void
    {
        $registry = new RoleRegistry(
            app(OrganizationModuleService::class)
        );

        $registry->registerProvider(
            new CoreRoleProvider()
        );

        $registry->registerProvider(
            new MemberRoleProvider()
        );

        $this->assertCount(
            2,
            $registry->providers()
        );
    }

    public function test_it_registers_roles_from_all_enabled_providers(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member'
        );

        $this->registerPermissions();

        $registry = new RoleRegistry(
            app(OrganizationModuleService::class)
        );

        $registry->registerProvider(
            new MemberRoleProvider()
        );

        $registry->registerRoles(
            app(RoleRegistrationService::class),
            $organization
        );

        $this->assertDatabaseHas('roles', [
            'organization_id' => $organization->id,
            'code' => 'organization_admin',
            'name' => 'Organization Administrator',
        ]);
    }

    public function test_registering_roles_multiple_times_does_not_create_duplicates(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member'
        );

        $this->registerPermissions();

        $registry = new RoleRegistry(
            app(OrganizationModuleService::class)
        );

        $registry->registerProvider(
            new MemberRoleProvider()
        );

        $registrationService = app(
            RoleRegistrationService::class
        );

        $registry->registerRoles(
            $registrationService,
            $organization
        );

        $registry->registerRoles(
            $registrationService,
            $organization
        );

        $this->assertSame(
            1,
            Role::where('organization_id', $organization->id)
                ->where('code', 'organization_admin')
                ->count()
        );
    }

    public function test_roles_are_registered_separately_for_each_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $moduleService = app(OrganizationModuleService::class);

        $moduleService->enable(
            $organizationA,
            'Member'
        );

        $moduleService->enable(
            $organizationB,
            'Member'
        );

        $this->registerPermissions();

        $registry = new RoleRegistry($moduleService);

        $registry->registerProvider(
            new MemberRoleProvider()
        );

        $registrationService = app(
            RoleRegistrationService::class
        );

        $registry->registerRoles(
            $registrationService,
            $organizationA
        );

        $registry->registerRoles(
            $registrationService,
            $organizationB
        );

        $this->assertSame(
            1,
            Role::where('organization_id', $organizationA->id)
                ->where('code', 'organization_admin')
                ->count()
        );

        $this->assertSame(
            1,
            Role::where('organization_id', $organizationB->id)
                ->where('code', 'organization_admin')
                ->count()
        );
    }

    public function test_it_does_not_register_roles_for_a_disabled_module(): void
    {
        $organization = Organization::factory()->create();

        $this->registerPermissions();

        $registry = new RoleRegistry(
            app(OrganizationModuleService::class)
        );

        $registry->registerProvider(
            new MemberRoleProvider()
        );

        $registry->registerRoles(
            app(RoleRegistrationService::class),
            $organization
        );

        $this->assertDatabaseMissing('roles', [
            'organization_id' => $organization->id,
            'code' => 'organization_admin',
        ]);
    }

    public function test_it_registers_global_roles_separately(): void
    {
        $organization = Organization::factory()->create();

        $registry = app(RoleRegistry::class);

        $registry->registerGlobalRoles(
            app(RoleRegistrationService::class)
        );

        $this->assertDatabaseHas('roles', [
            'organization_id' => null,
            'code' => 'super_admin',
            'is_system' => true,
        ]);

        $this->assertDatabaseMissing('roles', [
            'organization_id' => $organization->id,
            'code' => 'super_admin',
        ]);
    }

    public function test_organization_role_registration_does_not_register_global_roles(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationModuleService::class)->enable(
            $organization,
            'Member'
        );

        $this->registerPermissions();

        $registry = app(RoleRegistry::class);

        $registry->registerRoles(
            app(RoleRegistrationService::class),
            $organization
        );

        $this->assertDatabaseHas('roles', [
            'organization_id' => $organization->id,
            'code' => 'organization_admin',
        ]);

        $this->assertDatabaseMissing('roles', [
            'organization_id' => null,
            'code' => 'super_admin',
        ]);
    }

    private function registerPermissions(): void
    {
        app(PermissionRegistry::class)->registerPermissions(
            app(PermissionRegistrationService::class)
        );
    }
}