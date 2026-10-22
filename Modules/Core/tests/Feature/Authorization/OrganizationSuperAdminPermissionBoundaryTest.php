<?php

namespace Modules\Core\Tests\Feature\Authorization;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\RoleAssignmentService;
use Tests\TestCase;

class OrganizationSuperAdminPermissionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_super_admin_can_use_non_finance_permissions_without_individual_role_grants(): void
    {
        [$organization, $user] = $this->createOrganizationSuperAdmin();
        Permission::create([
            'module' => 'member',
            'name' => 'View Membership Applications',
            'code' => 'membership.application.view',
        ]);

        $this->assertTrue(app(AuthorizationService::class)->can(
            $user,
            'membership.application.view',
            new \Modules\Core\Data\AuthorizationContext((int) $organization->id),
        ));
    }

    public function test_organization_super_admin_cannot_use_finance_module_permissions(): void
    {
        [$organization, $user] = $this->createOrganizationSuperAdmin();
        Permission::create([
            'module' => 'Finance',
            'name' => 'View Balance Sheet',
            'code' => 'finance.balance-sheet.view',
        ]);

        $this->assertFalse(app(AuthorizationService::class)->can(
            $user,
            'finance.balance-sheet.view',
            new \Modules\Core\Data\AuthorizationContext((int) $organization->id),
        ));
    }

    public function test_system_admin_does_not_receive_organization_permissions(): void
    {
        [$organization, $user] = $this->createOrganizationSuperAdmin(['is_system_admin' => true]);
        Permission::create([
            'module' => 'member',
            'name' => 'View Membership Applications',
            'code' => 'membership.application.view',
        ]);

        $this->assertFalse(app(AuthorizationService::class)->can(
            $user,
            'membership.application.view',
            new \Modules\Core\Data\AuthorizationContext((int) $organization->id),
        ));
    }

    public function test_organization_super_admin_does_not_receive_unknown_permissions(): void
    {
        [$organization, $user] = $this->createOrganizationSuperAdmin();

        $this->assertFalse(app(AuthorizationService::class)->can(
            $user,
            'unknown.module.permission',
            new \Modules\Core\Data\AuthorizationContext((int) $organization->id),
        ));
    }

    /** @return array{Organization, User} */
    private function createOrganizationSuperAdmin(array $userAttributes = []): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create($userAttributes);
        $membership = OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $role = Role::create([
            'organization_id' => null,
            'name' => 'Super Administrator',
            'code' => 'super_admin',
            'description' => 'Full administrative access within the organization.',
            'is_system' => true,
        ]);

        app(RoleAssignmentService::class)->assign(
            $membership,
            $role,
            $organization,
            startsAt: now()->subMinute(),
        );

        return [$organization, $user];
    }
}
