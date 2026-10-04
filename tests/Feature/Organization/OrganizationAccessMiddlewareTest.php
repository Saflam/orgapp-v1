<?php

namespace Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class OrganizationAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_access_their_organization(): void
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
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->get('http://org1.lvh.me:8000/dashboard');

        $response->assertOk();
    }

    public function test_user_cannot_access_another_organization(): void
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

        $this->actingAs($user);

        $response = $this->get('http://org2.lvh.me:8000/dashboard');

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_an_organization(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $response = $this->get(
            'http://org1.lvh.me:8000/dashboard'
        );

        $response->assertUnauthorized();
    }
}