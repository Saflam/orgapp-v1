<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Services\RoleAssignmentService;
use Tests\TestCase;

class OrganizationMembershipRoleTest extends TestCase
{
    use RefreshDatabase;

    private RoleAssignmentService $roleAssignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAssignmentService = app(RoleAssignmentService::class);
    }

    public function test_it_can_assign_a_role_to_an_organization_membership(): void
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
            $membership->id,
            $assignment->organization_membership_id
        );

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

    public function test_it_can_retrieve_role_assignments_for_an_organization_membership(): void
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

        $this->assertCount(
            1,
            $membership->fresh()->roleAssignments
        );

        $this->assertSame(
            $assignment->id,
            $membership->fresh()->roleAssignments->first()->id
        );
    }

    public function test_a_role_can_be_assigned_to_multiple_organization_memberships(): void
    {
        $organization = Organization::factory()->create();

        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $membershipOne = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $userOne->id,
            'status' => 'active',
        ]);

        $membershipTwo = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $userTwo->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Super Admin',
            'code' => 'super_admin',
        ]);

        $assignmentOne = $this->roleAssignmentService->assign(
            $membershipOne,
            $role,
            $organization
        );

        $assignmentTwo = $this->roleAssignmentService->assign(
            $membershipTwo,
            $role,
            $organization
        );

        $this->assertNotSame(
            $assignmentOne->id,
            $assignmentTwo->id
        );

        $this->assertSame(
            $membershipOne->id,
            $assignmentOne->organization_membership_id
        );

        $this->assertSame(
            $membershipTwo->id,
            $assignmentTwo->organization_membership_id
        );

        $this->assertSame(
            $role->id,
            $assignmentOne->role_id
        );

        $this->assertSame(
            $role->id,
            $assignmentTwo->role_id
        );

        $this->assertCount(
            2,
            RoleAssignment::query()
                ->where('role_id', $role->id)
                ->get()
        );
    }

    public function test_assigning_the_same_role_and_context_returns_the_existing_assignment(): void
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

        $firstAssignment = $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $secondAssignment = $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization
        );

        $this->assertSame(
            $firstAssignment->id,
            $secondAssignment->id
        );

        $this->assertCount(
            1,
            RoleAssignment::query()
                ->where('organization_membership_id', $membership->id)
                ->where('role_id', $role->id)
                ->get()
        );
    }
}