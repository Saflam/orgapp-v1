<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationModule;
use Tests\TestCase;

class OrganizationLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AuthorizationDefinitionProvisioningService::class)
            ->provision();
    }

    public function test_system_admin_can_activate_an_inactive_organization(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->actingAs($systemAdmin, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/activate"
            );

        $response
            ->assertOk()
            ->assertJson([
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'code' => $organization->code,
                    'slug' => $organization->slug,
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => true,
        ]);
    }

    public function test_system_admin_can_deactivate_an_active_organization(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($systemAdmin, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/deactivate"
            );

        $response
            ->assertOk()
            ->assertJson([
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'code' => $organization->code,
                    'slug' => $organization->slug,
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => false,
        ]);
    }

    public function test_non_system_admin_cannot_activate_an_organization(): void
    {
        $user = User::factory()->create([
            'is_system_admin' => false,
        ]);

        $organization = Organization::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/activate"
            );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Only the system administrator can access this resource.',
            ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => false,
        ]);
    }

    public function test_unauthenticated_user_cannot_deactivate_an_organization(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->postJson(
            "/api/v1/system/organizations/{$organization->id}/deactivate"
        );

        $response->assertUnauthorized();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => true,
        ]);
    }

    public function test_deactivating_an_organization_preserves_membership(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $member = User::factory()->create();

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);

        $this->actingAs($systemAdmin, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/deactivate"
            )
            ->assertOk();

        $this->assertDatabaseHas('organization_memberships', [
            'id' => $membership->id,
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);
    }

    public function test_deactivating_an_organization_preserves_module_configuration(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        OrganizationModule::create([
            'organization_id' => $organization->id,
            'module' => 'Member',
            'is_enabled' => true,
        ]);

        $this->actingAs($systemAdmin, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/deactivate"
            )
            ->assertOk();

        $this->assertDatabaseHas('organization_modules', [
            'organization_id' => $organization->id,
            'module' => 'Member',
            'is_enabled' => true,
        ]);
    }

    public function test_non_system_admin_cannot_deactivate_an_organization(): void
    {
        $user = User::factory()->create([
            'is_system_admin' => false,
        ]);

        $organization = Organization::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/v1/system/organizations/{$organization->id}/deactivate"
            );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Only the system administrator can access this resource.',
            ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_active' => true,
        ]);
    }

    public function test_activating_an_unknown_organization_returns_not_found(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($systemAdmin, 'sanctum')
            ->postJson('/api/v1/system/organizations/999999/activate');

        $response->assertNotFound();
    }
}