<?php

namespace Modules\Core\Tests\Feature\Authorization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Modules\Core\Services\SuperAdminService;
use Tests\TestCase;

class SuperAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AuthorizationDefinitionProvisioningService::class)
            ->provision();
    }

    public function test_system_admin_can_replace_the_super_admin(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create();

        $oldSuperAdmin = User::factory()->create();
        $newSuperAdmin = User::factory()->create();

        $oldMembership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $oldSuperAdmin->id,
            'status' => 'active',
        ]);

        $newMembership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $newSuperAdmin->id,
            'status' => 'active',
        ]);

        $role = Role::query()
            ->where('code', 'super_admin')
            ->whereNull('organization_id')
            ->where('is_system', true)
            ->firstOrFail();

        $oldAssignment = RoleAssignment::create([
            'organization_membership_id' => $oldMembership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subDay(),
        ]);

        $this->actingAs($systemAdmin);

        app(SuperAdminService::class)->replace(
            $organization,
            $newSuperAdmin,
        );

        $this->assertNotNull(
            $oldAssignment->fresh()->ends_at
        );

        $this->assertDatabaseHas('role_assignments', [
            'organization_membership_id' => $newMembership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $oldMembership->id,
            'status' => 'active',
        ]);
    }

    public function test_non_system_admin_cannot_replace_super_admin(): void
    {
        $user = User::factory()->create([
            'is_system_admin' => false,
        ]);

        $organization = Organization::factory()->create();
        $newSuperAdmin = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $newSuperAdmin->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        app(SuperAdminService::class)->replace(
            $organization,
            $newSuperAdmin,
        );
    }

    public function test_system_admin_can_remove_super_admin(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create();
        $superAdmin = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $superAdmin->id,
            'status' => 'active',
        ]);

        $role = Role::query()
            ->where('code', 'super_admin')
            ->whereNull('organization_id')
            ->where('is_system', true)
            ->firstOrFail();

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subDay(),
        ]);

        $this->actingAs($systemAdmin);

        app(SuperAdminService::class)->remove($organization);

        $this->assertNotNull(
            $assignment->fresh()->ends_at
        );

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $membership->id,
            'status' => 'active',
        ]);
    }

    public function test_replacement_requires_active_organization_membership(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create();
        $newSuperAdmin = User::factory()->create();

        $this->actingAs($systemAdmin);

        $this->expectException(ValidationException::class);

        app(SuperAdminService::class)->replace(
            $organization,
            $newSuperAdmin,
        );
    }

    public function test_replacing_super_admin_preserves_old_membership(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create();

        $oldSuperAdmin = User::factory()->create();
        $newSuperAdmin = User::factory()->create();

        $oldMembership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $oldSuperAdmin->id,
            'status' => 'active',
        ]);

        $newMembership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $newSuperAdmin->id,
            'status' => 'active',
        ]);

        $role = Role::query()
            ->whereNull('organization_id')
            ->where('code', 'super_admin')
            ->where('is_system', true)
            ->firstOrFail();

        $oldAssignment = RoleAssignment::create([
            'organization_membership_id' => $oldMembership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subDay(),
        ]);

        $this->actingAs($systemAdmin);

        app(SuperAdminService::class)->replace(
            $organization,
            $newSuperAdmin,
        );

        $this->assertNotNull(
            $oldAssignment->fresh()->ends_at
        );

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $oldMembership->id,
            'organization_id' => $organization->id,
            'user_id' => $oldSuperAdmin->id,
            'status' => 'active',
        ]);
    }
}