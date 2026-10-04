<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Role;
use Modules\Core\Models\RoleAssignment;
use Tests\TestCase;

class SuperAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AuthorizationDefinitionProvisioningService::class)
            ->provision();
    }

    public function test_system_admin_can_replace_the_organization_super_admin(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create();

        $oldSuperAdmin = User::factory()->create();

        $oldMembership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $oldSuperAdmin->id,
            'status' => 'active',
        ]);

        $role = Role::query()
            ->whereNull('organization_id')
            ->where('code', 'super_admin')
            ->firstOrFail();

        $oldAssignment = RoleAssignment::create([
            'organization_membership_id' => $oldMembership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subDay(),
        ]);

        $newSuperAdmin = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $newSuperAdmin->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($systemAdmin, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/super-admin",
                [
                    'user_id' => $newSuperAdmin->id,
                ],
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Organization Super Admin replaced successfully.',
            ]);

        $this->assertNotNull(
            $oldAssignment->fresh()->ends_at
        );

        $this->assertDatabaseHas('role_assignments', [
            'organization_membership_id' =>
                OrganizationMembership::query()
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $newSuperAdmin->id)
                    ->firstOrFail()
                    ->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
        ]);
    }

    public function test_system_admin_can_remove_the_organization_super_admin(): void
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
            ->whereNull('organization_id')
            ->where('code', 'super_admin')
            ->firstOrFail();

        $assignment = RoleAssignment::create([
            'organization_membership_id' => $membership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($systemAdmin, 'sanctum')
            ->deleteJson(
                "/api/v1/system/organizations/{$organization->id}/super-admin"
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Organization Super Admin removed successfully.',
            ]);

        $this->assertNotNull(
            $assignment->fresh()->ends_at
        );
    }

    public function test_non_system_admin_cannot_replace_the_super_admin(): void
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

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/super-admin",
                [
                    'user_id' => $newSuperAdmin->id,
                ],
            );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'Only the system administrator can access this resource.',
            ]);
    }

    public function test_non_system_admin_cannot_remove_the_super_admin(): void
    {
        $user = User::factory()->create([
            'is_system_admin' => false,
        ]);

        $organization = Organization::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/v1/system/organizations/{$organization->id}/super-admin"
            );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'Only the system administrator can access this resource.',
            ]);
    }

    public function test_unauthenticated_user_cannot_replace_the_super_admin(): void
    {
        $organization = Organization::factory()->create();

        $newSuperAdmin = User::factory()->create();

        $response = $this->postJson(
            "/api/v1/system/organizations/{$organization->id}/super-admin",
            [
                'user_id' => $newSuperAdmin->id,
            ],
        );

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_remove_the_super_admin(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->deleteJson(
            "/api/v1/system/organizations/{$organization->id}/super-admin"
        );

        $response->assertUnauthorized();
    }

    public function test_replacement_requires_an_active_organization_membership(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create();

        $newSuperAdmin = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $newSuperAdmin->id,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($systemAdmin, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/super-admin",
                [
                    'user_id' => $newSuperAdmin->id,
                ],
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'user',
        ]);
    }

    public function test_replacement_does_not_end_the_previous_membership(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create();

        $oldSuperAdmin = User::factory()->create();

        $oldMembership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $oldSuperAdmin->id,
            'status' => 'active',
        ]);

        $role = Role::query()
            ->whereNull('organization_id')
            ->where('code', 'super_admin')
            ->firstOrFail();

        RoleAssignment::create([
            'organization_membership_id' => $oldMembership->id,
            'role_id' => $role->id,
            'context_type' => 'organization',
            'context_id' => $organization->id,
            'starts_at' => now()->subDay(),
        ]);

        $newSuperAdmin = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $newSuperAdmin->id,
            'status' => 'active',
        ]);

        $this->actingAs($systemAdmin, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/super-admin",
                [
                    'user_id' => $newSuperAdmin->id,
                ],
            )
            ->assertOk();

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $oldMembership->id,
            'organization_id' => $organization->id,
            'user_id' => $oldSuperAdmin->id,
            'status' => 'active',
        ]);
    }

    public function test_replacing_an_unknown_organization_returns_not_found(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $newSuperAdmin = User::factory()->create();

        $response = $this->actingAs($systemAdmin, 'sanctum')
            ->postJson(
                '/api/v1/system/organizations/999999/super-admin',
                [
                    'user_id' => $newSuperAdmin->id,
                ],
            );

        $response->assertNotFound();
    }
}