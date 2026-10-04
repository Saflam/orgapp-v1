<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Models\Designation;
use Modules\Core\Models\Permission;
use Modules\Committee\Services\DesignationPermissionService;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class DesignationPermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createDesignation(): Designation
    {
        $organization = Organization::factory()->create();

        return Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
            'is_active' => true,
        ]);
    }

    private function createPermission(
        string $code = 'finance.accounts.view',
    ): Permission {
        return Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => $code,
        ]);
    }

    public function test_permission_can_be_assigned_to_designation(): void
    {
        $designation = $this->createDesignation();
        $permission = $this->createPermission();

        $service = app(DesignationPermissionService::class);

        $service->assign($designation, $permission);

        $this->assertTrue(
            $service->has($designation, $permission)
        );
    }

    public function test_assigning_same_permission_twice_does_not_duplicate_it(): void
    {
        $designation = $this->createDesignation();
        $permission = $this->createPermission();

        $service = app(DesignationPermissionService::class);

        $service->assign($designation, $permission);
        $service->assign($designation, $permission);

        $this->assertCount(
            1,
            $service->permissions($designation)
        );
    }

    public function test_permission_can_be_removed_from_designation(): void
    {
        $designation = $this->createDesignation();
        $permission = $this->createPermission();

        $service = app(DesignationPermissionService::class);

        $service->assign($designation, $permission);
        $service->remove($designation, $permission);

        $this->assertFalse(
            $service->has($designation, $permission)
        );
    }

    public function test_has_returns_false_when_permission_is_not_assigned(): void
    {
        $designation = $this->createDesignation();
        $permission = $this->createPermission();

        $service = app(DesignationPermissionService::class);

        $this->assertFalse(
            $service->has($designation, $permission)
        );
    }

    public function test_permissions_returns_all_assigned_permissions(): void
    {
        $designation = $this->createDesignation();

        $view = $this->createPermission(
            'finance.accounts.view'
        );

        $create = $this->createPermission(
            'finance.transactions.create'
        );

        $service = app(DesignationPermissionService::class);

        $service->assign($designation, $view);
        $service->assign($designation, $create);

        $permissions = $service->permissions($designation);

        $this->assertCount(2, $permissions);

        $this->assertTrue(
            $permissions->contains(
                fn (Permission $permission) =>
                    $permission->code === 'finance.accounts.view'
            )
        );

        $this->assertTrue(
            $permissions->contains(
                fn (Permission $permission) =>
                    $permission->code === 'finance.transactions.create'
            )
        );
    }

    public function test_sync_replaces_existing_permissions(): void
    {
        $designation = $this->createDesignation();

        $view = $this->createPermission(
            'finance.accounts.view'
        );

        $create = $this->createPermission(
            'finance.transactions.create'
        );

        $approve = $this->createPermission(
            'finance.transactions.approve'
        );

        $service = app(DesignationPermissionService::class);

        $service->assign($designation, $view);
        $service->assign($designation, $create);

        $service->sync(
            $designation,
            [$approve->id]
        );

        $permissions = $service->permissions($designation);

        $this->assertCount(1, $permissions);

        $this->assertSame(
            'finance.transactions.approve',
            $permissions->first()->code
        );
    }

    public function test_sync_with_empty_permissions_removes_all_permissions(): void
    {
        $designation = $this->createDesignation();

        $permission = $this->createPermission();

        $service = app(DesignationPermissionService::class);

        $service->assign($designation, $permission);

        $service->sync($designation, []);

        $this->assertCount(
            0,
            $service->permissions($designation)
        );
    }
}