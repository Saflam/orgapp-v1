<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_assign_a_permission_to_a_role(): void
    {
        $organization = Organization::factory()->create();

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $permission = Permission::create([
            'module' => 'member',
            'name' => 'View Members',
            'code' => 'members.view',
        ]);

        $role->permissions()->attach($permission);

        $this->assertCount(
            1,
            $role->fresh()->permissions
        );

        $this->assertSame(
            $permission->id,
            $role->fresh()->permissions->first()->id
        );
    }

    public function test_it_can_retrieve_roles_through_a_permission(): void
    {
        $organization = Organization::factory()->create();

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $permission = Permission::create([
            'module' => 'member',
            'name' => 'View Members',
            'code' => 'members.view',
        ]);

        $role->permissions()->attach($permission);

        $this->assertCount(
            1,
            $permission->fresh()->roles
        );

        $this->assertSame(
            $role->id,
            $permission->fresh()->roles->first()->id
        );
    }

    public function test_it_can_assign_the_same_permission_to_multiple_roles(): void
    {
        $organization = Organization::factory()->create();

        $admin = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $manager = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Organization Manager',
            'code' => 'organization_manager',
        ]);

        $permission = Permission::create([
            'module' => 'member',
            'name' => 'View Members',
            'code' => 'members.view',
        ]);

        $admin->permissions()->attach($permission);
        $manager->permissions()->attach($permission);

        $this->assertCount(
            2,
            $permission->fresh()->roles
        );
    }

    public function test_it_cannot_assign_the_same_permission_to_a_role_twice(): void
    {
        $organization = Organization::factory()->create();

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $permission = Permission::create([
            'module' => 'member',
            'name' => 'View Members',
            'code' => 'members.view',
        ]);

        $role->permissions()->attach($permission);

        $this->expectException(QueryException::class);

        $role->permissions()->attach($permission);
    }
}