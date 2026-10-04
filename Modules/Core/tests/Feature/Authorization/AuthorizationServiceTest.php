<?php

namespace Modules\Core\Tests\Feature\Authorization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\RoleAssignmentService;
use Tests\TestCase;

class AuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthorizationService $authorization;
    private RoleAssignmentService $roleAssignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationService::class);
        $this->roleAssignmentService = app(RoleAssignmentService::class);
    }

    public function test_it_allows_a_user_with_an_organization_scoped_permission(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $allowed = $this->authorization->can(
            $user,
            'members.view',
            new AuthorizationContext($organization->id)
        );

        $this->assertTrue($allowed);
    }

    public function test_it_denies_a_user_without_an_organization_membership(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $this->createPermission('members.view');

        $allowed = $this->authorization->can(
            $user,
            'members.view',
            new AuthorizationContext($organization->id)
        );

        $this->assertFalse($allowed);
    }

    public function test_it_denies_an_inactive_organization_membership(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user,
            'inactive'
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $allowed = $this->authorization->can(
            $user,
            'members.view',
            new AuthorizationContext($organization->id)
        );

        $this->assertFalse($allowed);
    }

    public function test_it_denies_a_role_without_the_requested_permission(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $allowed = $this->authorization->can(
            $user,
            'members.update',
            new AuthorizationContext($organization->id)
        );

        $this->assertFalse($allowed);
    }

    public function test_it_denies_access_to_another_organization(): void
    {
        $organization = Organization::factory()->create();

        $otherOrganization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $allowed = $this->authorization->can(
            $user,
            'members.view',
            new AuthorizationContext($otherOrganization->id)
        );

        $this->assertFalse($allowed);
    }

    public function test_an_organization_scoped_role_can_access_any_unit_in_its_organization(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $allowed = $this->authorization->can(
            $user,
            'members.view',
            new AuthorizationContext(
                $organization->id,
                $unit->id
            )
        );

        $this->assertTrue($allowed);
    }

    public function test_a_unit_scoped_role_can_access_its_assigned_unit(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $unit
        );

        $allowed = $this->authorization->can(
            $user,
            'members.view',
            new AuthorizationContext(
                $organization->id,
                $unit->id
            )
        );

        $this->assertTrue($allowed);
    }

    public function test_a_unit_scoped_role_cannot_access_another_unit(): void
    {
        $organization = Organization::factory()->create();

        $assignedUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $otherUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $assignedUnit
        );

        $allowed = $this->authorization->can(
            $user,
            'members.view',
            new AuthorizationContext(
                $organization->id,
                $otherUnit->id
            )
        );

        $this->assertFalse($allowed);
    }

    public function test_it_allows_access_when_role_has_the_permission(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $this->assertTrue(
            $this->authorization->can(
                $user,
                'members.view',
                new AuthorizationContext($organization->id)
            )
        );
    }

    public function test_it_denies_access_when_role_does_not_have_the_permission(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $role = $this->createRole($organization);

        $permission = $this->createPermission('members.update');

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $this->assertFalse(
            $this->authorization->can(
                $user,
                'members.view',
                new AuthorizationContext($organization->id)
            )
        );
    }

    public function test_it_denies_access_when_role_assignment_has_expired(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $unit,
            now()->subYear(),
            now()->subDay(),
        );

        $allowed = $this->authorization->can(
            $user,
            'members.view',
            new AuthorizationContext(
                $organization->id,
                $unit->id
            )
        );

        $this->assertFalse($allowed);
    }

    public function test_a_role_belonging_to_another_organization_is_rejected(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organizationA->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $permission = Permission::create([
            'module' => 'members',
            'name' => 'View Members',
            'code' => 'members.view',
            'description' => null,
        ]);

        $role = Role::create([
            'organization_id' => $organizationB->id,
            'name' => 'Organization B Administrator',
            'code' => 'organization_b_admin',
            'description' => null,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);

        RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organizationA->id,
        ]);

        $context = new AuthorizationContext(
            organizationId: $organizationA->id,
        );

        $service = app(AuthorizationService::class);

        $this->assertFalse(
            $service->can(
                $user,
                'members.view',
                $context,
            )
        );
    }

    private function createMembership(
        Organization $organization,
        User $user,
        string $status = 'active',
    ): OrganizationMembership {
        return OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    private function createPermission(string $code): Permission
    {
        return Permission::create([
            'module' => 'member',
            'name' => str($code)->headline(),
            'code' => $code,
        ]);
    }

    private function createRole(
        Organization $organization
    ): Role {
        return Role::create([
            'organization_id' => $organization->id,
            'name' => 'Test Administrator',
            'code' => 'test_admin_' . uniqid(),
        ]);
    }
}