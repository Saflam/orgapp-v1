<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Models\Designation;
use Modules\Core\Models\Permission;
use Modules\Committee\Services\PermissionService;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_permission(): void
    {
        $service = app(PermissionService::class);

        $permission = $service->create(
            module: 'finance',
            name: 'View Accounts',
            code: 'finance.accounts.view',
        );

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'module' => 'finance',
            'code' => 'finance.accounts.view',
        ]);
    }

    public function test_it_assigns_a_permission_to_a_designation(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
        ]);

        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $service = app(PermissionService::class);

        $service->assignToDesignation(
            designation: $designation,
            permission: $permission,
        );

        $this->assertDatabaseHas('designation_permissions', [
            'designation_id' => $designation->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_assigning_same_permission_twice_does_not_duplicate_it(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
        ]);

        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $service = app(PermissionService::class);

        $service->assignToDesignation($designation, $permission);
        $service->assignToDesignation($designation, $permission);

        $this->assertDatabaseCount('designation_permissions', 1);
    }

    public function test_it_removes_a_permission_from_a_designation(): void
    {
        $organization = Organization::factory()->create();

        $designation = Designation::create([
            'organization_id' => $organization->id,
            'name' => 'Treasurer',
            'code' => 'TREASURER',
        ]);

        $permission = Permission::create([
            'module' => 'finance',
            'name' => 'View Accounts',
            'code' => 'finance.accounts.view',
        ]);

        $service = app(PermissionService::class);

        $service->assignToDesignation($designation, $permission);
        $service->removeFromDesignation($designation, $permission);

        $this->assertDatabaseMissing('designation_permissions', [
            'designation_id' => $designation->id,
            'permission_id' => $permission->id,
        ]);
    }
}