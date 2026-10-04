<?php

namespace Modules\Core\Tests\Feature\Authorization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Modules\Core\Services\SuperAdminProvisioningService;
use Tests\TestCase;

class SuperAdminProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AuthorizationDefinitionProvisioningService::class)
            ->provision();
    }

    public function test_it_creates_the_super_admin_user(): void
    {
        $organization = Organization::factory()->create();

        $user = app(SuperAdminProvisioningService::class)->provision(
            $organization,
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+919876543210',
            ],
        );

        $this->assertInstanceOf(User::class, $user);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+919876543210',
        ]);
    }

    public function test_it_creates_an_active_organization_membership(): void
    {
        $organization = Organization::factory()->create();

        $user = app(SuperAdminProvisioningService::class)->provision(
            $organization,
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        );

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_it_assigns_the_super_admin_role(): void
    {
        $organization = Organization::factory()->create();

        $user = app(SuperAdminProvisioningService::class)->provision(
            $organization,
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        );

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $role = Role::query()
            ->where('code', 'super_admin')
            ->whereNull('organization_id')
            ->where('is_system', true)
            ->firstOrFail();

        $this->assertDatabaseHas('role_assignments', [
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);
    }

    public function test_super_admin_role_assignment_is_active_from_now(): void
    {
        $organization = Organization::factory()->create();

        $user = app(SuperAdminProvisioningService::class)->provision(
            $organization,
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        );

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $assignment = RoleAssignment::query()
            ->where('organization_membership_id', $membership->id)
            ->firstOrFail();

        $this->assertTrue($assignment->isActive());
    }
}