<?php

namespace Tests\Feature\Organization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\RoleAssignmentService;
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

        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // The current /dashboard route renders the organization admin dashboard,
        // which intentionally requires a management permission in addition to
        // active organization membership.
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'organization.settings.manage'],
            ['module' => 'Core', 'name' => 'Manage organization settings'],
        );
        $role = Role::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organization Settings Manager',
            'code' => 'organization_settings_manager_' . uniqid(),
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);
        app(RoleAssignmentService::class)->assign($membership, $role, $organization);

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

        // This is a browser (web) route protected by Laravel's auth middleware,
        // so guests are redirected to the login page rather than receiving an API 401.
        $response->assertRedirect(route('login')); 
    }
}