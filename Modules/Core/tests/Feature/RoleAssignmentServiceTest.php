<?php

namespace Modules\Core\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Models\Unit;
use Modules\Core\Services\RoleAssignmentService;
use Tests\TestCase;

class RoleAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_a_role_to_an_organization_context(): void
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

        $service = app(RoleAssignmentService::class);

        $assignment = $service->assign(
            $membership,
            $role,
            $organization
        );

        $this->assertInstanceOf(
            RoleAssignment::class,
            $assignment
        );

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

    public function test_it_assigns_a_role_to_a_unit_context(): void
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

        $service = app(RoleAssignmentService::class);

        $assignment = $service->assign(
            $membership,
            $role,
            $unit
        );

        $this->assertInstanceOf(
            RoleAssignment::class,
            $assignment
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

    public function test_it_rejects_a_role_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organizationA->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organizationB->id,
            'name' => 'Unit Coordinator',
            'code' => 'unit_coordinator',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $organizationA
        );
    }

    public function test_it_rejects_a_context_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organizationA->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => $organizationA->id,
            'name' => 'Unit Coordinator',
            'code' => 'unit_coordinator',
        ]);

        $unit = Unit::create([
            'organization_id' => $organizationB->id,
            'name' => 'Other Organization Unit',
            'code' => 'other-org-unit',
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $unit
        );
    }

    public function test_assigning_the_same_role_and_context_twice_returns_the_existing_assignment(): void
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

        $service = app(RoleAssignmentService::class);

        $first = $service->assign(
            $membership,
            $role,
            $organization
        );

        $second = $service->assign(
            $membership,
            $role,
            $organization
        );

        $this->assertTrue($first->is($second));

        $this->assertDatabaseCount('role_assignments', 1);
    }

    public function test_it_can_revoke_a_role_assignment(): void
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

        $service = app(RoleAssignmentService::class);

        $assignment = $service->assign(
            $membership,
            $role,
            $organization
        );

        $assignmentId = $assignment->id;

        $service->revoke($assignment);

        $this->assertDatabaseMissing('role_assignments', [
            'id' => $assignmentId,
        ]);
    }

    public function test_it_can_assign_a_role_with_a_validity_period(): void
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
            'name' => 'Unit Secretary',
            'code' => 'unit_secretary',
        ]);

        $startsAt = now()->startOfYear();
        $endsAt = now()->endOfYear();

        $assignment = app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $organization,
            $startsAt,
            $endsAt,
        );

        $this->assertSame(
            $startsAt->toDateTimeString(),
            $assignment->starts_at->toDateTimeString()
        );

        $this->assertSame(
            $endsAt->toDateTimeString(),
            $assignment->ends_at->toDateTimeString()
        );
    }

    public function test_it_rejects_an_end_date_before_the_start_date(): void
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
            'name' => 'Unit Secretary',
            'code' => 'unit_secretary',
        ]);

        $startsAt = now()->addDay();
        $endsAt = now();

        $this->expectException(\InvalidArgumentException::class);

        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $organization,
            $startsAt,
            $endsAt,
        );
    }

    public function test_it_can_assign_a_role_without_a_validity_period(): void
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

        $assignment = app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $organization,
        );

        $this->assertNull($assignment->starts_at);
        $this->assertNull($assignment->ends_at);
    }

    public function test_it_can_assign_a_global_role_to_an_organization_membership(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'organization_id' => null,
            'name' => 'System Administrator',
            'code' => 'system_administrator',
            'is_system' => true,
        ]);

        $assignment = app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $organization,
        );

        $this->assertInstanceOf(
            RoleAssignment::class,
            $assignment
        );

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
}