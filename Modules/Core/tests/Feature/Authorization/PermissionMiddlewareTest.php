<?php

namespace Modules\Core\Tests\Feature\Authorization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Unit;
use Modules\Core\Services\RoleAssignmentService;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private RoleAssignmentService $roleAssignmentService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'example.test',
        ]);

        $this->roleAssignmentService = app(RoleAssignmentService::class);

        /*
         * Organization-level authorization test route.
         */
        Route::middleware([
            'auth',
            'organization',
            'organization.access',
            SubstituteBindings::class,
            'permission:members.view',
        ])->get('/__test/permission', function () {
            return response()->json([
                'message' => 'allowed',
            ]);
        });

        /*
         * Unit-level authorization test route.
         *
         * The Unit type-hint enables Laravel implicit
         * route model binding.
         */
        Route::middleware([
            'auth',
            'organization',
            'organization.access',
            SubstituteBindings::class,
            'permission:members.view',
        ])->get(
            '/__test/units/{unit}/members',
            function (Unit $unit) {
                return response()->json([
                    'message' => 'allowed',
                    'unit_id' => $unit->id,
                ]);
            }
        );
    }

    public function test_an_unauthenticated_user_is_rejected(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $response = $this->getJson(
            $this->organizationUrl($organization)
        );

        $response->assertUnauthorized();
    }

    public function test_a_user_without_the_permission_is_rejected(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $user = User::factory()->create();

        $this->createMembership(
            $organization,
            $user,
        );

        $this->createPermission('members.view');

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl($organization)
            );

        $response->assertForbidden();
    }

    public function test_a_user_with_the_permission_is_allowed(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user,
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl($organization)
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'allowed',
            ]);
    }

    public function test_an_inactive_membership_is_rejected(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user,
            'inactive',
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl($organization)
            );

        $response->assertForbidden();
    }

    public function test_a_user_from_another_organization_is_rejected(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $otherOrganization = Organization::factory()->create([
            'slug' => 'org-two',
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user,
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl($otherOrganization)
            );

        $response->assertForbidden();
    }

    public function test_an_organization_scoped_permission_allows_access_to_a_unit(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user,
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl(
                    $organization,
                    '/__test/units/' . $unit->id . '/members'
                )
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'allowed',
                'unit_id' => $unit->id,
            ]);
    }

    public function test_a_unit_scoped_permission_allows_access_to_the_assigned_unit(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user,
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $unit,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl(
                    $organization,
                    '/__test/units/' . $unit->id . '/members'
                )
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'allowed',
                'unit_id' => $unit->id,
            ]);
    }

    public function test_a_unit_scoped_permission_cannot_access_another_unit(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $assignedUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $otherUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user,
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $assignedUnit,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl(
                    $organization,
                    '/__test/units/' . $otherUnit->id . '/members'
                )
            );

        $response->assertForbidden();
    }

    public function test_a_unit_from_another_organization_is_not_a_valid_context(): void
    {
        $organization = Organization::factory()->create([
            'slug' => 'org-one',
        ]);

        $otherOrganization = Organization::factory()->create([
            'slug' => 'org-two',
        ]);

        $unit = Unit::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $user = User::factory()->create();

        $membership = $this->createMembership(
            $organization,
            $user,
        );

        $permission = $this->createPermission('members.view');

        $role = $this->createRole($organization);

        $role->permissions()->attach($permission);

        $this->roleAssignmentService->assign(
            $membership,
            $role,
            $organization,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                $this->organizationUrl(
                    $organization,
                    '/__test/units/' . $unit->id . '/members'
                )
            );

        $response->assertNotFound();
    }

    private function createMembership(
        Organization $organization,
        User $user,
        string $status = 'active',
    ): OrganizationMembership {
        return OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    private function createPermission(string $code): Permission
    {
        return Permission::create([
            'module' => 'member',
            'name' => str($code)->headline(),
            'code' => $code,
        ]);
    }

    private function createRole(
        Organization $organization,
    ): Role {
        return Role::create([
            'organization_id' => $organization->id,
            'name' => 'Test Administrator',
            'code' => 'test_admin_' . uniqid(),
        ]);
    }

    private function organizationUrl(
        Organization $organization,
        string $path = '/__test/permission',
    ): string {
        return 'http://'
            . $organization->slug
            . '.example.test'
            . $path;
    }
}