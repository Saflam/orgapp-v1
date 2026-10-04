<?php

namespace Modules\Core\Tests\Feature\Authorization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\Unit;
use Modules\Core\Services\RoleAssignmentService;
use Tests\TestCase;

class AuthorizationContextAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private RoleAssignmentService $roleAssignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAssignmentService = app(RoleAssignmentService::class);
    }

    public function test_a_role_can_be_assigned_with_an_organization_context(): void
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
            'name' => 'Central Committee President',
            'code' => 'central_committee_president',
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

    public function test_a_role_can_be_assigned_with_a_unit_context(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::create([
            'organization_id' => $organization->id,
            'name' => 'Aluva Unit',
            'code' => 'aluva',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Unit Coordinator',
            'code' => 'unit_coordinator',
        ]);

        $assignment = $this->roleAssignmentService->assign(
            $membership,
            $role,
            $unit
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
            'unit',
            $assignment->context_type
        );

        $this->assertSame(
            $unit->id,
            $assignment->context_id
        );
    }

    public function test_the_same_role_can_be_assigned_to_different_contexts(): void
    {
        $organization = Organization::factory()->create();

        $unitA = Unit::create([
            'organization_id' => $organization->id,
            'name' => 'Aluva Unit',
            'code' => 'aluva',
            'is_active' => true,
        ]);

        $unitB = Unit::create([
            'organization_id' => $organization->id,
            'name' => 'Cochin Unit',
            'code' => 'cochin',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Unit Coordinator',
            'code' => 'unit_coordinator',
        ]);

        $assignmentA = $this->roleAssignmentService->assign(
            $membership,
            $role,
            $unitA
        );

        $assignmentB = $this->roleAssignmentService->assign(
            $membership,
            $role,
            $unitB
        );

        $this->assertNotSame(
            $assignmentA->id,
            $assignmentB->id
        );

        $this->assertSame(
            $unitA->id,
            $assignmentA->context_id
        );

        $this->assertSame(
            $unitB->id,
            $assignmentB->context_id
        );

        $this->assertCount(
            2,
            $membership->fresh()->roleAssignments
        );
    }

    public function test_a_member_can_have_roles_assigned_to_different_contexts(): void
    {
        $organization = Organization::factory()->create();

        $unit = Unit::create([
            'organization_id' => $organization->id,
            'name' => 'Aluva Unit',
            'code' => 'aluva',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $organizationRole = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Central Committee President',
            'code' => 'central_committee_president',
        ]);

        $unitRole = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Unit Coordinator',
            'code' => 'unit_coordinator',
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

        $this->assertNotNull(
            $assignments->firstWhere(
                'id',
                $organizationAssignment->id
            )
        );

        $this->assertNotNull(
            $assignments->firstWhere(
                'id',
                $unitAssignment->id
            )
        );
    }
}