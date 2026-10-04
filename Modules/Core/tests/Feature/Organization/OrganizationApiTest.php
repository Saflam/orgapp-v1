<?php

namespace Modules\Core\Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthenticationService;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_access_their_organization_api(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organization = Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
            'name' => 'Organization One',
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $token = app(AuthenticationService::class)
            ->createApiToken($user);

        $response = $this->withToken($token)
            ->getJson(
                'http://org1.lvh.me/api/v1/organization'
            );

        $response
            ->assertOk()
            ->assertJson([
                'organization' => [
                    'id' => $organization->id,
                    'name' => 'Organization One',
                    'slug' => 'org1',
                ],
            ]);
    }

    public function test_a_member_cannot_access_another_organization_api(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organizationOne = Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $organizationTwo = Organization::factory()->create([
            'slug' => 'org2',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organizationOne->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $token = app(AuthenticationService::class)
            ->createApiToken($user);

        $response = $this->withToken($token)
            ->getJson(
                'http://org2.lvh.me/api/v1/organization'
            );

        $response->assertForbidden();
    }

    public function test_an_unknown_organization_returns_not_found(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $user = User::factory()->create();

        $token = app(AuthenticationService::class)
            ->createApiToken($user);

        $response = $this->withToken($token)
            ->getJson(
                'http://unknown.lvh.me/api/v1/organization'
            );

        $response->assertNotFound();
    }

    public function test_an_unauthenticated_user_cannot_access_an_organization_api(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            'http://org1.lvh.me/api/v1/organization'
        );

        $response->assertUnauthorized();
    }

    public function test_an_inactive_membership_cannot_access_the_organization_api(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $organization = Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'inactive',
        ]);

        $token = app(AuthenticationService::class)
            ->createApiToken($user);

        $response = $this->withToken($token)
            ->getJson(
                'http://org1.lvh.me/api/v1/organization'
            );

        $response->assertForbidden();
    }

    public function test_an_inactive_organization_cannot_be_accessed(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => false,
        ]);

        $user = User::factory()->create();

        $token = app(AuthenticationService::class)
            ->createApiToken($user);

        $response = $this->withToken($token)
            ->getJson(
                'http://org1.lvh.me/api/v1/organization'
            );

        $response->assertNotFound();
    }
}