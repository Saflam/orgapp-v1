<?php

namespace Modules\Core\Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Models\Unit;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_an_organization_context_assignment(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);

        $this->assertDatabaseHas('role_assignments', [
            'id' => $assignment->id,
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);
    }

    public function test_it_can_create_a_unit_context_assignment(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'unit',
            'context_id' => $unit->id,
        ]);

        $this->assertDatabaseHas('role_assignments', [
            'id' => $assignment->id,
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'unit',
            'context_id' => $unit->id,
        ]);
    }

    public function test_the_same_role_can_be_assigned_to_multiple_contexts(): void
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

        RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'unit',
            'context_id' => $unitA->id,
        ]);

        RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'unit',
            'context_id' => $unitB->id,
        ]);

        $this->assertDatabaseCount('role_assignments', 2);
    }

    public function test_duplicate_role_assignment_for_the_same_context_is_not_allowed(): void
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

        $attributes = [
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ];

        RoleAssignment::create($attributes);

        $this->expectException(
            \Illuminate\Database\UniqueConstraintViolationException::class
        );

        RoleAssignment::create($attributes);
    }

    public function test_role_assignment_belongs_to_membership_and_role(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);

        $this->assertTrue(
            $assignment->organizationMembership->is($membership)
        );

        $this->assertTrue(
            $assignment->role->is($role)
        );
    }

    public function test_membership_can_access_its_role_assignments(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);

        $this->assertTrue(
            $membership->roleAssignments
                ->contains($assignment)
        );
    }

    public function test_role_can_access_its_assignments(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);

        $this->assertTrue(
            $role->assignments
                ->contains($assignment)
        );
    }

    public function test_assignment_without_validity_dates_is_active(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);

        $this->assertTrue($assignment->isActive());
    }

    public function test_future_assignment_is_not_active(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->addDay(),
        ]);

        $this->assertFalse($assignment->isActive());
    }

    public function test_expired_assignment_is_not_active(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'ends_at' => now()->subSecond(),
        ]);

        $this->assertFalse($assignment->isActive());
    }

    public function test_assignment_is_active_during_term_and_inactive_after_term(): void
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

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'unit',
            'context_id' => 1,
            'starts_at' => '2026-01-01 00:00:00',
            'ends_at' => '2026-12-31 23:59:59',
        ]);

        $this->assertTrue(
            $assignment->isActive(
                \Carbon\Carbon::parse('2026-12-31 12:00:00')
            )
        );

        $this->assertFalse(
            $assignment->isActive(
                \Carbon\Carbon::parse('2027-01-01 00:00:00')
            )
        );
    }

    public function test_active_assignments_can_be_retrieved(): void
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

        RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id + 100,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);

        RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id + 200,
            'starts_at' => now()->addDay(),
            'ends_at' => null,
        ]);

        $activeAssignments = RoleAssignment::query()
            ->where('organization_membership_id', $membership->id)
            ->get()
            ->filter(fn (RoleAssignment $assignment) => $assignment->isActive());

        $this->assertCount(1, $activeAssignments);
    }

    public function test_active_scope_returns_only_current_assignments(): void
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

        $active = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id + 100,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertTrue(
            RoleAssignment::active()
                ->whereKey($active->id)
                ->exists()
        );

        $this->assertCount(
            1,
            RoleAssignment::active()->get()
        );
    }
}