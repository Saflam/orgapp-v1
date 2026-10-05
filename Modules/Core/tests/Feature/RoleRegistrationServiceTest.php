<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\RoleProvider;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\RoleRegistrationService;
use Modules\Member\Providers\MemberPermissionProvider;
use Modules\Member\Providers\MemberRoleProvider;
use Tests\TestCase;

class RoleRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_roles_from_a_provider(): void
    {
        $this->registerMemberPermissions();

        $organization = Organization::factory()->create();

        $service = app(RoleRegistrationService::class);

        $service->register(
            $organization,
            new MemberRoleProvider()
        );

        $this->assertDatabaseHas('roles', [
            'organization_id' => $organization->id,
            'name' => 'Organization Administrator',
            'code' => 'organization_admin',
        ]);
    }

    public function test_it_updates_an_existing_role(): void
    {
        $this->registerMemberPermissions();

        $organization = Organization::factory()->create();

        $service = app(RoleRegistrationService::class);

        $service->register(
            $organization,
            new MemberRoleProvider()
        );

        $service->register(
            $organization,
            new MemberRoleProvider()
        );

        $this->assertSame(
            1,
            Role::where('organization_id', $organization->id)
                ->where('code', 'organization_admin')
                ->count()
        );
    }

    public function test_it_registers_role_permissions(): void
    {
        $organization = Organization::factory()->create();

        Permission::create([
            'module' => 'member',
            'name' => 'View Members',
            'code' => 'members.view',
        ]);

        Permission::create([
            'module' => 'member',
            'name' => 'Update Members',
            'code' => 'members.update',
        ]);

        $provider = new class implements RoleProvider {
            public function module(): ?string
            {
                return 'Member';
            }

            public function roles(): array
            {
                return [
                    [
                        'name' => 'Organization Administrator',
                        'code' => 'organization_admin',
                        'description' => null,
                        'permissions' => [
                            'members.view',
                            'members.update',
                        ],
                    ],
                ];
            }
        };

        app(RoleRegistrationService::class)->register(
            $organization,
            $provider,
        );

        $role = Role::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'organization_admin')
            ->firstOrFail();

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => Permission::where(
                'code',
                'members.view'
            )->value('id'),
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => Permission::where(
                'code',
                'members.update'
            )->value('id'),
        ]);
    }

    public function test_it_rejects_an_unregistered_role_permission(): void
    {
        $organization = Organization::factory()->create();

        $provider = new class implements RoleProvider {
            public function module(): ?string
            {
                return 'Member';
            }

            public function roles(): array
            {
                return [
                    [
                        'name' => 'Organization Administrator',
                        'code' => 'organization_admin',
                        'description' => null,
                        'permissions' => [
                            'members.this_permission_does_not_exist',
                        ],
                    ],
                ];
            }
        };

        $this->expectException(\DomainException::class);

        app(RoleRegistrationService::class)->register(
            $organization,
            $provider,
        );
    }

    public function test_core_role_is_registered_as_a_global_system_role(): void
    {
        $organization = Organization::factory()->create();

        $service = app(RoleRegistrationService::class);

        $service->registerGlobal(
            new \Modules\Core\Providers\CoreRoleProvider()
        );

        $this->assertDatabaseHas('roles', [
            'organization_id' => null,
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);

        $this->assertDatabaseMissing('roles', [
            'organization_id' => $organization->id,
            'code' => 'super_admin',
        ]);
    }

    public function test_global_role_provider_cannot_be_registered_as_an_organization_role(): void
    {
        $organization = Organization::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        app(RoleRegistrationService::class)->register(
            $organization,
            new \Modules\Core\Providers\CoreRoleProvider()
        );
    }

    public function test_organization_role_provider_cannot_be_registered_as_a_global_role(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(RoleRegistrationService::class)->registerGlobal(
            new \Modules\Member\Providers\MemberRoleProvider()
        );
    }

    private function registerMemberPermissions(): void
    {
        foreach ((new MemberPermissionProvider())->permissions() as $permission) {
            Permission::create($permission);
        }
    }
}