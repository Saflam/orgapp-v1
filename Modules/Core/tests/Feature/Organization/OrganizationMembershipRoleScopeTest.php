<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\Unit;
use Modules\Core\Services\RoleAssignmentService;
use Tests\TestCase;

class OrganizationMembershipRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    private RoleAssignmentService $roleAssignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAssignmentService = app(RoleAssignmentService::class);
    }

    public function test_it_can_assign_an_organization_scoped_role(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $assignment = $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $this->assertNotNull($assignment);

        $this->assertSame(
            $role->id,
            $assignment->role_id
        );

        $this->assertSame(
            'organization',
            $assignment->context_type
        );

        $this->assertSame(
            $organization->id,
            $assignment->context_id
        );
    }

    public function test_it_can_assign_a_unit_scoped_role(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $assignment = $this->roleAssignmentService->assign(
            $membership,
            $role,
            $unit
        );

        $this->assertNotNull($assignment);

        $this->assertSame(
            $role->id,
            $assignment->role_id
        );

        $this->assertSame(
            'unit',
            $assignment->context_type
        );

        $this->assertSame(
            $unit->id,
            $assignment->context_id
        );
    }

    public function test_it_can_assign_roles_with_different_scopes(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $organizationRole = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $unitRole = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Unit Administrator',
            'code' => 'unit_admin',
        ]);

        $organizationAssignment = $this->roleAssignmentService->assign(
            $membership,
            $organizationRole,
            $organization
        );

        $unitAssignment = $this->roleAssignmentService->assign(
            $membership,
            $unitRole,
            $unit
        );

        $assignments = $membership->fresh()->roleAssignments;

        $this->assertCount(2, $assignments);

        $this->assertSame(
            $organizationAssignment->id,
            $assignments->first()->id
        );

        $this->assertSame(
            $unitAssignment->id,
            $assignments->last()->id
        );

        $this->assertSame(
            'organization',
            $organizationAssignment->context_type
        );

        $this->assertSame(
            $organization->id,
            $organizationAssignment->context_id
        );

        $this->assertSame(
            'unit',
            $unitAssignment->context_type
        );

        $this->assertSame(
            $unit->id,
            $unitAssignment->context_id
        );
    }

    public function test_organization_scoped_role_does_not_require_a_unit(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $assignment = $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $this->assertSame(
            'organization',
            $assignment->context_type
        );

        $this->assertSame(
            $organization->id,
            $assignment->context_id
        );
    }
}