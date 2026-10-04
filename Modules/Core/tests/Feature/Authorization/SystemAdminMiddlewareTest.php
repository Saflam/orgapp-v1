<?php

namespace Modules\Core\Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\AuthorizationDefinitionProvisioningService;
use Tests\TestCase;

class SystemAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AuthorizationDefinitionProvisioningService::class)
            ->provision();
    }

    public function test_system_admin_can_access_system_admin_route(): void
    {
        $systemAdmin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($systemAdmin, 'sanctum')
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Test Organization',
                    'code' => 'TEST-ORG',
                ],
                'super_admin' => [
                    'name' => 'Organization Admin',
                    'email' => 'admin@example.com',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'organization.name',
                'Test Organization'
            );
    }

    public function test_non_system_admin_cannot_access_system_admin_route(): void
    {
        $user = User::factory()->create([
            'is_system_admin' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/system/organizations', [
                'organization' => [
                    'name' => 'Test Organization',
                    'code' => 'TEST-ORG',
                ],
                'super_admin' => [
                    'name' => 'Organization Admin',
                    'email' => 'admin@example.com',
                ],
            ]);

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Only the system administrator can access this resource.',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_system_admin_route(): void
    {
        $response = $this->postJson('/api/v1/system/organizations', [
            'organization' => [
                'name' => 'Test Organization',
                'code' => 'TEST-ORG',
            ],
            'super_admin' => [
                'name' => 'Organization Admin',
                'email' => 'admin@example.com',
            ],
        ]);

        $response->assertUnauthorized();
    }
}